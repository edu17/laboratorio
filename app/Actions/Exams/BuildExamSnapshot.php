<?php

namespace App\Actions\Exams;

use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use Illuminate\Validation\ValidationException;

/**
 * Único formato del examen que se congela en versiones publicadas del catálogo y en las órdenes.
 * Solo incluye secciones y parámetros activos, en su orden, con los nombres de columna del catálogo.
 */
class BuildExamSnapshot
{
    /**
     * @return array{
     *     exam: array{code: string, name: string, source: string|null, clinical_validation_status: string},
     *     sections: list<array{
     *         code: string,
     *         name: string,
     *         structure: string,
     *         settings: array<string, mixed>|null,
     *         clinical_validation_status: string,
     *         parameters: list<array{
     *             code: string,
     *             name: string,
     *             result_type: string,
     *             unit: string|null,
     *             reference_text: string|null,
     *             reference_config: array<string, mixed>|null,
     *             options: list<string>|null,
     *             clinical_validation_status: string
     *         }>
     *     }>
     * }
     */
    public function handle(Exam $exam): array
    {
        $exam->load([
            'sections' => fn ($query) => $query
                ->where('is_active', true)
                ->reorder('sort_order')
                ->with(['parameters' => fn ($query) => $query->where('is_active', true)->reorder('sort_order')]),
        ]);

        return [
            'exam' => [
                'code' => $exam->code,
                'name' => $exam->name,
                'source' => $exam->source,
                'clinical_validation_status' => $exam->clinical_validation_status,
            ],
            'sections' => $exam->sections->map(fn (ExamSection $section): array => $this->section($section))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function section(ExamSection $section): array
    {
        if ($section->structure === 'parasitological_matrix' && count($section->settings['columns'] ?? []) < 2) {
            throw ValidationException::withMessages([
                'settings.columns' => "La matriz «{$section->name}» debe definir al menos dos columnas.",
            ]);
        }

        return [
            'code' => $section->code,
            'name' => $section->name,
            'structure' => $section->structure,
            'settings' => $section->settings,
            'clinical_validation_status' => $section->clinical_validation_status,
            'parameters' => $section->parameters->map(fn (ExamParameter $parameter): array => [
                'code' => $parameter->code,
                'name' => $parameter->name,
                'result_type' => $parameter->result_type,
                'unit' => $parameter->unit,
                'reference_text' => $parameter->reference_text,
                'reference_config' => $parameter->reference_config,
                'options' => $parameter->options,
                'clinical_validation_status' => $parameter->clinical_validation_status,
            ])->values()->all(),
        ];
    }
}
