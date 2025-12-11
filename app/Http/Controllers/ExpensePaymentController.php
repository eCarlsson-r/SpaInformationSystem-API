<?php

namespace App\Http\Controllers;

use App\Models\ExpensePayment;
use Illuminate\Http\Request;

class ExpensePaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return ExpensePayment::with("wallet")->where("expense_id", $request->input("expense_id"))->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $expensePayment = ExpensePayment::create($request->all());

        Journal::find(Expense::find($request->expense_id)->journal_id)->records()->create([
            'account_id' => Wallet::find($request->wallet_id)->account_id,
            'debit' => 0,
            'credit' => $request->amount
        ]);

        if ($expensePayment) {
            return response()->json($expensePayment, 200);
        } else {
            return response()->json(['message' => 'Failed to create expense payment'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ExpensePayment $expensePayment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ExpensePayment $expensePayment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpensePayment $expensePayment)
    {
        if ($expensePayment->delete()) {
            return response()->json(['message' => 'Expense payment deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete expense payment'], 500);
        }
    }
}
