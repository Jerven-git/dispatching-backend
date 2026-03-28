<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceJob>
 */
class ServiceJobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'service_id' => Service::factory(),
            'created_by' => User::factory()->state(['role' => 'admin']),
            'status' => 'pending',
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'address' => fake()->streetAddress(),
            'scheduled_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'scheduled_time' => fake()->time('H:i'),
            'total_cost' => fake()->randomFloat(2, 100, 1000),
        ];
    }

    public function assigned(User $technician = null): static
    {
        return $this->state(fn () => [
            'status' => 'assigned',
            'technician_id' => $technician ?? User::factory()->state(['role' => 'technician']),
        ]);
    }

    public function inProgress(User $technician = null): static
    {
        return $this->state(fn () => [
            'status' => 'in_progress',
            'technician_id' => $technician ?? User::factory()->state(['role' => 'technician']),
            'started_at' => now(),
        ]);
    }

    public function completed(User $technician = null): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'technician_id' => $technician ?? User::factory()->state(['role' => 'technician']),
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
        ]);
    }
}
