<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Session;
use App\Models\Treatment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Bed;
use App\Models\Branch;
use App\Models\Room;

class SessionFactory extends Factory
{
    protected $model = Session::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Session $session): void {
            $branchId = $session->bed?->room?->branch_id;
            if ($branchId && $session->employee && (int) $session->employee->branch_id !== (int) $branchId) {
                $session->employee->update(['branch_id' => $branchId]);
            }
        });
    }

    public function definition()
    {
        $branch = Branch::factory();
        $room = Room::factory()->state(['branch_id' => $branch]);

        return [
            'treatment_id' => Treatment::factory(),
            'customer_id' => Customer::factory(),
            'employee_id' => Employee::factory()->state(['branch_id' => $branch]),
            'bed_id' => Bed::factory()->state(['room_id' => $room]),
            'order_time' => $this->faker->time(),
            'reserved_time' => $this->faker->dateTime(),
            'payment' => 'walk-in',
            'date' => $this->faker->date(),
            'start' => $this->faker->time(),
            'end' => $this->faker->time(),
            'status' => 'waiting',
        ];
    }
}
