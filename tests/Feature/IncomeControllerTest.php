<?php

namespace Tests\Feature;

use App\Models\Income;
use App\Models\IncomeItem;
use App\Models\IncomePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_all_incomes()
    {
        Income::factory()->count(3)->create();

        $response = $this->getJson('/api/income');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_income()
    {
        $income = Income::factory()->create();

        $response = $this->getJson("/api/income/{$income->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $income->id,
                'description' => $income->description,
            ]);
    }

    public function test_income_has_items()
    {
        $income = Income::factory()->create();
        $items = IncomeItem::factory()->count(2)->create([
            'income_id' => $income->id,
        ]);

        $this->assertCount(2, $income->items);
        $this->assertEquals($items[0]->id, $income->items[0]->id);
    }

    public function test_income_has_payments()
    {
        $income = Income::factory()->create();
        $payments = IncomePayment::factory()->count(2)->create([
            'income_id' => $income->id,
        ]);

        $this->assertCount(2, $income->payments);
        $this->assertEquals($payments[0]->id, $income->payments[0]->id);
    }

    public function test_destroy_deletes_income()
    {
        $income = Income::factory()->create();

        $response = $this->deleteJson("/api/income/{$income->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Income deleted successfully']);

        $this->assertDatabaseMissing('incomes', ['id' => $income->id]);
    }
}
