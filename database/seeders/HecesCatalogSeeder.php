<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HecesCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $definition = config('heces');

        DB::transaction(function () use ($definition): void {
            $exam = Exam::firstOrCreate(
                ['code' => 'HECES'],
                [
                    'name' => $definition['exam_name'],
                    'source' => $definition['source'],
                    'clinical_validation_status' => 'pending',
                    'is_active' => true,
                    'sort_order' => 10,
                ],
            );

            foreach ($definition['sections'] as $index => $sectionDefinition) {
                $section = ExamSection::firstOrCreate(
                    ['exam_id' => $exam->id, 'code' => $sectionDefinition['code']],
                    [
                        'name' => $sectionDefinition['name'],
                        'structure' => 'table',
                        'source' => $definition['source'],
                        'clinical_validation_status' => 'pending',
                        'is_active' => true,
                        'sort_order' => ($index + 1) * 10,
                    ],
                );

                foreach ($sectionDefinition['parameters'] as $parameterDefinition) {
                    ExamParameter::firstOrCreate(
                        ['exam_section_id' => $section->id, 'code' => $parameterDefinition['id']],
                        [
                            'name' => $parameterDefinition['name'],
                            'result_type' => $parameterDefinition['type'],
                            'unit' => $parameterDefinition['unit'],
                            'reference_text' => $parameterDefinition['reference'],
                            'source' => $definition['source'],
                            'clinical_validation_status' => 'pending',
                            'is_active' => true,
                            'sort_order' => $parameterDefinition['order'],
                        ],
                    );
                }
            }

            $matrix = $definition['parasitological_matrix'];
            $matrixSection = ExamSection::firstOrCreate(
                ['exam_id' => $exam->id, 'code' => $matrix['code']],
                [
                    'name' => $matrix['name'],
                    'structure' => 'parasitological_matrix',
                    'settings' => ['columns' => $matrix['columns']],
                    'source' => $definition['source'],
                    'clinical_validation_status' => 'pending',
                    'is_active' => true,
                    'sort_order' => 30,
                ],
            );

            foreach ($matrix['organisms'] as $index => $organism) {
                ExamParameter::firstOrCreate(
                    ['exam_section_id' => $matrixSection->id, 'code' => Str::slug($organism, '_')],
                    [
                        'name' => $organism,
                        'result_type' => 'matrix_row',
                        'source' => $definition['source'],
                        'clinical_validation_status' => 'pending',
                        'is_active' => true,
                        'sort_order' => ($index + 1) * 10,
                    ],
                );
            }
        });
    }
}
