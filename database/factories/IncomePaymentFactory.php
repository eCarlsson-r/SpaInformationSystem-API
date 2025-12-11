<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\IncomePayment;
use App\Models\Income;
use App\Models\Wallet;

class IncomePaymentFactory extends Factory
{
    protected $model = IncomePayment::class;

    public function definition()
    {
        return [
            'income_id' => Income::factory(),
            'type' => $this->faker->randomElement(['cash', 'transfer', 'clearing']),
            'wallet_id' => Wallet::factory(),
            'amount' => $this->faker->numberBetween(10000, 500000),
            'description' => $this->faker->sentence,
        ];
    }
}
