<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function test_index_returns_all_customers()
    {
        $customers = Customer::factory()->count(3)->create();

        $response = $this->getJson(route('customer.index'));

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_specific_customer()
    {
        $customer = Customer::factory()->create();

        $response = $this->getJson(route('customer.show', $customer->id));

        $response->assertStatus(200)
            ->assertJson([
                'id' => $customer->id,
                'name' => $customer->name,
            ]);
    }

    public function test_destroy_deletes_customer()
    {
        $customer = Customer::factory()->create();

        $response = $this->deleteJson("/api/customer/{$customer->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Customer deleted successfully']);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }
}
