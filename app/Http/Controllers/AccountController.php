<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalRecord;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->has("start") && $request->has("end")) {
            $startDate = Carbon::parse($request->input("start"))->toDateString();
            $endDate = Carbon::parse($request->input("end"))->toDateString();

            $types = ['income', 'cost-of-sales', 'adm-expenses', 'other-expenses', 'other-income', 'tax'];

            return Account::query()
                ->join('journal_records', 'accounts.id', '=', 'journal_records.account_id')
                ->join('journals', 'journal_records.journal_id', '=', 'journals.id')
                ->whereIn('accounts.type', $types)
                ->selectRaw('
                    accounts.id, accounts.name, accounts.category, accounts.type,
                    ABS(SUM(CASE WHEN journals.date BETWEEN ? AND ? THEN (journal_records.debit - journal_records.credit) ELSE 0 END)) as `current`,
                    ABS(SUM(journal_records.debit - journal_records.credit)) as `previous`
                ', [$startDate, $endDate])
                ->groupBy('accounts.id', 'accounts.name', 'accounts.type')
                ->orderBy('accounts.id', 'ASC')
                ->get();
        } else if ($request->has("end")) {
            $endDate = Carbon::parse($request->input("end"))->toDateString();

            return Account::join('journal_records', 'accounts.id', '=', 'journal_records.account_id')
                ->join('journals', 'journal_records.journal_id', '=', 'journals.id')
                ->where('journals.date', '<=', $endDate)
                ->selectRaw('
                    accounts.id, accounts.name, accounts.type, accounts.category,
                    SUM(journal_records.debit - journal_records.credit) as balance
                ')
                ->groupBy('accounts.id', 'accounts.name', 'accounts.type')
                ->orderBy('accounts.id', 'ASC')
                ->get();
        } else {
            return Account::all();
        }
    }

    public function lookup(Request $request) {
        $query = Account::query()->select('id', 'name', 'type', 'category');

        // If a specific type is requested, filter the results
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        return response()->json($query->orderBy('name')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $account = Account::create($request->all());
        
        if ($account) {
            return response()->json($account, 201);
        } else {
            return response()->json(['message' => 'Failed to create account'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        if ($request->has("start") && $request->has("end")) {
            $startDate = Carbon::parse($request->input("start"))->toDateString();
            $endDate = Carbon::parse($request->input("end"))->toDateString();

            // 1. Saldo AWAL (Beginning Balance)
            $saldoAwal = JournalRecord::join('journals', 'journal_records.journal_id', '=', 'journals.id')
                ->where('journal_records.account_id', $id)
                ->where('journals.date', '<', $startDate)
                ->selectRaw("
                    '' as date, 
                    '' as `ref-no`, 
                    'SALDO AWAL' as description, 
                    COALESCE(SUM(debit), 0) as debit, 
                    COALESCE(SUM(credit), 0) as credit, 
                    0 as OrderNum
                ")
                ->get();

            // 2. Ledger Entries with specific OrderNum categorization
            $entries = JournalRecord::join('journals', 'journal_records.journal_id', '=', 'journals.id')
                ->where('journal_records.account_id', $id)
                ->whereBetween('journals.date', [$startDate, $endDate])
                ->selectRaw("
                    journals.date, 
                    journals.reference, 
                    journal_records.description, 
                    journal_records.debit, 
                    journal_records.credit,
                    CASE 
                        WHEN journals.reference LIKE 'EXO.BKM%' THEN 1
                        WHEN journals.reference LIKE 'EXO.BKK%' THEN 2
                        WHEN journals.reference LIKE 'EXO.BPB%' THEN 3
                        WHEN journals.reference LIKE 'TS%' THEN 4
                        ELSE 5
                    END as OrderNum
                ")
                ->orderBy('journals.date')
                ->orderBy('OrderNum')
                ->orderBy('journals.reference')
                ->get();

            return $saldoAwal->concat($entries);
        } else {
            return Account::findOrFail($id);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Account $account)
    {
        if ($account->update($request->all())) {
            return response()->json($account, 200);
        } else {
            return response()->json(['message' => 'Failed to update account'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Account $account)
    {
        if ($account->delete()) {
            return response()->json(['message' => 'Account deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete account'], 500);
        }
    }
}
