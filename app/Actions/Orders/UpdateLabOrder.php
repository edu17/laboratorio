<?php

namespace App\Actions\Orders;

use App\Models\Exam;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateLabOrder
{
    public function __construct(
        private BuildOrderExamSnapshot $buildSnapshot,
        private ResolveRequestedParameterCodes $resolveRequestedCodes,
    ) {}

    /**
     * @param  list<int|string>  $examIds
     * @param  array<int|string, list<string>>  $requestedParameterCodes  Análisis solicitados por examen; si falta un examen, conserva lo que ya tenía.
     */
    public function handle(Order $order, Patient $patient, array $examIds, ?string $notes, User $actor, ?string $referringDoctor = null, array $requestedParameterCodes = []): Order
    {
        Gate::forUser($actor)->authorize('update', $order);

        $validated = Validator::make(
            ['exam_ids' => $examIds, 'referring_doctor' => $referringDoctor, 'requested_parameter_codes' => $requestedParameterCodes],
            [
                'exam_ids' => ['required', 'array', 'min:1'],
                'referring_doctor' => ['nullable', 'string', 'max:180'],
                'exam_ids.*' => ['required', 'integer', 'distinct', 'exists:exams,id'],
                'requested_parameter_codes' => ['array'],
                'requested_parameter_codes.*' => ['array'],
                'requested_parameter_codes.*.*' => ['string', 'max:100'],
            ],
        )->validate();

        $examIds = array_map('intval', $validated['exam_ids']);

        return DB::transaction(function () use ($order, $patient, $examIds, $notes, $referringDoctor, $requestedParameterCodes): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if (in_array($order->status, [Order::STATUS_CANCELED, Order::STATUS_VALIDATED, Order::STATUS_PUBLISHED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'La orden no puede editarse en su estado actual.',
                ]);
            }

            if ($order->orderExams()->whereHas('resultValues')->exists() && $order->patient_id !== $patient->getKey()) {
                throw ValidationException::withMessages([
                    'patient_id' => 'No se puede cambiar el paciente después de capturar resultados.',
                ]);
            }

            $patient = Patient::query()->findOrFail($patient->getKey());
            $existingOrderExams = $order->orderExams()->get()->keyBy('exam_id');
            $exams = Exam::query()
                ->whereIn('id', $examIds)
                ->with('latestCatalogVersion')
                ->get();

            if ($exams->count() !== count($examIds)) {
                throw ValidationException::withMessages([
                    'exam_ids' => 'Uno o más exámenes ya no están disponibles.',
                ]);
            }

            foreach ($exams as $exam) {
                if (! $exam->is_active && ! $existingOrderExams->has($exam->getKey())) {
                    throw ValidationException::withMessages([
                        'exam_ids' => 'No se pueden agregar exámenes inactivos a una orden.',
                    ]);
                }
            }

            $selectedIds = collect($examIds);

            foreach ($existingOrderExams as $existingOrderExam) {
                if (! $selectedIds->contains((int) $existingOrderExam->exam_id)
                    && ! in_array($existingOrderExam->status, [OrderExam::STATUS_CANCELED, OrderExam::STATUS_WITHDRAWN], true)) {
                    $existingOrderExam->update(['status' => OrderExam::STATUS_WITHDRAWN]);
                }
            }

            foreach ($exams as $exam) {
                $existingOrderExam = $existingOrderExams->get($exam->getKey());

                if ($existingOrderExam) {
                    if (array_key_exists($exam->getKey(), $requestedParameterCodes)) {
                        $this->updateRequestedParameters($existingOrderExam, $exam, $requestedParameterCodes[$exam->getKey()]);
                    }

                    if ($existingOrderExam->status === OrderExam::STATUS_WITHDRAWN) {
                        $existingOrderExam->update([
                            'status' => $existingOrderExam->resultValues()->exists()
                                ? OrderExam::STATUS_IN_PROGRESS
                                : OrderExam::STATUS_PENDING,
                        ]);
                    }

                    continue;
                }

                $snapshot = $this->buildSnapshot->handle($exam);

                OrderExam::create([
                    'order_id' => $order->getKey(),
                    'exam_id' => $exam->getKey(),
                    'exam_catalog_version_id' => $exam->latestCatalogVersion?->getKey(),
                    'catalog_snapshot' => $snapshot,
                    'requested_parameter_codes' => $this->resolveRequestedCodes->handle(
                        $exam,
                        $snapshot,
                        $requestedParameterCodes[$exam->getKey()] ?? null,
                    ),
                    'status' => OrderExam::STATUS_PENDING,
                ]);
            }

            $order->update([
                'patient_id' => $patient->getKey(),
                'notes' => $notes,
                'referring_doctor' => filled($referringDoctor) ? trim($referringDoctor) : null,
            ]);

            return $order->fresh(['patient', 'creator', 'orderExams.exam']);
        });
    }

    /**
     * Cambia los análisis solicitados sin permitir quitar uno que ya tiene resultado capturado.
     *
     * @param  list<string>  $codes
     */
    private function updateRequestedParameters(OrderExam $orderExam, Exam $exam, array $codes): void
    {
        $requestedCodes = $this->resolveRequestedCodes->handle($exam, $orderExam->catalog_snapshot ?? [], $codes);
        $previousCodes = $orderExam->requestedParameterCodes();

        $orderExam->requested_parameter_codes = $requestedCodes;
        $removedCodes = array_diff($previousCodes, $orderExam->requestedParameterCodes());

        $removedWithResults = $orderExam->resultValues()
            ->whereIn('parameter_code', $removedCodes)
            ->get()
            ->filter(fn ($result): bool => filled($result->value['value'] ?? null))
            ->pluck('parameter_code');

        if ($removedWithResults->isNotEmpty()) {
            $names = OrderExam::requestableParameters($orderExam->catalog_snapshot ?? []);

            throw ValidationException::withMessages([
                "requested_parameter_codes.{$exam->getKey()}" => 'No se puede quitar un análisis que ya tiene resultado capturado: '
                    .$removedWithResults->map(fn (string $code): string => $names[$code] ?? $code)->implode(', ').'.',
            ]);
        }

        if ($orderExam->isDirty('requested_parameter_codes')) {
            $orderExam->save();
        }
    }
}
