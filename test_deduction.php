<?php

use App\Models\Employee;
use App\Models\Shift;
use App\Models\Attendance;
use Carbon\Carbon;

// Create dummy data
$employee = Employee::create([
    'complete_name' => 'Test Employee',
    'late_deduction' => 10000,
    'absent_deduction' => 50000,
    // other required fields... assuming nullable or defaults for now, or minimal set
    'user_id' => 1, 'nickname' => 'Test', 'status' => 'active', 'identity_type' => 'KTP', 'identity_number' => '123',
    'pob' => 'City', 'dob' => '1990-01-01', 'certified' => 0, 'recruiter' => 0, 'branch' => 'Main',
    'base_salary' => 0, 'expertise' => 'None', 'gender' => 'M', 'phone' => '123', 'address' => 'Street',
    'mobile' => '123', 'email' => 'test@example.com', 'meal_fee' => 0, 'bank_account' => '123', 'bank' => 'Bank'
]);

// Test Cases

// 1. On Time
$att1 = new Attendance([
    'employee_id' => $employee->id,
    'shift_id' => 'M',
    'date' => '2023-10-23', // Monday
    'clock_in' => '08:55:00',
    'clock_out' => '17:05:00'
]);
echo "Test 1 (On Time): Deduction = " . $att1->deduction . " (Expected: 0)\n";

// 2. Late 30 mins ( > 5 mins, < 1 hour) -> (0+1)*10000 = 10000
$att2 = new Attendance([
    'employee_id' => $employee->id,
    'shift_id' => 'M',
    'date' => '2023-10-23',
    'clock_in' => '09:30:00',
    'clock_out' => '17:00:00'
]);
echo "Test 2 (Late 30m): Deduction = " . $att2->deduction . " (Expected: 10000)\n";

// 3. Late 1 hour 1 min -> (1+1)*10000 = 20000
$att3 = new Attendance([
    'employee_id' => $employee->id,
    'shift_id' => 'M',
    'date' => '2023-10-23',
    'clock_in' => '10:01:00',
    'clock_out' => '17:00:00'
]);
echo "Test 3 (Late 1h 1m): Deduction = " . $att3->deduction . " (Expected: 20000)\n";

// 4. Late 2 hours -> 2 * 10000 = 20000
$att4 = new Attendance([
    'employee_id' => $employee->id,
    'shift_id' => 'M',
    'date' => '2023-10-23',
    'clock_in' => '11:00:00',
    'clock_out' => '17:00:00'
]);
echo "Test 4 (Late 2h): Deduction = " . $att4->deduction . " (Expected: 20000)\n";

// 6. Absent Weekday -> 1 * 50000 = 50000
$att6 = new Attendance([
    'employee_id' => $employee->id,
    'shift_id' => 'M',
    'date' => '2023-10-23', // Monday
    'clock_in' => null,
    'clock_out' => null
]);
// Mock yesterday check by forcing date to be in past
// The logic uses Carbon::yesterday(), so 2023-10-23 is definitely in past relative to 2025.
echo "Test 6 (Absent Weekday): Deduction = " . $att6->deduction . " (Expected: 50000)\n";

// 7. Absent Weekend -> 2 * 50000 = 100000
$att7 = new Attendance([
    'employee_id' => $employee->id,
    'shift_id' => 'M',
    'date' => '2023-10-22', // Sunday
    'clock_in' => null,
    'clock_out' => null
]);
echo "Test 7 (Absent Weekend): Deduction = " . $att7->deduction . " (Expected: 100000)\n";

// 8. OFF Shift -> OFF
$att8 = new Attendance([
    'employee_id' => $employee->id,
    'shift_id' => 'OFF',
    'date' => '2023-10-23',
    'clock_in' => null,
    'clock_out' => null
]);
echo "Test 8 (OFF Shift): Deduction = " . $att8->deduction . " (Expected: 0)\n";

