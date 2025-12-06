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

    public function test_index_returns_occupancy_stats()
    {
        $branch = Branch::factory()->create();
        $room = Room::factory()->create(['branch_id' => $branch->id]);
        
        // Bed 1: Occupied (ongoing session)
        $bed1 = Bed::factory()->create(['room_id' => $room->id]);
        Session::factory()->create([
            'bed_id' => $bed1->id,
            'branch_id' => $branch->id,
            'status' => 'ongoing',
            'date' => now()->toDateString(),
        ]);

        // Bed 2: Empty (completed session)
        $bed2 = Bed::factory()->create(['room_id' => $room->id]);
        Session::factory()->create([
            'bed_id' => $bed2->id,
            'branch_id' => $branch->id,
            'status' => 'completed',
            'date' => now()->toDateString(),
        ]);

        // Bed 3: Empty (no session)
        Bed::factory()->create(['room_id' => $room->id]);

        $response = $this->getJson('/api/room');

        $response->assertStatus(200);
        
        $data = $response->json();
        $roomData = $data[0];

        $this->assertEquals(1, $roomData['occupied_beds'], 'Occupied beds count should be 1');
        $this->assertEquals(2, $roomData['empty_beds'], 'Empty beds count should be 2');
    }
}
