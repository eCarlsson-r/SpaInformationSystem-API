<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BranchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function test_index_returns_all_branches()
    {
        Branch::factory()->count(3)->create();

        $response = $this->getJson('/api/branch');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_branch()
    {
        $branch = Branch::factory()->create();

        $response = $this->getJson("/api/branch/{$branch->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $branch->id,
                'name' => $branch->name,
            ]);
    }

    public function test_destroy_deletes_branch()
    {
        $branch = Branch::factory()->create();

        $response = $this->deleteJson("/api/branch/{$branch->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Branch deleted successfully']);

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }
}
