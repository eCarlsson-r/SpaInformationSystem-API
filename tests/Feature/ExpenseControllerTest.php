<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\ExpensePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function test_index_returns_all_expenses()
    {
        Expense::factory()->count(3)->create();

        $response = $this->getJson('/api/expense');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_expense()
    {
        $expense = Expense::factory()->create();

        $response = $this->getJson("/api/expense/{$expense->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $expense->id,
                'description' => $expense->description,
            ]);
    }

    public function test_expense_has_items()
    {
        $expense = Expense::factory()->create();
        $items = ExpenseItem::factory()->count(2)->create([
            'expense_id' => $expense->id,
        ]);

        $this->assertCount(2, $expense->items);
        $this->assertEquals($items[0]->id, $expense->items[0]->id);
    }

    public function test_expense_has_payments()
    {
        $expense = Expense::factory()->create();
        $payments = ExpensePayment::factory()->count(2)->create([
            'expense_id' => $expense->id,
        ]);

        $this->assertCount(2, $expense->payments);
        $this->assertEquals($payments[0]->id, $expense->payments[0]->id);
    }

    public function test_destroy_deletes_expense()
    {
        $expense = Expense::factory()->create();

        $response = $this->deleteJson("/api/expense/{$expense->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Expense deleted successfully']);

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }
}
