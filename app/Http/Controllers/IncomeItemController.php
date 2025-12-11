<?php

namespace App\Http\Controllers;

use App\Models\IncomeItem;
use Illuminate\Http\Request;

class IncomeItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return IncomeItem::where("income_id", $request->input("income_id"))->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $incomeItem = IncomeItem::create($request->all());

        Journal::find(Income::find($request->income_id)->journal_id)->records()->create([
            'account_id' => $request->account_id,
            'debit' => $request->amount,
            'credit' => 0
        ]);

        if ($incomeItem) {
            return response()->json($incomeItem, 200);
        } else {
            return response()->json(['message' => 'Failed to create income item'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(IncomeItem $incomeItem)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, IncomeItem $incomeItem)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(IncomeItem $incomeItem)
    {
        if ($incomeItem->delete()) {
            return response()->json(['message' => 'Income item deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete income item'], 500);
        }
    }
}
