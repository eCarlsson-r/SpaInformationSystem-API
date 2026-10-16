<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bed;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\CartRecords;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Room;
use App\Models\Session;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCheckout(): array
    {
        config([
            'services.xendit.secret_key' => 'test-secret',
            'services.xendit.webhook_token' => 'test-webhook-token',
            'services.xendit.success_url' => 'https://booking.example/checkout',
            'services.xendit.cancel_url' => 'https://booking.example/checkout',
        ]);
        Http::fake(['*' => Http::response([
            'payment_session_id' => 'payment-session-test',
            'payment_link_url' => 'https://pay.example/session-test',
        ], 200)]);

        $user = User::factory()->create(['type' => 'CUSTOMER']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create();
        $room = Room::factory()->create(['branch_id' => $branch->id]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        $employee = Employee::factory()->create(['branch_id' => $branch->id]);
        $treatment = Treatment::factory()->create(['price' => 125000, 'duration' => 60]);
        $cart = CartRecords::create([
            'customer_id' => $customer->id,
            'session_type' => 'walkin',
            'session_date' => now()->addDay()->toDateString(),
            'session_time' => '10:00:00',
            'employee_id' => $employee->id,
            'treatment_id' => $treatment->id,
            'room_id' => $room->id,
            'quantity' => 1,
            'price' => 1,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/booking-payments', ['cart_id' => $cart->id])
            ->assertOk()
            ->assertJsonPath('amount', 125000)
            ->assertJsonPath('status', 'pending');

        return compact('branch', 'bed', 'cart', 'customer', 'employee', 'room', 'treatment', 'user') + [
            'payment' => BookingPayment::where('gateway_session_id', 'payment-session-test')->firstOrFail(),
        ];
    }

    private function completedWebhook(BookingPayment $payment): array
    {
        return [
            'event' => 'payment_session.completed',
            'data' => [
                'payment_session_id' => $payment->gateway_session_id,
                'reference_id' => $payment->reference_id,
                'session_type' => 'PAY',
                'mode' => 'PAYMENT_LINK',
                'status' => 'COMPLETED',
                'country' => 'ID',
                'currency' => 'IDR',
                'amount' => $payment->amount,
                'payment_id' => 'gateway-payment-test',
            ],
        ];
    }

    public function test_completed_webhook_atomically_fulfills_and_is_idempotent(): void
    {
        Queue::fake();
        $scenario = $this->createCheckout();
        $cashAccount = Account::factory()->create();
        $incomeAccount = Account::factory()->create();
        $wallet = Wallet::factory()->create(['account_id' => $cashAccount->id]);
        $scenario['branch']->update([
            'cash_account' => (string) $wallet->id,
            'walkin_account' => (string) $incomeAccount->id,
        ]);

        $payload = $this->completedWebhook($scenario['payment']);
        $headers = ['x-callback-token' => 'test-webhook-token'];

        $this->postJson('/api/webhooks/xendit/payment-session', $payload, $headers)->assertOk();
        $scenario['payment']->refresh();

        $this->assertSame('paid', $scenario['payment']->status);
        $this->assertNotNull($scenario['payment']->sales_id);
        $this->assertNotNull($scenario['payment']->session_id);
        $this->assertDatabaseHas('sessions', [
            'id' => $scenario['payment']->session_id,
            'sales_id' => $scenario['payment']->sales_id,
            'status' => 'waiting',
        ]);
        $this->assertDatabaseHas('sales', ['id' => $scenario['payment']->sales_id, 'total' => $scenario['payment']->amount]);

        $this->postJson('/api/webhooks/xendit/payment-session', $payload, $headers)->assertOk();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sessions', 1);
    }

    public function test_completed_payment_with_conflicted_slot_requires_refund_and_is_acknowledged(): void
    {
        Queue::fake();
        $scenario = $this->createCheckout();
        Session::factory()->create([
            'bed_id' => $scenario['bed']->id,
            'employee_id' => $scenario['employee']->id,
            'date' => $scenario['payment']->session_date,
            'start' => $scenario['payment']->session_start,
            'end' => $scenario['payment']->session_end,
            'status' => 'waiting',
        ]);

        $payload = $this->completedWebhook($scenario['payment']);
        $headers = ['x-callback-token' => 'test-webhook-token'];

        $this->postJson('/api/webhooks/xendit/payment-session', $payload, $headers)->assertOk();
        $scenario['payment']->refresh();
        $this->assertSame('refund_required', $scenario['payment']->status);
        $this->assertSame('gateway-payment-test', $scenario['payment']->gateway_payment_id);
        $this->assertNull($scenario['payment']->sales_id);

        $this->postJson('/api/webhooks/xendit/payment-session', $payload, $headers)->assertOk();
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_webhook_rejects_missing_callback_token(): void
    {
        $scenario = $this->createCheckout();

        $this->postJson('/api/webhooks/xendit/payment-session', $this->completedWebhook($scenario['payment']))
            ->assertForbidden();
    }

    public function test_webhook_rejects_amount_mismatch_without_fulfilling(): void
    {
        $scenario = $this->createCheckout();
        $payload = $this->completedWebhook($scenario['payment']);
        $payload['data']['amount']++;

        $this->postJson('/api/webhooks/xendit/payment-session', $payload, ['x-callback-token' => 'test-webhook-token'])
            ->assertUnprocessable();
        $this->assertSame('pending', $scenario['payment']->fresh()->status);
        $this->assertDatabaseCount('sales', 0);
    }
}