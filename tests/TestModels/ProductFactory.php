<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\TestModels;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(5),
            'description' => $this->faker->paragraph(2),
            'brand_name' => $this->faker->word(),
            'price' => $this->faker->randomFloat(0, 100, 999),
            'category_id' => rand(1, 20),
        ];
    }
}
