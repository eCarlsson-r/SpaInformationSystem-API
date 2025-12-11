<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Income;

class IncomeFactory extends Factory
{
    protected $model = Income::class;

    public function definition()
    {
        return [
            'journal_reference' => 'EXO.BKM.'.date('y').sprintf('%05d', $this->faker->unique()->numberBetween(1, 10000)),
            'date' => $this->faker->date(),
            'description' => $this->faker->sentence,
            'partner_type' => 'customer',
            'partner' => $this->faker->name,
        ];
    }
}
