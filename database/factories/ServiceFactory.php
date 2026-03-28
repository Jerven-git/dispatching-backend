<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'base_price' => fake()->randomFloat(2, 50, 500),
            'estimated_duration_minutes' => fake()->numberBetween(30, 240),
            'is_active' => true,
        ];
    }
}
