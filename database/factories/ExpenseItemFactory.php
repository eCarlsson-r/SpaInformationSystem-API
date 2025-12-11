<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ExpenseItem;
use App\Models\Expense;
use App\Models\Account;

class ExpenseItemFactory extends Factory
{
    protected $model = ExpenseItem::class;

    public function definition()
    {
        return [
            'expense_id' => Expense::factory(),
            'account_id' => Account::factory(),
            'amount' => $this->faker->numberBetween(10000, 500000),
            'description' => $this->faker->sentence,
        ];
    }
}
