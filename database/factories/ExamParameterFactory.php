<?php

namespace Database\Factories;

use App\Models\ExamParameter;
use App\Models\ExamSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamParameter>
 */
class ExamParameterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_section_id' => ExamSection::factory(),
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'result_type' => 'text',
            'unit' => null,
            'reference_text' => null,
            'reference_config' => null,
            'options' => null,
            'source' => null,
            'clinical_validation_status' => 'pending',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
