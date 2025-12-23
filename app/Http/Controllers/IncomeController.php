<?php

namespace App\Http\Controllers;

use App\Models\Income;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Income::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $incomeId = Income::whereYear('date', date("Y"))->orderBy("id", "desc")->first();
        if ($incomeId) $incomeId = $incomeId->id;
        $previousIncomeId = Income::whereYear('date', '<', date("Y"))->orderBy("id", "desc")->first();
        if ($previousIncomeId) $previousIncomeId = $previousIncomeId->id;

        if ($incomeId) {
            $reference = "EXO.BKM.".date("y").sprintf('%05d', ($incomeId-$previousIncomeId)+1);
        } else {
            $reference = "EXO.BKM.".date("y").sprintf('%05d', 1);
        }
        $income = Income::create([
            'journal_reference' => $reference,
            'date' => $request->date,
            'description' => $request->description,
            'partner_type' => $request->partner_type,
            'partner' => $request->input("partner_".$request->partner_type)
        ]);

        $income->items()->createMany($request->items);
        $income->payments()->createMany($request->payments);

        $journal = Journal::create([
            'reference' => $reference,
            'date' => $request->date,
            'description' => $request->description
        ]);

        foreach($request->items as $item) {
            $journal->records()->create([
                'account_id' => $item['account_id'],
                'debit' => $item['amount'],
                'credit' => 0
            ]);
        }

        foreach($request->payments as $payment) {
            $journal->records()->create([
                'account_id' => Wallet::find($payment['wallet_id'])->account_id,
                'debit' => 0,
                'credit' => $payment['amount']
            ]);
        }

        if ($income) {
            return response()->json($income, 201);
        } else {
            return response()->json(['message' => 'Failed to create income'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Income $income)
    {
        return Income::with('items', 'payments')->findOrFail($income->id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Income $income)
    {
        $income->update([
            'date' => $request->date,
            'description' => $request->description,
            'partner_type' => $request->partner_type,
            'partner' => $request->input("partner_".$request->partner_type)
        ]);

        if ($income) {
            return response()->json($income, 200);
        } else {
            return response()->json(['message' => 'Failed to update income'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Income $income)
    {
        if ($income->delete()) {
            return response()->json(['message' => 'Income deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete income'], 500);
        }
    }
}
