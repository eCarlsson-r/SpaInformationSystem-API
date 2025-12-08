<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->input("treatment")) {
            return Voucher::where("treatment_id", $request->input("treatment"))->where("id", "LIKE", $request->input("treatment")."%")->orderBy("id", "desc")->first();
        } else return Voucher::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        if ($request->quantity) {
            $quantity = $request->quantity;
            $voucherEnd = substr($id, 0, 4).sprintf('%06d', intval(substr($id, 4))+(intval($quantity)-1));
            return Voucher::select(
                'vouchers.*', 
                'sales.income_id', 'sales.date AS sales_date', 
                'sessions.id', 'sessions.date AS session_date',
                'employees.name'
            )->leftJoin('sales', 'sales.id', '=', 'vouchers.sales_id')
            ->leftJoin('sessions', 'sessions.id', '=', 'vouchers.session_id')
            ->leftJoin('employees', 'employees.id', '=', 'sessions.employee_id')
            ->where("vouchers.id BETWEEN " . $id . " AND " . $voucherEnd)->get();
        } else {
            return Voucher::select(
                'voucher.amount', 'voucher.customer_id', 'voucher.id', 'sessions.date AS session_date', 
                'voucher.purchase_date', 'voucher.register_date', 'voucher.register_time', 'employees.name',
                'voucher.sales_id', 'incomes.journal_reference', 'voucher.session_id', 'voucher.treatment_id'
            )->leftJoin('sales', 'sales.id', '=', 'voucher.sales_id')->leftJoin('sessions', 'sessions.id', '=', 'voucher.session_id')
            ->leftJoin('incomes', 'incomes.id', '=', 'sales.income_id')->leftJoin('employees', 'employees.id', '=', 'sessions.employee_id')
            ->where('voucher.id', $id)->first();
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Voucher $voucher)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Voucher $voucher)
    {
        //
    }
}