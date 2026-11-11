<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderValidationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderValidationEvent>
 */
class OrderValidationEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'version' => 1,
            'action' => OrderValidationEvent::ACTION_VALIDATED,
            'actor_id' => User::factory(),
            'reason' => null,
            'results_snapshot' => ['exams' => []],
            'acted_at' => now(),
        ];
    }
}
