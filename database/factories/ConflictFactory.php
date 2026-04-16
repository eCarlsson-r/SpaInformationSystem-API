<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConflictFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id'             => Session::factory(),
            'conflicting_booking_id' => Session::factory(),
            'conflict_type'          => $this->faker->randomElement(['therapist', 'room']),
            'detection_timestamp'    => now(),
            'resolution_status'      => 'pending',
            'resolution_action'      => null,
            'resolution_timestamp'   => null,
            'alternative_slots'      => [],
            'branch_id'              => Branch::factory(),
        ];
    }
}
