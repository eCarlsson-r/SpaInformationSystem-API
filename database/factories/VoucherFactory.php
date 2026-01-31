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
        $treatment = Treatment::factory()->create();
        return [
            'id' => $treatment->id.sprintf('%06d', $this->faker->numberBetween(1, 999999)),
            'customer_id' => Customer::factory(),
            'treatment_id' => $treatment->id,
            'session_id' => Session::factory(),
            'register_date' => $this->faker->date(),
            'register_time' => $this->faker->time(),
            'amount' => $this->faker->numberBetween(100000, 500000),
            'purchase_date' => $this->faker->date(),
        ];
    }
}
