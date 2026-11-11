<?php

namespace App\Actions\Exams;

use App\Models\Exam;
use Illuminate\Validation\ValidationException;

class BuildExamResultFormDefinition
{
    /**
     * @return array{
     *     exam: array{code: string, name: string},
     *     sections: list<array{
     *         code: string,
     *         name: string,
     *         structure: string,
     *         settings: array<string, mixed>|null,
     *         parameters: list<array<string, mixed>>
     *     }>
     * }
     */
    public function handle(Exam $exam): array
    {
        if (! $exam->is_active) {
            throw ValidationException::withMessages([
                'exam' => 'No se puede generar una captura para un examen inactivo.',
            ]);
        }

        $exam->load([
            'sections' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->with(['parameters' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')]),
        ]);

        $sections = $exam->sections->map(function ($section): array {
            $settings = $section->settings;

            if ($section->structure === 'parasitological_matrix' && count($settings['columns'] ?? []) < 2) {
                throw ValidationException::withMessages([
                    'settings.columns' => 'La matriz parasitológica debe definir al menos dos columnas.',
                ]);
            }

            return [
                'code' => $section->code,
                'name' => $section->name,
                'structure' => $section->structure,
                'settings' => $settings,
                'parameters' => $section->parameters->map(fn ($parameter): array => [
                    'code' => $parameter->code,
                    'name' => $parameter->name,
                    'type' => $parameter->result_type,
                    'unit' => $parameter->unit,
                    'reference' => $parameter->reference_text,
                    'reference_config' => $parameter->reference_config,
                    'options' => $parameter->options,
                    'clinical_validation_status' => $parameter->clinical_validation_status,
                    'order' => $parameter->sort_order,
                ])->all(),
            ];
        })->values()->all();

        return [
            'exam' => [
                'code' => $exam->code,
                'name' => $exam->name,
            ],
            'sections' => $sections,
        ];
    }
}
