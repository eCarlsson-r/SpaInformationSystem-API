<?php

namespace App\Http\Controllers;

use App\Models\IncomePayment;
use Illuminate\Http\Request;

class IncomePaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return IncomePayment::where("income_id", $request->input("income_id"))->get();
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
    public function show(IncomePayment $incomePayment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, IncomePayment $incomePayment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(IncomePayment $incomePayment)
    {
        if ($incomePayment->delete()) {
            return response()->json(['message' => 'Income payment deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete income payment'], 500);
        }
    }
}
