<?php

namespace App\Http\Controllers;

use App\Models\ExpenseItem;
use Illuminate\Http\Request;

class ExpenseItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return ExpenseItem::where("expense_id", $request->input("expense_id"))->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $expenseItem = ExpenseItem::create($request->all());

        Journal::find(Expense::find($request->expense_id)->journal_id)->records()->create([
            'account_id' => $request->account_id,
            'debit' => $request->amount,
            'credit' => 0
        ]);

        if ($expenseItem) {
            return response()->json($expenseItem, 200);
        } else {
            return response()->json(['message' => 'Failed to create expense item'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ExpenseItem $expenseItem)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ExpenseItem $expenseItem)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpenseItem $expenseItem)
    {
        if ($expenseItem->delete()) {
            return response()->json(['message' => 'Expense item deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete expense item'], 500);
        }
    }
}
