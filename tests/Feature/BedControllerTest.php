<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BedControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_all_beds()
    {
        Bed::factory()->count(3)->create();

        $response = $this->getJson('/api/bed');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_bed()
    {
        $bed = Bed::factory()->create();

        $response = $this->getJson("/api/bed/{$bed->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $bed->id,
                'name' => $bed->name,
            ]);
    }
}
