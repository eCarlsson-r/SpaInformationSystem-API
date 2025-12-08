<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Session;
use App\Models\Treatment;
use App\Models\Voucher;
use App\Models\Walkin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function it_can_create_a_session()
    {
        $session = Session::factory()->create();

        $this->assertDatabaseHas('sessions', [
            'id' => $session->id,
        ]);
    }

    public function it_belongs_to_a_customer()
    {
        $customer = Customer::factory()->create();
        $session = Session::factory()->create(['customer_id' => $customer->id]);

        $this->assertTrue($session->customer->is($customer));
    }

    public function it_belongs_to_an_employee()
    {
        $employee = Employee::factory()->create();
        $session = Session::factory()->create(['employee_id' => $employee->id]);

        $this->assertTrue($session->employee->is($employee));
    }

    public function it_belongs_to_a_treatment()
    {
        $treatment = Treatment::factory()->create();
        $session = Session::factory()->create(['treatment_id' => $treatment->id]);

        $this->assertTrue($session->treatment->is($treatment));
    }

    public function it_belongs_to_a_bed()
    {
        $bed = Bed::factory()->create();
        $session = Session::factory()->create(['bed_id' => $bed->id]);

        $this->assertTrue($session->bed->is($bed));
    }

    public function it_has_one_walkin()
    {
        $session = Session::factory()->create();
        $walkin = Walkin::factory()->create(['session_id' => $session->id]);

        $this->assertTrue($session->walkin->is($walkin));
    }

    public function it_has_one_voucher()
    {
        $session = Session::factory()->create();
        $voucher = Voucher::factory()->create(['session_id' => $session->id]);

        $this->assertTrue($session->voucher->is($voucher));
    }
}
