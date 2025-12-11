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
        $incomePayment = IncomePayment::create($request->all());

        Journal::find(Income::find($request->income_id)->journal_id)->records()->create([
            'account_id' => Wallet::find($request->wallet_id)->account_id,
            'debit' => 0,
            'credit' => $request->amount
        ]);

        if ($incomePayment) {
            return response()->json($incomePayment, 200);
        } else {
            return response()->json(['message' => 'Failed to create income payment'], 500);
        }
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
