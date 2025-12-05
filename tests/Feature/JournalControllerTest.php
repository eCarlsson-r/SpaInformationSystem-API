<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class JournalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_all_journals()
    {
        Journal::factory()->count(3)->create();

        $response = $this->getJson('/api/journal');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_journal_with_records()
    {
        $journal = Journal::factory()->create();
        $account = Account::factory()->create();
        JournalRecord::factory()->create([
            'journal_id' => $journal->id,
            'account_id' => $account->id
        ]);

        $response = $this->getJson("/api/journal/{$journal->id}");

        $response->assertStatus(200)
            ->assertJson([
                0 => [
                    'id' => $journal->id,
                    'reference' => $journal->reference,
                ]
            ]);
        
        $data = $response->json();
        $this->assertNotEmpty($data[0]['records']);
    }

    public function test_destroy_deletes_journal()
    {
        $journal = Journal::factory()->create();

        $response = $this->deleteJson("/api/journal/{$journal->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Journal deleted successfully']);

        $this->assertDatabaseMissing('journals', ['id' => $journal->id]);
    }
}
