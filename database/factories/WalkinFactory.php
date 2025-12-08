<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Walkin;
use App\Models\Treatment;
use App\Models\Customer;
use App\Models\Session;

class WalkinFactory extends Factory
{
    protected $model = Walkin::class;

    public function definition()
    {
        return [
            'treatment_id' => Treatment::factory(),
            'customer_id' => Customer::factory(),
            'session_id' => Session::factory(),
        ];
    }
}
