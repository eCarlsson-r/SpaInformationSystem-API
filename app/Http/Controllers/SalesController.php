<?php

namespace App\Http\Controllers;

use App\Models\Sales;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Income;
use App\Models\Journal;
use App\Models\Voucher;
use App\Models\Walkin;
use App\Notifications\SalesMade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (auth()->user()->customer) {
            return Sales::with('records')
            ->where('customer_id', auth()->user()->customer->id)->get();
        } else {
            $this->authorizeStaffBranch($request, (int) $request->input('branch'));
            return Sales::with('customer', 'records', 'income', 'income.payments')
                ->whereBetween('date', [Carbon::parse($request->start), Carbon::parse($request->end)])
                ->where('branch_id', $request->branch)
                ->get();
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'customer_id' => 'required|exists:customers,id',
            'employee_id' => 'required|exists:employees,id',
            'subtotal' => 'required|integer|min:0',
            'discount' => 'required|integer|min:0',
            'rounding' => 'required|integer|min:0',
            'total' => 'required|integer|min:0',
            'records' => 'required|array|min:1',
            'records.*.treatment_id' => 'required|exists:treatments,id',
            'records.*.quantity' => 'required|integer|min:1',
            'records.*.price' => 'required|integer|min:0',
            'records.*.discount' => 'required|integer|min:0',
            'records.*.redeem_type' => 'required|in:walkin,voucher,walk-in',
            'records.*.total_price' => 'required|integer|min:0',
        ]);
        $this->authorizeStaffBranch($request, (int) $request->input('branch_id'));

        return DB::transaction(function () use ($request) {
        $incomeId = Income::whereYear('date', date("Y"))->orderBy("id", "desc")->first();
        if ($incomeId) $incomeId = $incomeId->id;
        $previousIncomeId = Income::whereYear('date', '<', date("Y"))->orderBy("id", "desc")->first();
        if ($previousIncomeId) $previousIncomeId = $previousIncomeId->id;

        if ($incomeId) {
            $reference = "EXO.BKM.".date("y").sprintf('%05d', ($incomeId-$previousIncomeId)+1);
        } else {
            $reference = "EXO.BKM.".date("y").sprintf('%05d', 1);
        }

        $sales = Sales::create([
            "date" => date("Y-m-d"),
            "time" => date("H:i:s"),
            "branch_id" => $request->branch_id,
            "customer_id" => $request->customer_id,
            "employee_id" => $request->employee_id,
            "subtotal" => $request->subtotal,
            "discount" => $request->discount,
            "rounding" => $request->rounding,
            "total" => $request->total
        ]);

        $sales->records()->createMany($request->records);

        if ($sales) {
            $customer = Customer::find($request->customer_id)->user;
            if ($customer) $customer->notify(new SalesMade($sales));
            $admin = User::find(1);
            if ($admin) $admin->notify(new SalesMade($sales));
            return response()->json($sales, 201);
        } else {
            return response()->json(['message' => 'Failed to create sales'], 500);
        }
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $sales = Sales::findOrFail($id);
        $request = request();
        if ($request->user()->customer) {
            abort_unless((int) $sales->customer_id === (int) $request->user()->customer->id, 404);
        } else {
            $this->authorizeStaffBranch($request, (int) $sales->branch_id);
        }
        return $sales;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sales $sales)
    {
        $request->validate([
            'id' => 'required|integer|exists:sales,id',
            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|in:cash,card,ewallet,voucher,qr',
            'payments.*.amount' => 'required|integer|min:0',
            'payments.*.details' => 'required|array',
        ]);
        $salesForAuthorization = Sales::findOrFail($request->input('id'));
        $this->authorizeStaffBranch($request, (int) $salesForAuthorization->branch_id);
        abort_unless((int) collect($request->input('payments'))->sum('amount') === (int) $salesForAuthorization->total, 422, 'Payment amounts must equal the sale total.');

        abort_unless((int) $sales->id === (int) $request->input('id'), 404);

        return DB::transaction(function () use ($request) {
        $sales = Sales::with('branch')->lockForUpdate()->findOrFail($request->id);
        abort_if($sales->income_id, 409, 'This sale has already been finalized.');
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
            "journal_reference" => $reference,
            "date" => date("Y-m-d"),
            "partner_type" => "customer",
            "partner" => $sales->customer_id,
            "description" => "Penjualan No. ".$sales->id." a/n ".$sales->customer->name
        ]);

        $sales->update([
            "income_id" => $income->id
        ]);

        $journal = Journal::create([
            "reference" => $sales->income->journal_reference,
            "date" => date("Y-m-d"),
            "description" => "Penjualan No. ".$sales->id." a/n ".$sales->customer->name
        ]);

        foreach($sales->records as $record) {
            if ($record->redeem_type == "voucher") {
                $start = (int)explode($record->treatment_id, $record->voucher_start)[1];
                $end = (int)explode($record->treatment_id, $record->voucher_end)[1];
                for ($i=$start; $i <= $end; $i++) {
                    $voucherCode = $record->treatment_id.sprintf('%06d', $i);
                    $voucher = Voucher::where("id", $voucherCode)->first();
                    if ($voucher) {
                        $voucher->update([
                            "sales_id" => $sales->id,
                            "customer_id" => $sales->customer_id,
                            "amount" => $record->price
                        ]);
                    }
                }

                $journal->records()->create([
                    "account_id" => Branch::find($sales->branch_id)->voucher_purchase_account,
                    "debit" => 0,
                    "credit" => $record->total_price,
                    "description" => $record->treatment->name.", No. Voucher: ".$record->voucher_start." - ".$record->voucher_end
                ]);

                $income->items()->create([
                    "type" => "penjualan",
                    "transaction" => $record->id,
                    "amount" => $record->total_price,
                    "description" => "No. Voucher dari ".$record->voucher_start." s/d ".$record->voucher_end
                ]);
            } else if ($record->redeem_type == "walkin") {
                for ($i=0; $i<$record->quantity; $i++) {
                    Walkin::create([
                        "treatment_id" => $record->treatment_id,
                        "customer_id" => $sales->customer_id,
                        "sales_id" => $sales->id
                    ]);
                }

                $journal->records()->create([
                    "account_id" => Branch::find($sales->branch_id)->walkin_account,
                    "debit" => 0,
                    "credit" => $record->total_price,
                    "description" => $record->treatment->name." WALK IN"
                ]);

                $income->items()->create([
                    "type" => "penjualan",
                    "transaction" => $record->id,
                    "amount" => $record->total_price,
                    "description" => "Walk-in ".$record->treatment->name
                ]);
            }
        } 

        foreach($request->payments as $payment) {
            $paymentDetail = $payment["details"];
            if ($payment["method"] == "cash") {
                $income->payments()->create([
                    "wallet_id" => Branch::find($sales->branch_id)->cash_account,
                    "amount" => $payment['amount'], "type" => "cash",
                    "description" => "Uang Tunai"
                ]);
            } else if ($payment["method"] == "card") {
                $income->payments()->create([
                    "wallet_id" => Wallet::where("name", "EDC ".$paymentDetail["card_edc"])->first()->id,
                    "amount" => $payment['amount'], "type" => "card",
                    "description" => "Kartu ".$paymentDetail["card_type"]." dengan nomor ".$paymentDetail["card_number"]
                ]);
            } else if ($payment["method"] == "ewallet") {
                $income->payments()->create([
                    "wallet_id" => Wallet::where("name", "EDC ".$paymentDetail["wallet_edc"])->first()->id,
                    "amount" => $payment['amount'], "type" => "card",
                    "description" => "E-Wallet ".$paymentDetail["wallet_edc"]." dengan nomor ".$paymentDetail["mobile_number"]
                ]);
            } else if ($payment["method"] == "voucher") {
                $income->payments()->create([
                    "wallet_id" => Wallet::where("name", "Voucher ".$paymentDetail["voucher_provider"])->first()->id,
                    "amount" => $payment['amount'], "type" => "card",
                    "description" => "Voucher ".$paymentDetail["voucher_provider"]." dengan nomor ".$paymentDetail["voucher_number"]
                ]);
            } else if ($payment["method"] == "qr") {
                $income->payments()->create([
                    "wallet_id" => Wallet::where("name", "AIO : Kode QR ".$paymentDetail["qr_edc"])->first()->id,
                    "amount" => $payment['amount'], "type" => "card",
                    "description" => "Kode QR ".$paymentDetail["qr_edc"]
                ]);
            }
        }

        if ($sales) {
            return response()->json($sales, 200);
        } else {
            return response()->json(['message' => 'Failed to update sales'], 500);
        }
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sales $sales)
    {
        //
    }

    private function authorizeStaffBranch(Request $request, int $branchId): void
    {
        $user = $request->user();
        abort_unless(in_array(strtoupper((string) $user->type), ['ADMIN', 'MANAGER', 'STAFF', 'CASHIER', 'THERAPIST'], true), 403);
        if (strtoupper((string) $user->type) !== 'ADMIN') {
            abort_unless((int) $user->employee?->branch_id === $branchId, 403, 'Sales are limited to your assigned branch.');
        }
    }
}
