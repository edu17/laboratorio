<?php

namespace App\Actions\Results;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\ResultValueRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveOrderExamResultDraft
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(OrderExam $orderExam, array $data, User $actor): OrderExam
    {
        Gate::forUser($actor)->authorize('captureResults', $orderExam);

        $values = $data['values'] ?? [];
        $sectionStatuses = $data['section_statuses'] ?? [];

        if (! is_array($values) || ! is_array($sectionStatuses)) {
            throw ValidationException::withMessages(['values' => 'Los resultados enviados tienen un formato inválido.']);
        }

        $snapshot = $orderExam->catalog_snapshot ?? [];
        $definitions = $this->getParameterDefinitions($snapshot);
        $allowedCodes = array_column($definitions, 'code');
        $unknownCodes = array_diff(array_keys($values), $allowedCodes);

        if ($unknownCodes !== []) {
            throw ValidationException::withMessages(['values' => 'La captura contiene parámetros que no pertenecen al examen solicitado.']);
        }

        $matrixSections = $this->getMatrixSections($snapshot);
        $unknownSections = array_diff(array_keys($sectionStatuses), array_keys($matrixSections));

        if ($unknownSections !== []) {
            throw ValidationException::withMessages(['section_statuses' => 'El estado indicado no pertenece a una matriz del examen.']);
        }

        $normalizedSectionStatuses = [];
        $storedSectionStatuses = $orderExam->section_statuses ?? [];

        foreach ($matrixSections as $sectionCode => $parameterCodes) {
            $status = $sectionStatuses[$sectionCode] ?? $storedSectionStatuses[$sectionCode] ?? OrderExam::SECTION_STATUS_PENDING;

            if (! in_array($status, [
                OrderExam::SECTION_STATUS_PENDING,
                OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS,
                OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS,
            ], true)) {
                throw ValidationException::withMessages([
                    "section_statuses.{$sectionCode}" => 'Selecciona un estado permitido para completar la matriz.',
                ]);
            }

            $normalizedSectionStatuses[$sectionCode] = $status;
        }

        $normalizedValues = [];

        foreach ($definitions as $definition) {
            $submitted = $values[$definition['code']] ?? [];

            if (! is_array($submitted)) {
                throw ValidationException::withMessages(["values.{$definition['code']}" => 'El resultado tiene un formato inválido.']);
            }

            if ($definition['matrix']) {
                $cells = $submitted['cells'] ?? [];

                if (! is_array($cells) || array_diff(array_keys($cells), $definition['column_keys']) !== []) {
                    throw ValidationException::withMessages(["values.{$definition['code']}.cells" => 'La fila contiene columnas no permitidas.']);
                }

                $normalizedCells = [];

                foreach ($definition['column_keys'] as $columnKey) {
                    $cell = $cells[$columnKey] ?? null;

                    Validator::make(
                        ['value' => $cell],
                        ['value' => ['nullable', 'string', 'max:2000']],
                    )->validate();

                    $normalizedCells[$columnKey] = filled($cell) ? trim($cell) : null;
                }

                $normalizedValues[$definition['code']] = ['cells' => $normalizedCells];

                continue;
            }

            $value = $submitted['value'] ?? null;

            if (in_array($definition['type'], ['select', 'positive_negative'], true) && $definition['options'] === []) {
                throw ValidationException::withMessages([
                    "values.{$definition['code']}" => 'El catálogo todavía no define las opciones permitidas para este parámetro.',
                ]);
            }

            $rules = match ($definition['type']) {
                'number' => ['nullable', 'numeric', 'max:99999999999999999999999999999999999999'],
                'select', 'positive_negative' => $definition['options'] !== []
                    ? ['nullable', 'string', Rule::in($definition['options'])]
                    : ['nullable', 'string', 'max:2000'],
                default => ['nullable', 'string', 'max:2000'],
            };

            Validator::make(['value' => $value], ['value' => $rules])->validate();

            $normalizedValues[$definition['code']] = [
                'value' => filled($value) ? trim((string) $value) : null,
            ];
        }

        foreach ($matrixSections as $sectionCode => $parameterCodes) {
            $hasFindings = collect($parameterCodes)
                ->flatMap(fn (string $parameterCode): array => array_values($normalizedValues[$parameterCode]['cells'] ?? []))
                ->contains(fn (mixed $cell): bool => filled($cell));
            $status = $normalizedSectionStatuses[$sectionCode];

            if ($status === OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS && ! $hasFindings) {
                throw ValidationException::withMessages([
                    "section_statuses.{$sectionCode}" => 'Registra al menos un hallazgo o indica que la matriz se completó sin hallazgos.',
                ]);
            }

            if ($status === OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS && $hasFindings) {
                throw ValidationException::withMessages([
                    "section_statuses.{$sectionCode}" => 'La matriz marcada sin hallazgos debe dejar todas sus celdas vacías.',
                ]);
            }
        }

        return DB::transaction(function () use ($orderExam, $normalizedValues, $normalizedSectionStatuses, $actor): OrderExam {
            $orderId = OrderExam::query()->whereKey($orderExam->getKey())->value('order_id');
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($orderId);
            $lockedOrderExam = OrderExam::query()
                ->where('order_id', $lockedOrder->getKey())
                ->lockForUpdate()
                ->findOrFail($orderExam->getKey());
            $lockedOrderExam->setRelation('order', $lockedOrder);

            Gate::forUser($actor)->authorize('captureResults', $lockedOrderExam);

            foreach ($normalizedValues as $parameterCode => $value) {
                $result = $lockedOrderExam->resultValues()
                    ->where('parameter_code', $parameterCode)
                    ->lockForUpdate()
                    ->first();

                if (! $result) {
                    $lockedOrderExam->resultValues()->create([
                        'parameter_code' => $parameterCode,
                        'value' => $value,
                        'edited_by' => $actor->getKey(),
                    ]);

                    continue;
                }

                if ($result->value !== $value) {
                    ResultValueRevision::create([
                        'result_value_id' => $result->getKey(),
                        'previous_value' => $result->value,
                        'changed_by' => $actor->getKey(),
                        'changed_at' => now(),
                    ]);

                    $result->update([
                        'value' => $value,
                        'edited_by' => $actor->getKey(),
                    ]);
                }
            }

            $lockedOrderExam->update(['section_statuses' => $normalizedSectionStatuses]);

            if ($lockedOrderExam->status === OrderExam::STATUS_PENDING) {
                $lockedOrderExam->update(['status' => OrderExam::STATUS_IN_PROGRESS]);
            }

            if ($lockedOrder->status === Order::STATUS_PENDING) {
                $lockedOrder->update(['status' => Order::STATUS_IN_PROGRESS]);
            }

            AuditLog::create([
                'user_id' => $actor->getKey(),
                'action' => 'results_draft_saved',
                'entity_type' => $lockedOrderExam->getMorphClass(),
                'entity_id' => $lockedOrderExam->getKey(),
                'metadata' => ['parameter_count' => count($normalizedValues)],
            ]);

            return $lockedOrderExam->fresh(['order', 'resultValues']);
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array{code: string, type: string, options: list<string>, matrix: bool, column_keys: list<string>}>
     */
    private function getParameterDefinitions(array $snapshot): array
    {
        $definitions = [];

        foreach ($snapshot['sections'] ?? [] as $section) {
            $isMatrix = ($section['structure'] ?? null) === 'parasitological_matrix';
            $columns = $isMatrix ? array_slice($section['settings']['columns'] ?? [], 1) : [];

            foreach ($section['parameters'] ?? [] as $parameter) {
                $code = $parameter['code'] ?? null;

                if (! is_string($code) || $code === '') {
                    continue;
                }

                $definitions[] = [
                    'code' => $code,
                    'type' => $parameter['type'] ?? $parameter['result_type'] ?? 'text',
                    'options' => is_array($parameter['options'] ?? null)
                        ? array_values(array_filter($parameter['options'], 'is_string'))
                        : [],
                    'matrix' => $isMatrix,
                    'column_keys' => array_map(fn (string $column): string => Str::slug($column, '_'), $columns),
                ];
            }
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, list<string>>
     */
    private function getMatrixSections(array $snapshot): array
    {
        $sections = [];

        foreach ($snapshot['sections'] ?? [] as $section) {
            if (($section['structure'] ?? null) !== 'parasitological_matrix' || ! is_string($section['code'] ?? null)) {
                continue;
            }

            $sections[$section['code']] = array_values(array_filter(
                array_column($section['parameters'] ?? [], 'code'),
                'is_string',
            ));
        }

        return $sections;
    }
}
