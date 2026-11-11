<?php

namespace App\Actions\Orders;

use App\Models\Exam;
use App\Models\LabProfile;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\OrderSequence;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateLabOrder
{
    public function __construct(
        private BuildOrderExamSnapshot $buildSnapshot,
        private ResolveRequestedParameterCodes $resolveRequestedCodes,
    ) {}

    /**
     * @param  list<int|string>  $examIds
     * @param  array<int|string, list<string>>  $requestedParameterCodes  Análisis solicitados por examen; si falta un examen, se pide completo.
     */
    public function handle(Patient $patient, array $examIds, User $actor, ?string $notes = null, ?string $referringDoctor = null, array $requestedParameterCodes = []): Order
    {
        Gate::forUser($actor)->authorize('create', Order::class);

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

        return DB::transaction(function () use ($patient, $examIds, $actor, $notes, $referringDoctor, $requestedParameterCodes): Order {
            $patient = Patient::query()->findOrFail($patient->getKey());
            $exams = Exam::query()
                ->whereIn('id', $examIds)
                ->where('is_active', true)
                ->with('latestCatalogVersion')
                ->get();

            if ($exams->count() !== count($examIds)) {
                throw ValidationException::withMessages([
                    'exam_ids' => 'Uno o más exámenes no están activos o ya no están disponibles.',
                ]);
            }

            $year = (int) now()->format('Y');
            $labProfile = LabProfile::query()->where('is_active', true)->firstOrFail();

            DB::table('order_sequences')->insertOrIgnore([
                'year' => $year,
                'next_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = OrderSequence::query()->where('year', $year)->lockForUpdate()->firstOrFail();
            $sequence->increment('next_number');

            $order = Order::create([
                'number' => sprintf(
                    '%s-%d-%0'.$labProfile->order_number_padding.'d',
                    $labProfile->order_prefix,
                    $year,
                    $sequence->next_number,
                ),
                'patient_id' => $patient->getKey(),
                'ordered_at' => now(),
                'status' => Order::STATUS_PENDING,
                'created_by' => $actor->getKey(),
                'notes' => $notes,
                'referring_doctor' => filled($referringDoctor) ? trim($referringDoctor) : null,
            ]);

            foreach ($exams as $exam) {
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

            return $order->load(['patient', 'creator', 'orderExams.exam']);
        });
    }
}
