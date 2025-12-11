<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Bank;

class BankFactory extends Factory
{
    protected $model = Bank::class;

    public function definition()
    {
        return [
            'id' => $this->faker->unique()->numerify('###'),
            'name' => $this->faker->company,
        ];
    }
}
