<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Employee::query();

        $query->when(request()->has('id'), function ($query) {
            return $query->where('id', request('id'));
        });

        $query->when(request()->has('branch_id'), function ($query) {
            return $query->where('branch_id', request('branch_id'));
        });

        $query->when(request()->has('active_only'), function ($query) {
            return $query->where('status', 'fixed');
        });

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Employee::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        //
    }
}
