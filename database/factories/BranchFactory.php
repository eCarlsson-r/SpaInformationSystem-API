<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'country' => $this->faker->country(),
            'phone' => $this->faker->phoneNumber(),
            'description' => $this->faker->sentence(),
            'image' => $this->faker->imageUrl(),
            'cash_account' => Account::factory(),
            'walkin_account' => Account::factory(),
            'voucher_purchase_account' => Account::factory(),
            'voucher_usage_account' => Account::factory()
        ];
    }
}
