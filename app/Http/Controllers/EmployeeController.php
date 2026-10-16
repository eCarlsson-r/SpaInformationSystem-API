<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\BookingPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Employee::query();

        $query->when($request->has('id'), function ($query) use ($request) {
            return $query->where('id', $request->input('id'));
        });

        $query->when($request->has('branch_id'), function ($query) use ($request) {
            return $query->where('branch_id', $request->input('branch_id'));
        });

        $query->when($request->has('active_only'), function ($query) {
            return $query->where('status', 'active');
        });

        if ($request->input('show') === 'therapist') {
            $data = $request->validate([
                'branch_id' => 'required|integer|exists:branches,id',
                'date' => 'required|date',
                'time' => 'required|date_format:H:i',
                'duration' => 'sometimes|integer|min:1|max:1440',
            ]);
            $start = Carbon::createFromFormat('Y-m-d H:i', Carbon::parse($data['date'])->toDateString() . ' ' . $data['time']);
            $end = $start->copy()->addMinutes((int) ($data['duration'] ?? 60));
            $date = $start->toDateString();

            $query->where('status', 'active')
                ->whereHas('grade', fn ($grades) => $grades
                    ->where('grade', '!=', 'K')
                    ->where('start_date', '<=', $date)
                    ->where(fn ($ends) => $ends->whereNull('end_date')->orWhere('end_date', '>=', $date)))
                ->whereHas('attendance', fn ($attendance) => $attendance->whereDate('date', $date))
                ->whereDoesntHave('sessions', fn ($sessions) => $sessions
                    ->whereDate('date', $date)
                    ->whereIn('status', ['waiting', 'ongoing'])
                    ->where('start', '<', $end->format('H:i:s'))
                    ->where('end', '>', $start->format('H:i:s')));

            $heldEmployeeIds = BookingPayment::query()
                ->whereDate('session_date', $date)
                ->whereIn('status', ['creating', 'pending'])
                ->where('expires_at', '>', now())
                ->where('session_start', '<', $end->format('H:i:s'))
                ->where('session_end', '>', $start->format('H:i:s'))
                ->pluck('employee_id');
            $query->whereNotIn('id', $heldEmployeeIds)->select(['id', 'name', 'branch_id']);
        }

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $employee = Employee::create($request->all());

        if ($employee) {
            return response()->json($employee, 201);
        } else {
            return response()->json(['message' => 'Failed to create employee'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Employee::with('grade')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        if ($employee->update($request->all())) {
            return response()->json($employee, 200);
        } else {
            return response()->json(['message' => 'Failed to update employee'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        if ($employee->delete()) {
            return response()->json(['message' => 'Employee deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete employee'], 500);
        }
    }
}
