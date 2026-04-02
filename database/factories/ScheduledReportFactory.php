<?php

namespace Database\Factories;

use App\Models\ScheduledReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledReport>
 */
class ScheduledReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true) . ' Report',
            'report_type' => fake()->randomElement(['summary', 'jobs_by_status', 'technician_performance']),
            'frequency' => fake()->randomElement(['daily', 'weekly', 'monthly']),
            'recipients' => [fake()->safeEmail()],
            'created_by' => User::factory()->state(['role' => 'admin']),
            'is_active' => true,
        ];
    }
}
