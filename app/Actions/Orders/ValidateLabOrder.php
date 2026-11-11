<?php

namespace App\Actions\Orders;

use App\Models\AuditLog;
use App\Models\LabProfile;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\OrderValidationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ValidateLabOrder
{
    public function handle(Order $order, User $actor): Order
    {
        Gate::forUser($actor)->authorize('validateResults', $order);

        return DB::transaction(function () use ($order, $actor): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            Gate::forUser($actor)->authorize('validateResults', $lockedOrder);

            $orderExams = OrderExam::query()
                ->where('order_id', $lockedOrder->getKey())
                ->whereNotIn('status', [OrderExam::STATUS_CANCELED, OrderExam::STATUS_WITHDRAWN])
                ->with('resultValues')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($orderExams->isEmpty()) {
                throw ValidationException::withMessages([
                    'results' => 'La orden no contiene exámenes activos para validar.',
                ]);
            }

            $missingResults = [];
            $examSnapshots = [];
            $patient = $lockedOrder->patient()->lockForUpdate()->firstOrFail();

            foreach ($orderExams as $orderExam) {
                $storedValues = $orderExam->resultValues->keyBy('parameter_code');
                $parameterCount = 0;
                $examName = $orderExam->catalog_snapshot['exam']['name'] ?? $orderExam->exam->name;
                $requestedSnapshot = $orderExam->requestedCatalogSnapshot();
                $requestedCodes = array_flip($orderExam->requestedParameterCodes());

                foreach ($requestedSnapshot['sections'] ?? [] as $section) {
                    $isMatrix = ($section['structure'] ?? null) === 'parasitological_matrix';

                    if ($isMatrix) {
                        $matrixParameters = $section['parameters'] ?? [];
                        $parameterCount += count($matrixParameters);
                        $sectionCode = $section['code'] ?? null;
                        $sectionStatus = is_string($sectionCode)
                            ? ($orderExam->section_statuses[$sectionCode] ?? OrderExam::SECTION_STATUS_PENDING)
                            : OrderExam::SECTION_STATUS_PENDING;
                        $hasFindings = collect($matrixParameters)
                            ->flatMap(fn (array $parameter): array => array_values(
                                $storedValues->get($parameter['code'] ?? '')?->value['cells'] ?? [],
                            ))
                            ->contains(fn (mixed $cell): bool => filled($cell));
                        $isComplete = match ($sectionStatus) {
                            OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS => $hasFindings,
                            OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS => ! $hasFindings,
                            default => false,
                        };

                        if (! $isComplete) {
                            $missingResults[] = $examName.' / '.($section['name'] ?? 'Matriz parasitológica').' / Selecciona un estado y confirma si hay hallazgos.';
                        }

                        continue;
                    }

                    foreach ($section['parameters'] ?? [] as $parameter) {
                        $parameterCode = $parameter['code'] ?? null;

                        if (! is_string($parameterCode) || $parameterCode === '') {
                            continue;
                        }

                        $parameterCount++;
                        $value = $storedValues->get($parameterCode)?->value ?? [];
                        $value = is_array($value) ? $value : [];
                        $isComplete = filled($value['value'] ?? null);

                        if (! $isComplete) {
                            $missingResults[] = $examName.' / '.($section['name'] ?? 'Sección').' / '.($parameter['name'] ?? $parameterCode);
                        }
                    }
                }

                if ($parameterCount === 0) {
                    $missingResults[] = $examName.' / Sin parámetros configurados';
                }

                $examSnapshots[] = [
                    'order_exam_id' => $orderExam->getKey(),
                    'exam_id' => $orderExam->exam_id,
                    'catalog_snapshot' => $requestedSnapshot,
                    'section_statuses' => $orderExam->section_statuses ?? [],
                    'results' => $orderExam->resultValues
                        ->filter(fn ($result): bool => isset($requestedCodes[$result->parameter_code]))
                        ->sortBy('parameter_code')
                        ->map(fn ($result): array => [
                            'parameter_code' => $result->parameter_code,
                            'value' => $result->value,
                        ])
                        ->values()
                        ->all(),
                ];
            }

            if ($missingResults !== []) {
                throw ValidationException::withMessages([
                    'results' => 'Completa todos los parámetros antes de validar: '.implode('; ', $missingResults),
                ]);
            }

            $version = $lockedOrder->validation_version + 1;
            $validatedAt = now();
            $labProfile = LabProfile::query()->where('is_active', true)->firstOrFail();
            $resultsSnapshot = [
                'order' => [
                    'id' => $lockedOrder->getKey(),
                    'number' => $lockedOrder->number,
                    'referring_doctor' => $lockedOrder->referring_doctor,
                    'ordered_at' => $lockedOrder->ordered_at->toIso8601String(),
                ],
                'patient' => [
                    'id' => $patient->getKey(),
                    'first_names' => $patient->first_names,
                    'last_names' => $patient->last_names,
                    'birth_date' => $patient->birth_date?->toDateString(),
                    'sex' => $patient->sex,
                    'identifier' => $patient->identifier,
                ],
                'validated_by' => [
                    'id' => $actor->getKey(),
                    'name' => $actor->name,
                ],
                'validated_at' => $validatedAt->toIso8601String(),
                'version' => $version,
                'lab_profile' => [
                    'id' => $labProfile->getKey(),
                    'version' => $labProfile->version,
                    'institution_name' => $labProfile->institution_name,
                    'subtitle' => $labProfile->subtitle,
                    'address' => $labProfile->address,
                    'phone' => $labProfile->phone,
                    'email' => $labProfile->email,
                    'sanitary_registration' => $labProfile->sanitary_registration,
                    'emergency_phone' => $labProfile->emergency_phone,
                    'opening_hours' => $labProfile->opening_hours,
                    'logo_path' => $labProfile->logo_path,
                    'technical_signature_path' => $labProfile->technical_signature_path,
                    'biologist_signature_path' => $labProfile->biologist_signature_path,
                    'institutional_seal_path' => $labProfile->institutional_seal_path,
                ],
                'exams' => $examSnapshots,
            ];

            OrderValidationEvent::create([
                'order_id' => $lockedOrder->getKey(),
                'version' => $version,
                'action' => OrderValidationEvent::ACTION_VALIDATED,
                'actor_id' => $actor->getKey(),
                'results_snapshot' => $resultsSnapshot,
                'acted_at' => $validatedAt,
            ]);

            foreach ($orderExams as $orderExam) {
                $orderExam->update(['status' => OrderExam::STATUS_VALIDATED]);
            }

            $lockedOrder->update([
                'status' => Order::STATUS_VALIDATED,
                'validation_version' => $version,
                'validated_by' => $actor->getKey(),
                'validated_at' => $validatedAt,
            ]);

            AuditLog::create([
                'user_id' => $actor->getKey(),
                'action' => 'order_validated',
                'entity_type' => $lockedOrder->getMorphClass(),
                'entity_id' => $lockedOrder->getKey(),
                'metadata' => ['version' => $version, 'exam_count' => $orderExams->count()],
            ]);

            return $lockedOrder->fresh(['validatedBy', 'orderExams']);
        }, attempts: 3);
    }
}
