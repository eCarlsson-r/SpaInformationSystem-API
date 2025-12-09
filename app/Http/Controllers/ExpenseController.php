<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Expense::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $expenseId = Expense::whereYear('date', date("Y"))->orderBy("id", "desc")->first();
        if ($expenseId) $expenseId = $expenseId->id;
        $previousExpenseId = Expense::whereYear('date', '<', date("Y"))->orderBy("id", "desc")->first();
        if ($previousExpenseId) $previousExpenseId = $previousExpenseId->id;

        if ($expenseId) {
            $reference = "EXO.BKK.".date("y").sprintf('%05d', ($expenseId-$previousExpenseId)+1);
        } else {
            $reference = "EXO.BKK.".date("y").sprintf('%05d', 1);
        }
        $expense = Expense::create([
            'journal_reference' => $reference,
            'date' => $request->date,
            'description' => $request->description,
            'partner_type' => $request->partner_type,
            'partner' => $request->partner
        ]);

        $expense->items()->createMany($request->items);
        $expense->payments()->createMany($request->payments);

        if ($expense) {
            return response()->json($expense, 201);
        } else {
            return response()->json(['message' => 'Failed to create expense'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Expense::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        if ($expense->delete()) {
            return response()->json(['message' => 'Expense deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete expense'], 500);
        }
    }
}
