<?php

namespace Database\Factories;

use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programme>
 */
class ProgrammeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PRG-###')),
            'name' => 'B.Tech '.fake()->unique()->word(),
            'degree' => 'B.Tech',
            'duration_years' => 4,
            'is_active' => true,
        ];
    }
}
