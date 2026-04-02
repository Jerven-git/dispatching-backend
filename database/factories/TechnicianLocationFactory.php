<?php

namespace Database\Factories;

use App\Models\TechnicianLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicianLocation>
 */
class TechnicianLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'technician']),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'accuracy' => fake()->randomFloat(2, 1, 50),
            'heading' => fake()->randomFloat(2, 0, 360),
            'speed' => fake()->randomFloat(2, 0, 120),
            'recorded_at' => now(),
        ];
    }
}
