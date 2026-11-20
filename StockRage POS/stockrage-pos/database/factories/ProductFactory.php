<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'category' => 'Takoyaki',
            'price' => fake()->randomElement([50, 60, 70, 85]),
            'cost_price' => 20,
            'stock' => 100,
            'low_stock_threshold' => 20,
            'is_active' => true,
        ];
    }
}
