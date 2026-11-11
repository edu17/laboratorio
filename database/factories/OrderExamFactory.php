<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Order;
use App\Models\OrderExam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderExam>
 */
class OrderExamFactory extends Factory
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
            'exam_id' => Exam::factory(),
            'exam_catalog_version_id' => null,
            'catalog_snapshot' => ['exam' => ['code' => 'FICTICIO', 'name' => 'Examen ficticio'], 'sections' => []],
            'status' => OrderExam::STATUS_PENDING,
        ];
    }
}
