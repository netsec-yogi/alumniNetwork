<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(3, 60))->setTime(fake()->numberBetween(9, 18), 0);

        return [
            'title' => fake()->randomElement(['Bengaluru Chapter Meetup', 'Batch of 2015 Reunion', 'AI in Industry: Alumni Talk', 'Founders Fireside', 'Career Night', 'Annual Alumni Meet']).' '.fake()->year(),
            'type' => fake()->randomElement(EventType::cases()),
            'summary' => fake()->sentence(12),
            'description' => fake()->paragraphs(3, true),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(3),
            'venue' => fake()->randomElement(['Main Auditorium, ABV-IIITM Gwalior', 'Hotel Taj, Bengaluru', 'WeWork Galaxy, Bengaluru', 'India Habitat Centre, New Delhi']),
            'is_online' => false,
            'capacity' => 100,
            'max_guests' => 1,
            'audience' => Event::AUDIENCE_MEMBERS,
            'status' => Event::PUBLISHED,
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => Event::DRAFT]);
    }

    public function public(): static
    {
        return $this->state(['audience' => Event::AUDIENCE_PUBLIC]);
    }
}
