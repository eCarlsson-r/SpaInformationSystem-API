<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Customer;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'gender' => $this->faker->randomElement(['M', 'F']),
            'address' => $this->faker->address,
            'city' => $this->faker->city,
            'country' => $this->faker->country,
            'place_of_birth' => $this->faker->city,
            'date_of_birth' => $this->faker->date(),
            'mobile' => $this->faker->phoneNumber,
            'email' => $this->faker->email,
            'liability_account' => \App\Models\Account::factory(),
        ];
    }
}
