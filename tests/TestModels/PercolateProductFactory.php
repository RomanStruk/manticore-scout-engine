<?php

namespace RomanStruk\ManticoreScoutEngine\Tests\TestModels;

use Illuminate\Database\Eloquent\Factories\Factory;

class PercolateProductFactory extends Factory
{
    protected $model = PercolateProduct::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->word(),
            'color' => 'red',
        ];
    }
}
