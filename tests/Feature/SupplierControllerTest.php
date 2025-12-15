<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function test_index_returns_all_suppliers()
    {
        Supplier::factory()->count(3)->create();

        $response = $this->getJson('/api/supplier');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_supplier()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->getJson("/api/supplier/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $supplier->id,
                'name' => $supplier->name,
            ]);
    }

    public function test_destroy_deletes_supplier()
    {
        $supplier = Supplier::factory()->create();

        $response = $this->deleteJson("/api/supplier/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Supplier deleted successfully']);

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }
}
