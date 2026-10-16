<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Branch;
use App\Models\Attendance;
use App\Models\Bed;
use App\Models\Grade;
use App\Models\Room;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->authenticate();
    }

    public function test_index_returns_employees()
    {
        $employees = Employee::factory()->count(3)->create();

        $response = $this->getJson('/api/employee');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_specific_employee()
    {
        $employee = \App\Models\Employee::factory()->create();

        $response = $this->getJson("/api/employee/{$employee->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $employee->id,
                'name' => $employee->name,
            ]);
    }

    public function test_store_creates_employee()
    {
        $employeeData = \App\Models\Employee::factory()->make()->toArray();

        $response = $this->postJson('/api/employee', $employeeData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('employees', ['name' => $employeeData['name']]);
    }

    public function test_update_modifies_employee()
    {
        $employee = \App\Models\Employee::factory()->create();
        $newName = 'Updated Employee Name';

        $response = $this->putJson("/api/employee/{$employee->id}", ['name' => $newName]);

        $response->assertStatus(200);
        $this->assertEquals($newName, $employee->fresh()->name);
    }

    public function test_destroy_deletes_employee()
    {
        $employee = Employee::factory()->create();

        $response = $this->deleteJson(route('employee.destroy', $employee->id));

        $response->assertStatus(200)
             ->assertJson(['message' => 'Employee deleted successfully']);

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }

    public function test_therapist_list_uses_branch_and_duration_aware_session_availability(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $free = Employee::factory()->create(['branch_id' => $branch->id, 'status' => 'active']);
        $busy = Employee::factory()->create(['branch_id' => $branch->id, 'status' => 'active']);
        $other = Employee::factory()->create(['branch_id' => $otherBranch->id, 'status' => 'active']);
        $date = now()->addDay()->toDateString();

        foreach ([$free, $busy, $other] as $employee) {
            Grade::factory()->create([
                'employee_id' => $employee->id,
                'grade' => 'A',
                'start_date' => now()->subYear()->toDateString(),
                'end_date' => null,
            ]);
            Attendance::factory()->create(['employee_id' => $employee->id, 'date' => $date]);
        }

        $room = Room::factory()->create(['branch_id' => $branch->id]);
        $bed = Bed::factory()->create(['room_id' => $room->id]);
        Session::create([
            'bed_id' => $bed->id,
            'customer_id' => \App\Models\Customer::factory()->create()->id,
            'employee_id' => $busy->id,
            'treatment_id' => \App\Models\Treatment::factory()->create()->id,
            'date' => $date,
            'start' => '10:00:00',
            'end' => '11:00:00',
            'status' => 'waiting',
            'payment' => 'walk-in',
        ]);

        $this->getJson('/api/employee?show=therapist&branch_id=' . $branch->id . '&date=' . $date . '&time=10:30&duration=60')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $free->id)
            ->assertJsonMissing(['id' => $busy->id])
            ->assertJsonMissing(['id' => $other->id]);
    }
}
