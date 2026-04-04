<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'plan' => 'basic',
            'max_users' => 10,
            'is_active' => true,
        ];
    }

    public function pro(): static
    {
        return $this->state(fn () => ['plan' => 'pro', 'max_users' => 50]);
    }

    public function enterprise(): static
    {
        return $this->state(fn () => ['plan' => 'enterprise', 'max_users' => 200]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
