<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CualitativosCatalogSeeder extends Seeder
{
    /**
     * Carga los exámenes cualitativos definidos en config/catalogo_cualitativos.php sin modificar los ya existentes.
     */
    public function run(): void
    {
        $definition = config('catalogo_cualitativos');

        DB::transaction(function () use ($definition): void {
            foreach ($definition['exams'] as $examIndex => $examDefinition) {
                $source = "{$definition['source']} · hoja {$examDefinition['sheet']}";

                $exam = Exam::firstOrCreate(
                    ['code' => $examDefinition['code']],
                    [
                        'name' => $examDefinition['name'],
                        'source' => $source,
                        'clinical_validation_status' => 'validated',
                        'is_active' => true,
                        'allows_partial_request' => $examDefinition['allows_partial_request'],
                        'sort_order' => 100 + ($examIndex * 10),
                    ],
                );

                foreach ($examDefinition['sections'] as $sectionIndex => $sectionDefinition) {
                    $section = ExamSection::firstOrCreate(
                        ['exam_id' => $exam->id, 'code' => $sectionDefinition['code']],
                        [
                            'name' => $sectionDefinition['name'],
                            'structure' => 'table',
                            'source' => $source,
                            'clinical_validation_status' => 'validated',
                            'is_active' => true,
                            'sort_order' => ($sectionIndex + 1) * 10,
                        ],
                    );

                    foreach ($sectionDefinition['parameters'] as $parameterIndex => $parameterDefinition) {
                        ExamParameter::firstOrCreate(
                            ['exam_section_id' => $section->id, 'code' => $parameterDefinition['code']],
                            [
                                'name' => $parameterDefinition['name'],
                                'result_type' => $parameterDefinition['type'],
                                'unit' => $parameterDefinition['unit'] ?? null,
                                'reference_text' => $parameterDefinition['reference'] ?? null,
                                'options' => $parameterDefinition['options'] ?? null,
                                'source' => $source,
                                'clinical_validation_status' => 'validated',
                                'is_active' => true,
                                'sort_order' => ($parameterIndex + 1) * 10,
                            ],
                        );
                    }
                }
            }
        });
    }
}
