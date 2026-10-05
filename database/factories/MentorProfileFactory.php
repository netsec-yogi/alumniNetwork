<?php

namespace Database\Factories;

use App\Models\MentorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentorProfile>
 */
class MentorProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'is_accepting' => true,
            'categories' => fake()->randomElements(array_keys(config('mentoring.categories')), 2),
            'expertise' => fake()->randomElements(['ML', 'System design', 'GATE', 'Product', 'Startups', 'Finance', 'Research', 'Higher studies abroad'], 3),
            'bio' => fake()->sentence(16),
            'preferred_mentee' => 'both',
            'max_mentees' => 3,
            'availability' => 'Two evenings a month',
            'preferred_mode' => 'video',
        ];
    }
}
