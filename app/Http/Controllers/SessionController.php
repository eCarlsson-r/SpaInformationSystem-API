<?php

namespace App\Http\Controllers;

use App\Models\Session;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $sessions = Session::whereIn('sessions.status', json_decode($request->input('status')))
            ->join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
            ->join('customers', 'sessions.customer_id', '=', 'customers.id')
            ->join('employees', 'sessions.employee_id', '=', 'employees.id')
            ->join('beds', 'sessions.bed_id', '=', 'beds.id')
            ->leftJoin('walkin', 'walkin.session_id', '=', 'sessions.id')
            ->leftJoin('voucher', 'voucher.session_id', '=', 'sessions.id')
            ->leftJoin('sales', 'sales.id', '=', 'walkin.sales_id')
            ->leftJoin('incomes', 'incomes.id', '=', 'sales.income_id')
            ->select(
                'sessions.*',
                'customers.name AS customer_name', 
                'treatments.name AS treatment_name', 
                'treatments.duration AS treatment_duration', 
                'employees.name AS therapist_name', 
                'beds.name AS bed_name',
                'walkin.id AS walkin_id',
                'voucher.id AS voucher_id',
                'incomes.journal_reference AS reference'
            );
        
        if ($request->input('branch_id')) {
            return $sessions->where('branch_id', $request->input('branch_id'))->get();
        } else if ($request->input('employee_id')) {
            return $sessions->where('employee_id', $request->input('employee_id'))->get();
        } else {
            return $sessions->get();
        }
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
        return Session::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Session $session)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Session $session)
    {
        if ($session->delete()) {
            return response()->json(['message' => 'Session deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete session'], 500);
        }
    }
}
