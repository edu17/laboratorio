<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga exámenes desde un archivo de config/ con la forma de config/catalogo_cualitativos.php
 * sin modificar exámenes, secciones ni parámetros que ya existen.
 */
abstract class CatalogDefinitionSeeder extends Seeder
{
    abstract protected function configKey(): string;

    public function run(): void
    {
        $definition = config($this->configKey());

        DB::transaction(function () use ($definition): void {
            foreach ($definition['exams'] as $examDefinition) {
                $source = "{$definition['source']} · hoja {$examDefinition['sheet']}";

                $exam = Exam::firstOrCreate(
                    ['code' => $examDefinition['code']],
                    [
                        'name' => $examDefinition['name'],
                        'source' => $source,
                        'clinical_validation_status' => 'validated',
                        'is_active' => true,
                        'allows_partial_request' => $examDefinition['allows_partial_request'],
                        'sort_order' => $examDefinition['sort_order'],
                    ],
                );

                foreach ($examDefinition['sections'] as $sectionIndex => $sectionDefinition) {
                    $section = ExamSection::firstOrCreate(
                        ['exam_id' => $exam->id, 'code' => $sectionDefinition['code']],
                        [
                            'name' => $sectionDefinition['name'],
                            'structure' => 'table',
                            'settings' => $sectionDefinition['settings'] ?? null,
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
                                'reference_config' => $parameterDefinition['reference_config'] ?? null,
                                'options' => $parameterDefinition['options'] ?? null,
                                'source' => $source,
                                'clinical_validation_status' => $parameterDefinition['clinical_validation_status'] ?? 'validated',
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
