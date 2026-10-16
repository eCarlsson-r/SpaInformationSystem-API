<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffAiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_account_cannot_call_staff_chat(): void
    {
        $user = User::factory()->create(['type' => 'CUSTOMER']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/chat/staff', ['query' => 'Show revenue'])
            ->assertForbidden()
            ->assertJsonPath('type', 'authorization_error');
    }

    public function test_staff_chat_calculates_revenue_only_for_the_authenticated_branch(): void
    {
        config([
            'ai.default' => 'openai',
            'ai.providers.openai.key' => 'test-key',
            'ai.providers.openai.models.text.default' => 'gpt-4o-mini',
        ]);
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test',
                'model' => 'gpt-4o-mini',
                'output' => [[
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'type' => 'data_response',
                            'intent' => 'revenue_query',
                            'value' => '',
                            'period' => '',
                            'branch' => '',
                            'formattedAnswer' => '',
                        ]),
                    ]],
                ]],
            ]),
        ]);

        $assignedBranch = Branch::factory()->create(['name' => 'Assigned Branch']);
        $otherBranch = Branch::factory()->create(['name' => 'Other Branch']);
        $customer = Customer::factory()->create();
        $user = User::factory()->create(['type' => 'CASHIER']);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'branch_id' => $assignedBranch->id]);

        foreach ([[$assignedBranch, 250000], [$otherBranch, 900000]] as [$branch, $total]) {
            Sales::create([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'employee_id' => $employee->id,
                'date' => now()->toDateString(),
                'time' => now()->format('H:i:s'),
                'subtotal' => $total,
                'discount' => 0,
                'rounding' => 0,
                'total' => $total,
            ]);
        }

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/chat/staff', ['query' => 'Show revenue this month for all branches'])
            ->assertOk()
            ->assertJsonPath('value', '250000')
            ->assertJsonPath('branch', 'Assigned Branch')
            ->assertJsonPath('formattedAnswer', 'Revenue for this month at Assigned Branch: IDR 250,000');
    }
}
