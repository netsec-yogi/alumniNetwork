<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\AlumniProfile;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlumniProfile>
 */
class AlumniProfileFactory extends Factory
{
    private const COMPANIES = ['Google', 'Microsoft', 'Amazon', 'Infosys', 'TCS', 'Adobe', 'Goldman Sachs', 'Flipkart', 'Samsung R&D', 'ISRO', 'Deloitte', 'Zomato', 'Razorpay', 'Atlassian'];

    private const CITIES = [['Bengaluru', 'Karnataka', 'India'], ['Hyderabad', 'Telangana', 'India'], ['Pune', 'Maharashtra', 'India'], ['Gurugram', 'Haryana', 'India'], ['Gwalior', 'Madhya Pradesh', 'India'], ['Seattle', 'Washington', 'United States'], ['London', 'England', 'United Kingdom'], ['Singapore', null, 'Singapore']];

    public function definition(): array
    {
        $graduation = fake()->numberBetween(2002, 2025);
        [$city, $state, $country] = fake()->randomElement(self::CITIES);

        return [
            'user_id' => User::factory()->role(RoleName::Alumni),
            'roll_number' => strtoupper(fake()->unique()->bothify('IPG-'.($graduation - 5).'-###')),
            'programme_id' => fn () => Programme::inRandomOrder()->value('id') ?? Programme::factory(),
            'admission_year' => $graduation - 5,
            'graduation_year' => $graduation,
            'company' => fake()->randomElement(self::COMPANIES),
            'designation' => fake()->randomElement(['Software Engineer', 'Senior Engineer', 'Product Manager', 'Data Scientist', 'Founder', 'Research Scientist', 'Engineering Manager', 'Consultant']),
            'industry' => fake()->randomElement(['Technology', 'Finance', 'Consulting', 'Research', 'E-commerce', 'Government']),
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'bio' => fake()->sentence(18),
            'interests' => fake()->randomElements(array_keys(AlumniProfile::INTERESTS), fake()->numberBetween(0, 3)),
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['verification_status' => VerificationStatus::Pending, 'verified_at' => null]);
    }
}
