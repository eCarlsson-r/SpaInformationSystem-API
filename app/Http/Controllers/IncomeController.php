<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\Journal;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Carbon\Carbon;

class IncomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->variant && $request->start && $request->end && $request->account) {
            $query = Income::query()
                ->join('income_payments', 'incomes.id', '=', 'income_payments.income_id')
                ->join('income_items', 'incomes.id', '=', 'income_items.income_id')
                ->join('wallets', 'income_payments.wallet_id', '=', 'wallets.id')
                ->leftJoin('customers', function ($join) {
                    $join->on('incomes.partner', '=', 'customers.id')
                        ->where('incomes.partner_type', '=', 'customer');
                })
                ->leftJoin('agents', function ($join) {
                    $join->on('incomes.partner', '=', 'agents.id')
                        ->where('incomes.partner_type', '=', 'agent');
                })
                ->where('income_payments.wallet_id', $request->account)
                ->whereBetween('incomes.date', [Carbon::parse($request->start), Carbon::parse($request->end)])
                ->groupBy('incomes.id', 'incomes.date', 'incomes.journal_reference', 'incomes.partner_type', 'customers.name', 'agents.name', 'incomes.description', 'income_payments.type', 'income_payments.description', 'wallets.name');

            if ($request->variant == 1) {
                return $query->selectRaw("
                        incomes.date,  incomes.journal_reference, incomes.description,
                        IF(incomes.partner_type = 'customer', customers.name, IF(incomes.partner_type = 'agent', agents.name, '')) as `partner`,
                        SUM(income_items.amount) as `amount`
                    ")
                    ->get();
            } else if ($request->variant == 2) {
                return $query->selectRaw("
                        incomes.date, incomes.journal_reference, incomes.description,
                        IF(incomes.partner_type = 'customer', customers.name, IF(incomes.partner_type = 'agent', agents.name, '')) as `partner`,
                        income_payments.type as `pay_type`,
                        IF(income_payments.description NOT LIKE 'Kartu%',
                            CONCAT(IF(income_payments.description LIKE 'Voucher%', 'Voucher ', 'eWallet '), IF(income_payments.description LIKE 'Voucher%', SUBSTRING_INDEX(wallets.name, ' ', -1), SUBSTRING_INDEX(income_payments.description, ' ', 1)), ' [', SUBSTRING_INDEX(income_payments.description, ' ', -1), ']'),
                            CONCAT(IF(income_payments.description LIKE 'Kartu debit%', 'Kartu Debit', 'Kartu Kredit'), ' [', RIGHT(SUBSTRING_INDEX(income_payments.description, ' ', -1), 4), ']')
                        ) as `pay_tool`,
                        SUM(income_items.amount) as `amount`
                    ")
                    ->get();
            } else {
                return response()->json(['message' => 'Please select report variant.'], 500);
            }
        } else {
            return Income::all();
        }
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
