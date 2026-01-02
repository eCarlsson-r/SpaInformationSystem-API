<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Branch;
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
}
