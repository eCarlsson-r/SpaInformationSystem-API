<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function test_index_returns_all_accounts()
    {
        Account::factory()->count(3)->create();

        $response = $this->getJson('/api/account');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_account()
    {
        $account = Account::factory()->create();

        $response = $this->getJson("/api/account/{$account->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $account->id,
                'name' => $account->name,
            ]);
    }
}
