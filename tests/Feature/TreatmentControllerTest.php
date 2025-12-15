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

        $response = $this->getJson(route('treatment.index'));

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_specific_treatment()
    {
        $treatment = Treatment::factory()->create();

        $response = $this->getJson(route('treatment.show', $treatment->id));

        $response->assertStatus(200)
            ->assertJson([
                'id' => $treatment->id,
                'name' => $treatment->name,
            ]);
    }

    public function test_update_modifies_treatment()
    {
        $treatment = Treatment::factory()->create();
        $newData = [
            'name' => 'Updated Name',
            'price' => 150000,
        ];

        // Ensure category is present or handled if required by validation/logic
        // In the controller, it uses $request->all() directly.
        // If there are other required fields that are not in $newData, this might fail if it were a strict update.
        // But since it's updateOrCreate with existing ID, it should update.
        // Wait, updateOrCreate overwrites? No, it respects the matching attributes and updates/creates the rest.
        // But here the signature is update(Request $request, $id).
        // And it calls Treatment::updateOrCreate(['id' => $id], $request->all()).
        // If $request->all() doesn't contain the other fields, they might remain IF it was just update,
        // BUT updateOrCreate might try to set what's in the second array.
        // If the model exists, it updates it with the values in the second array.
        // So this should work.

        $response = $this->putJson(route('treatment.update', $treatment->id), $newData);

        $response->assertStatus(200)
             ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('treatments', [
            'id' => $treatment->id,
            'name' => 'Updated Name',
        ]);
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
