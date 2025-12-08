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

    public function test_index_returns_employees()
    {
        $employees = Employee::factory()->count(3)->create();

        $response = $this->getJson(route('employee.index'));

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_show_returns_specific_employee()
    {
        $employee = Employee::factory()->create();

        $response = $this->getJson(route('employee.show', $employee->id));

        $response->assertStatus(200)
            ->assertJson([
                'id' => $employee->id,
                'name' => $employee->name,
            ]);
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
