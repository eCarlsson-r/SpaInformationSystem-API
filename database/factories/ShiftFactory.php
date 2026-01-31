<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Shift;

class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition()
    {
        return [
            'id' => $this->faker->randomElement(['M', 'A', 'N', 'D']),
            'name' => $this->faker->randomElement(['Morning', 'Afternoon', 'Night', 'Whole Day']),
            'start_time' => $this->faker->time(),
            'end_time' => $this->faker->time(),
        ];
    }
}
