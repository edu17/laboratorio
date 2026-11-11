<?php

namespace Database\Factories;

use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('EXM-###'),
            'name' => fake()->words(2, true),
            'description' => null,
            'source' => null,
            'clinical_validation_status' => 'pending',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
