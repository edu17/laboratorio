<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSection>
 */
class ExamSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'structure' => 'table',
            'settings' => null,
            'source' => null,
            'clinical_validation_status' => 'pending',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
