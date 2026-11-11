<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_names' => 'Paciente ficticio '.fake()->unique()->numerify('####'),
            'last_names' => 'Prueba',
            'birth_date' => null,
            'sex' => null,
            'phone' => null,
            'identifier' => null,
        ];
    }
}
