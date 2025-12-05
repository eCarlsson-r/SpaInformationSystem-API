<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Journal>
 */
class JournalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => "EXO.".$this->faker->randomElement(['BKK', 'BKM', 'BPB']).".".$this->faker->numberBetween(1, 999999),
            'date' => $this->faker->date(),
            'description' => $this->faker->sentence(),
        ];
    }
}
