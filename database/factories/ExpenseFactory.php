<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Expense;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition()
    {
        return [
            'journal_reference' => 'EXO.BKK.'.date('y').sprintf('%05d', $this->faker->unique()->numberBetween(1, 10000)),
            'date' => $this->faker->date(),
            'description' => $this->faker->sentence,
            'partner_type' => 'supplier',
            'partner' => $this->faker->company,
        ];
    }
}
