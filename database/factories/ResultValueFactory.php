<?php

namespace Database\Factories;

use App\Models\OrderExam;
use App\Models\ResultValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultValue>
 */
class ResultValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_exam_id' => OrderExam::factory(),
            'parameter_code' => fake()->unique()->slug(2),
            'value' => ['value' => fake()->word()],
            'edited_by' => User::factory(),
        ];
    }
}
