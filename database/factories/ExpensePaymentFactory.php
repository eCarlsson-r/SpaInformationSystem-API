<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ExpensePayment;
use App\Models\Expense;
use App\Models\Wallet;

class ExpensePaymentFactory extends Factory
{
    protected $model = ExpensePayment::class;

    public function definition()
    {
        return [
            'expense_id' => Expense::factory(),
            'type' => $this->faker->randomElement(['cash', 'transfer', 'clearing']),
            'wallet_id' => Wallet::factory(),
            'amount' => $this->faker->numberBetween(10000, 500000),
            'description' => $this->faker->sentence,
        ];
    }
}
