<?php

namespace Tests\Feature;

use App\Models\Treatment;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TreatmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function test_index_returns_treatments()
    {
        $treatments = Treatment::factory()->count(3)->create();

        $response = $this->getJson('/api/treatment');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_specific_treatment()
    {
        $treatment = \App\Models\Treatment::factory()->create();

        $response = $this->getJson("/api/treatment/{$treatment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $treatment->id,
                'name' => $treatment->name,
            ]);
    }

    public function test_store_creates_treatment()
    {
        $treatmentData = \App\Models\Treatment::factory()->make()->toArray();

        $response = $this->postJson('/api/treatment', $treatmentData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('treatments', ['name' => $treatmentData['name']]);
    }

    public function test_update_modifies_treatment()
    {
        $treatment = Treatment::factory()->create();
        $newName = 'Updated Treatment Name';

        $response = $this->putJson("/api/treatment/{$treatment->id}", ['name' => $newName]);

        $response->assertStatus(200);
        $this->assertEquals($newName, $treatment->fresh()->name);
    }

    public function test_destroy_deletes_treatment()
    {
        $treatment = Treatment::factory()->create();

        $response = $this->deleteJson(route('treatment.destroy', $treatment->id));

        $response->assertStatus(200)
             ->assertJson(['message' => 'Treatment deleted successfully']);

        $this->assertDatabaseMissing('treatments', ['id' => $treatment->id]);
    }
}
