<?php

namespace Database\Factories;

use App\Models\AlumniRecord;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlumniRecord>
 */
class AlumniRecordFactory extends Factory
{
    public function definition(): array
    {
        $graduation = fake()->numberBetween(2002, 2025);

        return [
            'roll_number' => strtoupper(fake()->unique()->bothify('REC-'.$graduation.'-###')),
            'name' => fake()->name(),
            'programme_id' => fn () => Programme::inRandomOrder()->value('id') ?? Programme::factory(),
            'admission_year' => $graduation - 4,
            'graduation_year' => $graduation,
        ];
    }
}
