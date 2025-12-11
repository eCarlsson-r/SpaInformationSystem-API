<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\IncomeItem;
use App\Models\Income;

class IncomeItemFactory extends Factory
{
    protected $model = IncomeItem::class;

    public function definition()
    {
        return [
            'income_id' => Income::factory(),
            'type' => $this->faker->randomElement(['umum', 'titipan']),
            'transaction' => $this->faker->word,
            'amount' => $this->faker->numberBetween(10000, 500000),
            'description' => $this->faker->sentence,
        ];
    }
}
