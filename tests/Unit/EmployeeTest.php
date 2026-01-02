<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Branch;
use App\Models\Session;
use App\Models\Grade;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_has_default_attributes()
    {
        $employee = Employee::factory()->create();

        $this->assertEquals(50000, $employee->absent_deduction);
        $this->assertEquals(0, $employee->meal_fee);
        $this->assertEquals(20000, $employee->late_deduction);
    }

    public function test_employee_belongs_to_branch()
    {
        $branch = Branch::factory()->create();
        $employee = Employee::factory()->create(['branch_id' => $branch->id]);

        $this->assertInstanceOf(Branch::class, $employee->branch);
        $this->assertTrue($employee->branch->is($branch));
    }

    public function test_employee_has_many_sessions()
    {
        $employee = Employee::factory()->create();
        $session = Session::factory()->create(['employee_id' => $employee->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $employee->sessions);
        $this->assertTrue($employee->sessions->contains($session));
    }

    public function test_employee_has_many_grades()
    {
        $employee = Employee::factory()->create();
        $grade = Grade::factory()->create(['employee_id' => $employee->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $employee->grade);
        $this->assertTrue($employee->grade->contains($grade));
    }

    public function test_employee_has_many_attendance()
    {
        $employee = Employee::factory()->create();
        $attendance = Attendance::factory()->create(['employee_id' => $employee->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $employee->attendance);
        $this->assertTrue($employee->attendance->contains($attendance));
    }
}
