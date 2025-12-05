<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bed>
 */
class BedFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roomId = Room::factory()->create()->id;
        return [
            'id' => $this->faker->numerify(explode("-", $roomId)[1].'##'),
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'room_id' => $roomId,
        ];
    }
}
