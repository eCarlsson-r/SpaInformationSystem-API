<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RecommendationBranchContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_are_empty_until_customer_selects_a_branch(): void
    {
        $user = User::factory()->create(['type' => 'CUSTOMER']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $service = Mockery::mock(RecommendationService::class);
        $service->shouldNotReceive('getRecommendations');
        $this->app->instance(RecommendationService::class, $service);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/recommendations?customer_id=' . $customer->id)
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_customer_cannot_request_another_customers_recommendations(): void
    {
        $user = User::factory()->create(['type' => 'CUSTOMER']);
        Customer::factory()->create(['user_id' => $user->id]);
        $otherCustomer = Customer::factory()->create();
        $branch = Branch::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/recommendations?customer_id=' . $otherCustomer->id . '&branch_id=' . $branch->id)
            ->assertForbidden();
    }

    public function test_pos_staff_cannot_request_recommendations_for_another_branch(): void
    {
        $assignedBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $customer = Customer::factory()->create();
        $user = User::factory()->create(['type' => 'CASHIER']);
        Employee::factory()->create(['user_id' => $user->id, 'branch_id' => $assignedBranch->id]);
        $service = Mockery::mock(RecommendationService::class);
        $service->shouldNotReceive('getRecommendations');
        $this->app->instance(RecommendationService::class, $service);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai/recommendations/pos?customer_id=' . $customer->id . '&branch_id=' . $otherBranch->id)
            ->assertForbidden();
    }
}
