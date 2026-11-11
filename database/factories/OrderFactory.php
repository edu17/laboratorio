<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->bothify('LAB-####-######'),
            'patient_id' => Patient::factory(),
            'ordered_at' => now(),
            'status' => Order::STATUS_PENDING,
            'created_by' => User::factory(),
            'notes' => null,
            'cancellation_reason' => null,
            'canceled_at' => null,
            'canceled_by' => null,
        ];
    }
}
