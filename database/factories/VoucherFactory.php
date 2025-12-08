<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Voucher;
use App\Models\Customer;
use App\Models\Treatment;
use App\Models\Session;

class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition()
    {
        return [
            'id' => $this->faker->uuid,
            'customer_id' => Customer::factory(),
            'treatment_id' => Treatment::factory(),
            'session_id' => Session::factory(),
            'register_date' => $this->faker->date(),
            'register_time' => $this->faker->time(),
            'amount' => $this->faker->numberBetween(100000, 500000),
            'purchase_date' => $this->faker->date(),
        ];
    }
}
