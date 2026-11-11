<?php

namespace Database\Factories;

use App\Models\ReferringDoctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferringDoctor>
 */
class ReferringDoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Dr. Ficticio '.fake()->unique()->numerify('####'),
            'kind' => ReferringDoctor::KIND_DOCTOR,
            'specialty' => null,
            'license_number' => null,
            'phone' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function institution(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Farmacia Ficticia '.fake()->unique()->numerify('####'),
            'kind' => ReferringDoctor::KIND_INSTITUTION,
        ]);
    }
}
