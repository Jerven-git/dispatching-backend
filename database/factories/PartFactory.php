<?php

namespace Database\Factories;

use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'sku' => strtoupper(fake()->unique()->bothify('??-####')),
            'unit_price' => fake()->randomFloat(2, 5, 500),
            'stock_quantity' => fake()->numberBetween(10, 200),
            'minimum_stock' => 5,
            'unit' => fake()->randomElement(['piece', 'meter', 'liter', 'kg', 'box']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => [
            'stock_quantity' => 3,
            'minimum_stock' => 5,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock_quantity' => 0]);
    }
}
