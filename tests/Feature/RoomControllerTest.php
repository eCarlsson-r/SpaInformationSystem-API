<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Room;
use App\Models\Session;
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

    public function test_booking_room_list_filters_branch_and_overlapping_sessions(): void
    {
        $branch = Branch::factory()->create();
        $room = Room::factory()->create(['branch_id' => $branch->id]);
        $busyBed = Bed::factory()->create(['room_id' => $room->id]);
        $freeBed = Bed::factory()->create(['room_id' => $room->id]);
        $otherBranchRoom = Room::factory()->create();
        Bed::factory()->create(['room_id' => $otherBranchRoom->id]);
        $employee = Employee::factory()->create(['branch_id' => $branch->id]);
        $date = now()->addDay()->toDateString();

        Session::factory()->create([
            'bed_id' => $busyBed->id,
            'employee_id' => $employee->id,
            'date' => $date,
            'start' => '10:00:00',
            'end' => '11:00:00',
            'status' => 'waiting',
        ]);

        $this->getJson('/api/room?show=empty&branch_id=' . $branch->id . '&date=' . $date . '&time=10:30&duration=60')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $room->id)
            ->assertJsonCount(1, '0.bed')
            ->assertJsonPath('0.bed.0.id', $freeBed->id);
    }

    public function test_booking_room_list_excludes_beds_with_active_payment_holds(): void
    {
        $branch = Branch::factory()->create();
        $room = Room::factory()->create(['branch_id' => $branch->id]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $employee = Employee::factory()->create(['branch_id' => $branch->id]);
        $customer = \App\Models\Customer::factory()->create();
        $date = now()->addDay()->toDateString();

        BookingPayment::create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'treatment_id' => \App\Models\Treatment::factory()->create()->id,
            'employee_id' => $employee->id,
            'room_id' => $room->id,
            'bed_id' => $bed->id,
            'session_date' => $date,
            'session_start' => '10:00:00',
            'session_end' => '11:00:00',
            'amount' => 100000,
            'reference_id' => 'SPA-' . \Illuminate\Support\Str::uuid(),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(20),
        ]);

        $this->getJson('/api/room/available?branch_id=' . $branch->id . '&date=' . $date . '&time=10:30&duration=30')
            ->assertOk()
            ->assertExactJson([]);
    }
}
