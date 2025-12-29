<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Journal;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->start && $request->end && $request->account) {
            return Expense::query()
                ->join('expense_payments', 'expenses.id', '=', 'expense_payments.expense_id')
                ->join('expense_items', 'expenses.id', '=', 'expense_items.expense_id')
                ->join('wallets', 'expense_payments.wallet_id', '=', 'wallets.id')
                ->leftJoin('customers', function ($join) {
                    $join->on('expenses.partner', '=', 'customers.id')
                        ->where('expenses.partner_type', '=', 'customer');
                })
                ->leftJoin('agents', function ($join) {
                    $join->on('expenses.partner', '=', 'agents.id')
                        ->where('expenses.partner_type', '=', 'agent');
                })
                ->leftJoin('bank', function ($join) {
                    $join->on('expenses.partner', '=', 'bank.id')
                        ->where('expenses.partner_type', '=', 'bank');
                })
                ->leftJoin('suppliers', function ($join) {
                    $join->on('expenses.partner', '=', 'suppliers.id')
                        ->where('expenses.partner_type', '=', 'supplier');
                })
                ->where('expense_payments.wallet_id', $request->account)
                ->whereBetween('expenses.date', [Carbon::parse($request->start), Carbon::parse($request->end)])
                ->groupBy('expenses.id', 'expenses.date', 'expenses.journal_reference', 'expenses.partner_type', 'customers.name', 'agents.name', 'bank.name', 'suppliers.name', 'expenses.description', 'expense_payments.type', 'expense_payments.description', 'wallets.name')
                ->selectRaw("
                    expenses.date,  expenses.journal_reference, expenses.description,
                    IF(expenses.partner_type = 'customer', customers.name, IF(expenses.partner_type = 'agent', agents.name, IF(expenses.partner_type = 'bank', bank.name, IF(expenses.partner_type = 'supplier', suppliers.name, '')))) as `partner`,
                    SUM(expense_items.amount) as `amount`
                ")->get();
        } else {
            return Expense::all();
        }
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
            'partner' => $request->input("partner_".$request->partner_type)
        ]);

        $expense->items()->createMany($request->items);
        $expense->payments()->createMany($request->payments);

        $journal = Journal::create([
            'reference' => $reference,
            'date' => $request->date,
            'description' => $request->description
        ]);

        foreach($request->items as $item) {
            $journal->records()->create([
                'account_id' => $item['account_id'],
                'debit' => $item['amount'],
                'credit' => 0,
                'description' => $item['description'] ?? $request->description
            ]);
        }

        foreach($request->payments as $payment) {
            $journal->records()->create([
                'account_id' => Wallet::find($payment['wallet_id'])->account_id,
                'debit' => 0,
                'credit' => $payment['amount'],
                'description' => $payment['description'] ?? $request->description
            ]);
        }

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
        return Expense::with('items', 'payments')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        $expense->update([
            'date' => $request->date,
            'description' => $request->description,
            'partner_type' => $request->partner_type,
            'partner' => $request->input("partner_".$request->partner_type)
        ]);

        if ($expense) {
            return response()->json($expense, 200);
        } else {
            return response()->json(['message' => 'Failed to update expense'], 500);
        }
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
