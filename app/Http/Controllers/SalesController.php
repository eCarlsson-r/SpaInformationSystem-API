<?php

namespace App\Http\Controllers;

use App\Models\Sales;
use App\Models\Branch;
use App\Models\Income;
use App\Models\IncomePayment;
use App\Models\Journal;
use App\Models\Voucher;
use App\Models\Walkin;
use App\Models\Wallet;
use App\Notifications\SalesMade;
use Illuminate\Http\Request;
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
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Sales::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sales $sales)
    {
        $sales = Sales::with('branch')->find($request->id);
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
                    "amount" => $sales->total, "type" => "cash",
                    "description" => "Uang Tunai"
                ]);
            } else if ($payment["method"] == "card") {
                $income->payments()->create([
                    "wallet_id" => Wallet::where("name", "EDC ".$paymentDetail["card_edc"])->first()->id,
                    "amount" => $sales->total, "type" => "card",
                    "description" => "Kartu ".$paymentDetail["card_type"]." dengan nomor ".$paymentDetail["card_number"]
                ]);
            } else if ($payment["method"] == "ewallet") {
                $income->payments()->create([
                    "wallet_id" => Wallet::where("name", "EDC ".$paymentDetail["wallet_edc"])->first()->id,
                    "amount" => $sales->total, "type" => "card",
                    "description" => "E-Wallet ".$paymentDetail["wallet_edc"]." dengan nomor ".$paymentDetail["mobile_number"]
                ]);
            } else if ($payment["method"] == "voucher") {
                $income->payments()->create([
                    "wallet_id" => Wallet::where("name", "Voucher ".$paymentDetail["voucher_provider"])->first()->id,
                    "amount" => $sales->total, "type" => "card",
                    "description" => "Voucher ".$paymentDetail["voucher_provider"]." dengan nomor ".$paymentDetail["voucher_number"]
                ]);
            } else if ($payment["method"] == "qr") {
                $income->payments()->create([
                    "wallet_id" => Wallet::where("name", "AIO : Kode QR ".$paymentDetail["qr_edc"])->first()->id,
                    "amount" => $sales->total, "type" => "card",
                    "description" => "Kode QR ".$paymentDetail["qr_edc"]
                ]);
            }
        }

        if ($sales) {
            return response()->json($sales, 200);
        } else {
            return response()->json(['message' => 'Failed to update sales'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sales $sales)
    {
        //
    }
}
