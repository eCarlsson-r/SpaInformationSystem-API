<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Branch;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RoomControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function test_index_returns_all_rooms_with_relationships()
    {
        $branch = Branch::factory()->create();
        $room = Room::factory()->create(['branch_id' => $branch->id]);
        Bed::factory()->create(['room_id' => $room->id]);

        $response = $this->getJson('/api/room');

        $response->assertStatus(200)
            ->assertJsonCount(1);
        
        $data = $response->json();
        $this->assertArrayHasKey('branch', $data[0]);
        $this->assertArrayHasKey('bed', $data[0]);
    }

    public function test_show_returns_room()
    {
        $room = Room::factory()->create();

        $response = $this->getJson("/api/room/{$room->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $room->id,
                'name' => $room->name,
            ]);
    }

    public function test_destroy_deletes_room()
    {
        $room = Room::factory()->create();

        $response = $this->deleteJson("/api/room/{$room->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Room deleted successfully']);

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }
}
