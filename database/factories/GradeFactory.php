<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Grade;
use App\Models\Employee;

class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition()
    {
        return [
            'employee_id' => Employee::factory(),
            'grade' => $this->faker->randomElement(['A', 'B', 'C', 'D', 'E']),
            'start_date' => $this->faker->date(),
            'end_date' => null,
        ];
    }
}
