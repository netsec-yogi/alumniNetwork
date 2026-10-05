<?php

namespace Database\Factories;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'job',
            'title' => fake()->randomElement(['Software Engineer II', 'Data Scientist', 'Product Manager', 'Backend Engineer', 'ML Engineer', 'Business Analyst', 'SDE Intern']),
            'organization' => fake()->randomElement(['Google', 'Razorpay', 'Atlassian', 'Flipkart', 'Zomato', 'Adobe', 'Microsoft']),
            'location' => fake()->randomElement(['Bengaluru', 'Hyderabad', 'Gurugram', 'Pune', 'Remote']),
            'work_mode' => fake()->randomElement(array_keys(JobPosting::WORK_MODES)),
            'employment_type' => 'full_time',
            'experience_min' => 1,
            'experience_max' => 4,
            'skills' => fake()->randomElements(['Go', 'Python', 'Kubernetes', 'React', 'SQL', 'ML', 'Java', 'System design'], 3),
            'description' => fake()->paragraphs(3, true),
            'apply_url' => 'https://careers.example.com/'.fake()->slug(2),
            'deadline' => now()->addDays(30)->toDateString(),
            'referral_available' => true,
            'status' => JobPosting::APPROVED,
            'posted_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => JobPosting::PENDING]);
    }
}
