<?php

namespace Database\Factories;

use App\Models\Startup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Startup>
 */
class StartupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['PayFlow', 'KrishiAI', 'Medlytics', 'Codeyard', 'Voltpath', 'Learnly', 'Shipwise', 'Quantleaf']),
            'tagline' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
            'industry' => fake()->randomElement(['Fintech', 'Agritech', 'Healthtech', 'Developer tools', 'EV', 'Edtech', 'Logistics']),
            'website_url' => 'https://example.com',
            'location' => fake()->randomElement(['Bengaluru', 'Gurugram', 'Pune', 'Hyderabad']),
            'founded_year' => fake()->numberBetween(2015, 2025),
            'funding_stage' => fake()->randomElement(array_keys(Startup::STAGES)),
            'is_hiring' => fake()->boolean(),
        ];
    }
}
