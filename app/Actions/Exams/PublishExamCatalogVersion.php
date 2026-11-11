<?php

namespace App\Actions\Exams;

use App\Models\Exam;
use App\Models\ExamCatalogVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishExamCatalogVersion
{
    public function handle(Exam $exam, User $user): ExamCatalogVersion
    {
        Gate::forUser($user)->authorize('update', $exam);

        return DB::transaction(function () use ($exam, $user): ExamCatalogVersion {
            $exam = Exam::query()
                ->lockForUpdate()
                ->with(['sections' => fn ($query) => $query->with('parameters')])
                ->findOrFail($exam->getKey());

            $versionNumber = ((int) $exam->catalogVersions()->max('version_number')) + 1;

            $snapshot = [
                'exam' => [
                    'code' => $exam->code,
                    'name' => $exam->name,
                    'source' => $exam->source,
                    'clinical_validation_status' => $exam->clinical_validation_status,
                    'is_active' => $exam->is_active,
                    'sort_order' => $exam->sort_order,
                ],
                'sections' => $exam->sections->map(fn ($section): array => [
                    'code' => $section->code,
                    'name' => $section->name,
                    'structure' => $section->structure,
                    'settings' => $section->settings,
                    'source' => $section->source,
                    'clinical_validation_status' => $section->clinical_validation_status,
                    'is_active' => $section->is_active,
                    'sort_order' => $section->sort_order,
                    'parameters' => $section->parameters->map(fn ($parameter): array => [
                        'code' => $parameter->code,
                        'name' => $parameter->name,
                        'result_type' => $parameter->result_type,
                        'unit' => $parameter->unit,
                        'reference_text' => $parameter->reference_text,
                        'reference_config' => $parameter->reference_config,
                        'options' => $parameter->options,
                        'source' => $parameter->source,
                        'clinical_validation_status' => $parameter->clinical_validation_status,
                        'is_active' => $parameter->is_active,
                        'sort_order' => $parameter->sort_order,
                    ])->all(),
                ])->all(),
            ];

            return ExamCatalogVersion::create([
                'exam_id' => $exam->getKey(),
                'version_number' => $versionNumber,
                'snapshot' => $snapshot,
                'created_by' => $user->getKey(),
                'published_at' => now(),
            ]);
        });
    }
}
