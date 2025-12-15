<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Branch;
use App\Models\Room;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomOccupancyTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function test_index_returns_occupancy_stats()
    {
        $room = Room::factory()->create();
        $customer = \App\Models\Customer::factory()->create();
        $treatment = \App\Models\Treatment::factory()->create();
        $employee = \App\Models\Employee::factory()->create();
        
        // Bed 1: Occupied (ongoing session)
        $bed1 = Bed::factory()->create(['room_id' => $room->id]);

        Session::factory()->create([
            'bed_id' => $bed1->id,
            'customer_id' => $customer->id,
            'treatment_id' => $treatment->id,
            'employee_id' => $employee->id,
            'status' => 'ongoing',
            'date' => now()->toDateString(),
        ]);

        // Bed 2: Empty (completed session)
        $bed2 = Bed::factory()->create(['room_id' => $room->id]);
        Session::factory()->create([
            'bed_id' => $bed2->id,
            'customer_id' => $customer->id,
            'treatment_id' => $treatment->id,
            'employee_id' => $employee->id,
            'status' => 'completed',
            'date' => now()->toDateString(),
        ]);

        // Bed 3: Empty (no session)
        Bed::factory()->create(['room_id' => $room->id]);

        $response = $this->getJson('/api/room');

        $response->assertStatus(200);
        
        $data = $response->json();
        $roomData = $data[0];

        $this->assertEquals(1, $roomData['occupied'], 'Occupied beds count should be 1');
        $this->assertEquals(2, $roomData['empty'], 'Empty beds count should be 2');
    }
}
