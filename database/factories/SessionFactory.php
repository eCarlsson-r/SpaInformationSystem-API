<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Session;
use App\Models\Treatment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Bed;

class SessionFactory extends Factory
{
    protected $model = Session::class;

    public function definition()
    {
        return [
            'treatment_id' => Treatment::factory(),
            'customer_id' => Customer::factory(),
            'employee_id' => Employee::factory(),
            'bed_id' => Bed::factory(),
            'order_time' => $this->faker->time(),
            'reserved_time' => $this->faker->time(),
            'payment' => 'walk-in',
            'date' => $this->faker->date(),
            'start' => $this->faker->time(),
            'end' => $this->faker->time(),
            'status' => 'waiting',
        ];
    }
}
