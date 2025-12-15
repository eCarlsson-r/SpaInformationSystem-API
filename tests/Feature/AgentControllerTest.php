<?php

namespace Tests\Feature;

use App\Models\Agent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AgentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function test_index_returns_all_agents()
    {
        Agent::factory()->count(3)->create();

        $response = $this->getJson('/api/agent');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_agent()
    {
        $agent = Agent::factory()->create();

        $response = $this->getJson("/api/agent/{$agent->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $agent->id,
                'name' => $agent->name,
            ]);
    }

    public function test_destroy_deletes_agent()
    {
        $agent = Agent::factory()->create();

        $response = $this->deleteJson("/api/agent/{$agent->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Agent deleted successfully']);

        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
    }
}
