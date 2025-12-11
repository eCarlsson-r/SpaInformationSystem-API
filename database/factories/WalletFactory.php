<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Wallet;
use App\Models\Bank;
use App\Models\Account;

class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word . ' Wallet',
            'bank_account_number' => $this->faker->bankAccountNumber,
            'bank_id' => Bank::factory(),
            'account_id' => Account::factory(),
            'edc_machine' => $this->faker->boolean,
        ];
    }
}
