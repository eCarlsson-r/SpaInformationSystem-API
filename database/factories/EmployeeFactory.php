<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Employee;
use App\Models\Branch;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition()
    {
        return [
            'complete_name' => $this->faker->name,
            'name' => $this->faker->firstName,
            'status' => 'active',
            'identity_type' => 'KTP',
            'identity_number' => $this->faker->nik,
            'place_of_birth' => $this->faker->city,
            'date_of_birth' => $this->faker->date(),
            'certified' => 1,
            'recruiter' => null,
            'branch_id' => Branch::factory(),
            'base_salary' => 5000000,
            'expertise' => 'therapist',
            'gender' => $this->faker->randomElement(['M', 'F']),
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'mobile' => $this->faker->phoneNumber,
            'email' => $this->faker->email,
        ];
    }
}
