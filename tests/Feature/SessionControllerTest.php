<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Bed;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Sales;
use App\Models\Session;
use App\Models\Treatment;
use App\Models\Voucher;
use App\Models\Walkin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function test_it_can_create_a_session_via_api()
    {
        User::factory()->create([
            'username' => 'demo_admin',
            'password' => Hash::make('Am12345'),
            'type' => 'ADMIN'
        ]);

        $treatment = Treatment::factory()->create();
        $walkin = Walkin::factory()->create(['treatment_id' => $treatment->id]);
        $sessionData = Session::factory()->make([
            'treatment_id' => $treatment->id,
            'start' => '2023-10-10T10:00:00',
            'walkin_id' => $walkin->id
        ])->toArray();

        $response = $this->postJson('/api/session', $sessionData);

        $response->assertStatus(201); 
        $this->assertDatabaseHas('sessions', [
            'customer_id' => $sessionData['customer_id'],
        ]);
    }

    public function test_it_rejects_a_session_that_overlaps_an_existing_slot(): void
    {
        $existing = Session::factory()->create([
            'date' => now()->addDay()->toDateString(),
            'start' => '10:00:00',
            'end' => '11:00:00',
            'status' => 'waiting',
        ]);

        $response = $this->postJson('/api/session', [
            'bed_id' => $existing->bed_id,
            'customer_id' => Customer::factory()->create()->id,
            'employee_id' => $existing->employee_id,
            'treatment_id' => Treatment::factory()->create()->id,
            'payment' => 'walk-in',
            'date' => $existing->date,
            'start' => '10:30',
        ]);

        $response->assertStatus(409);
    }

    public function test_it_rejects_a_slot_held_by_online_checkout(): void
    {
        $existing = Session::factory()->make();
        $branch = $existing->bed->room->branch;
        $date = now()->addDay()->toDateString();

        BookingPayment::create([
            'customer_id' => $existing->customer_id,
            'branch_id' => $branch->id,
            'treatment_id' => $existing->treatment_id,
            'employee_id' => $existing->employee_id,
            'room_id' => $existing->bed->room_id,
            'bed_id' => $existing->bed_id,
            'session_date' => $date,
            'session_start' => '10:00:00',
            'session_end' => '11:00:00',
            'amount' => 100000,
            'reference_id' => 'SPA-' . \Illuminate\Support\Str::uuid(),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(20),
        ]);

        $this->postJson('/api/session', [
            'bed_id' => $existing->bed_id,
            'customer_id' => Customer::factory()->create()->id,
            'employee_id' => $existing->employee_id,
            'treatment_id' => Treatment::factory()->create()->id,
            'payment' => 'walk-in',
            'date' => $date,
            'start' => '10:30',
        ])->assertStatus(409);
    }

    public function test_pos_session_is_linked_to_its_sale(): void
    {
        $session = Session::factory()->make();
        $branch = Branch::findOrFail($session->bed->room->branch_id);
        $customer = Customer::findOrFail($session->customer_id);
        $sale = Sales::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'employee_id' => $session->employee_id,
            'date' => now()->toDateString(),
            'time' => now()->format('H:i:s'),
            'subtotal' => 100000,
            'discount' => 0,
            'rounding' => 0,
            'total' => 100000,
        ]);
        $walkin = Walkin::create([
            'treatment_id' => $session->treatment_id,
            'customer_id' => $customer->id,
            'sales_id' => $sale->id,
        ]);

        $this->postJson('/api/session', [
            'bed_id' => $session->bed_id,
            'customer_id' => $customer->id,
            'employee_id' => $session->employee_id,
            'treatment_id' => $session->treatment_id,
            'payment' => 'walk-in',
            'walkin_id' => $walkin->id,
            'date' => now()->addDay()->toDateString(),
            'start' => '12:00',
        ])->assertCreated();

        $this->assertDatabaseHas('sessions', [
            'sales_id' => $sale->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_it_belongs_to_a_customer()
    {
        $customer = Customer::factory()->create();
        $session = Session::factory()->create(['customer_id' => $customer->id]);

        $this->assertTrue($session->customer->is($customer));
    }

    public function test_it_belongs_to_an_employee()
    {
        $employee = Employee::factory()->create();
        $session = Session::factory()->create(['employee_id' => $employee->id]);

        $this->assertTrue($session->employee->is($employee));
    }

    public function test_it_belongs_to_a_treatment()
    {
        $treatment = Treatment::factory()->create();
        $session = Session::factory()->create(['treatment_id' => $treatment->id]);

        $this->assertTrue($session->treatment->is($treatment));
    }

    public function test_it_belongs_to_a_bed()
    {
        $bed = Bed::factory()->create();
        $session = Session::factory()->create(['bed_id' => $bed->id]);

        $this->assertTrue($session->bed->is($bed));
    }

    public function test_it_has_one_walkin()
    {
        $session = Session::factory()->create();
        $walkin = Walkin::factory()->create(['session_id' => $session->id]);

        $this->assertTrue($session->walkin->is($walkin));
    }

    public function test_it_has_one_voucher()
    {
        $session = Session::factory()->create();
        $voucher = Voucher::factory()->create(['session_id' => $session->id]);

        $this->assertTrue($session->voucher->is($voucher));
    }

    public function test_it_can_start_a_session()
    {
        $session = Session::factory()->create(['status' => 'waiting']);
        $response = $this->postJson("/api/session/{$session->id}/start");

        $response->assertStatus(200);
        $this->assertEquals('ongoing', $session->fresh()->status);
    }

    public function test_it_can_finish_a_session()
    {
        $session = Session::factory()->create(['status' => 'ongoing']);
        $response = $this->postJson("/api/session/{$session->id}/finish");

        $response->assertStatus(200);
        $this->assertEquals('completed', $session->fresh()->status);
    }

    public function test_it_can_delete_a_session()
    {
        $session = Session::factory()->create();
        $response = $this->deleteJson("/api/session/{$session->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('sessions', ['id' => $session->id]);
    }
}
