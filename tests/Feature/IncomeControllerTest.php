<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Wallet;
use App\Models\Income;
use App\Models\IncomeItem;
use App\Models\IncomePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

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

    public function test_store_creates_income()
    {
        $account = Account::factory()->create();
        $wallet = Wallet::factory()->create(['account_id' => $account->id]);
        
        $incomeData = [
            'date' => '2023-10-10',
            'description' => 'Test Income',
            'partner_type' => 'customer',
            'partner_customer' => Customer::factory()->create()->id,
            'items' => [
                ['account_id' => $account->id, 'type' => 'penjualan', 'transaction' => 'sales', 'amount' => 100, 'description' => 'Item description'],
            ],
            'payments' => [
                ['wallet_id' => $wallet->id, 'type' => 'cash', 'amount' => 100, 'description' => 'Payment description'],
            ],
        ];

        $response = $this->postJson('/api/income', $incomeData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('incomes', ['description' => 'Test Income']);
        $this->assertDatabaseHas('journals', ['description' => 'Test Income']);
    }

    public function test_update_modifies_income()
    {
        $income = Income::factory()->create();
        $newDescription = 'Updated Income Description';

        $response = $this->putJson("/api/income/{$income->id}", [
            'date' => $income->date,
            'description' => $newDescription,
            'partner_type' => 'customer',
            'partner_customer' => Customer::factory()->create()->id,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($newDescription, $income->fresh()->description);
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
