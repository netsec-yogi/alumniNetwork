<?php

namespace Database\Factories;

use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kind' => Community::KIND_COMMUNITY,
            'category' => 'interest',
            'name' => fake()->randomElement(['AI & ML', 'Product Management', 'Founders', 'Higher Studies Abroad', 'Quant Finance', 'Open Source']).' '.fake()->word(),
            'description' => fake()->sentence(),
            'join_policy' => Community::OPEN,
            'is_official' => true,
        ];
    }

    public function chapter(string $city = 'Bengaluru'): static
    {
        return $this->state(['kind' => Community::KIND_CHAPTER, 'category' => 'city', 'name' => "{$city} Chapter"]);
    }

    public function approval(): static
    {
        return $this->state(['join_policy' => Community::APPROVAL]);
    }
}
