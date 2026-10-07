<?php

namespace Database\Factories;

use App\Models\SpeakerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpeakerProfile>
 */
class SpeakerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'is_available' => true,
            'topics' => fake()->randomElements(['AI', 'Product management', 'Entrepreneurship', 'Career growth', 'Cloud', 'Finance', 'Research careers'], 2),
            'formats' => ['talk', 'webinar'],
            'bio' => fake()->sentence(14),
            'languages' => 'English, Hindi',
            'remote' => true,
            'in_person' => fake()->boolean(),
        ];
    }
}
