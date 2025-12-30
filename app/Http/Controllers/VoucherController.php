<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\Treatment;
use App\Models\Session;
use App\Models\SalesRecord;
use Illuminate\Http\Request;
use Carbon\Carbon;

class VoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->input("treatment")) {
            return Voucher::where("treatment_id", $request->input("treatment"))->where("id", "LIKE", $request->input("treatment")."%")->orderBy("id", "desc")->first();
        } else if ($request->input("variant") && $request->input("end") && $request->input("metric")) {
            if ($request->input("variant") == "QTY") {
                $metrics = json_decode($request->input("metric"));
                $in_stock = in_array("in-stock", $metrics);
                $sold_out = in_array("sold-out", $metrics);

                $vouchersQuery = Voucher::with('customer')->orderBy('id');

                if ($in_stock && !$sold_out) {
                    $vouchersQuery->whereNull('customer_id');
                } else if (!$in_stock && $sold_out) {
                    $vouchersQuery->whereNotNull('customer_id');
                }

                $allVouchers = $vouchersQuery->where('register_date', '<=', Carbon::parse($request->input("end"))->format('Y-m-d'))->get();

                return $allVouchers->groupBy('treatment_id')->map(function ($group, $treatmentId) use ($in_stock, $sold_out) {
                    $treatment = Treatment::find($treatmentId);
                    $name = $treatment ? $treatment->name : $treatmentId;

                    $chunked = collect();
                    if ($in_stock && !$sold_out) {
                        // Use treatment's voucher_normal_quantity for book grouping
                        $chunked = $group->chunk($treatment->voucher_normal_quantity);
                    } else {
                        // Group by customer for Sold Out or Combined reports
                        $chunked = $group->chunkWhile(fn($v, $k, $c) => $v->customer_id === $c->last()->customer_id);
                    }

                    $voucherList = $chunked->map(fn($chunk) => [
                        'name' => $name,
                        'range' => $chunk->count() > 1 ? "{$chunk->first()->id} s/d {$chunk->last()->id}" : $chunk->first()->id,
                        'count' => $chunk->count(),
                        'customer-name' => $chunk->first()->customer_id ? $chunk->first()->customer->name : 'IN STOCK'
                    ])->values();

                    return [
                        "name" => $name,
                        "voucher" => $voucherList
                    ];
                })->values();

            } else if ($request->input("variant") == "REKAP_TANGGAL_PENJUALAN") {
                return Voucher::join('sales', 'voucher.sales_id', '=', 'sales.id')
                    ->selectRaw('treatment_id, MONTH(sales.date) as month, YEAR(sales.date) as year, COUNT(*) as count, SUM(voucher.amount) as amount')
                    ->groupBy('treatment_id', 'year', 'month')
                    ->get()
                    ->groupBy('treatment_id')
                    ->map(function ($sales, $treatmentId) {
                        $treatment = Treatment::find($treatmentId);
                        return [
                            'name' => $treatment ? $treatment->name : $treatmentId,
                            'sales' => $sales->map(fn($item) => [
                                'name' => $treatment ? $treatment->name : $treatmentId,
                                'month' => $item->month,
                                'year' => $item->year,
                                'count' => $item->count,
                                'amount' => $item->amount,
                            ])->values()
                        ];
                    })->values();
            }
        } else if ($request->input("variant") == "voucher-sales") {
            $fromDate = Carbon::parse($request->input("start"))->format("Y-m-d");
            $toDate = Carbon::parse($request->input("end"))->format("Y-m-d");
            return Voucher::join("sales_records", "voucher.sales_id", "=", "sales_records.sales_id")
                ->join("sales", "sales_records.sales_id", "=", "sales.id")
                ->join("incomes", "sales.income_id", "=", "incomes.id")
                ->join("treatments", "voucher.treatment_id", "=", "treatments.id")
                ->join("branches", "sales.branch_id", "=", "branches.id")
                ->join("customers", "sales.customer_id", "=", "customers.id")
                ->selectRaw(
                    "sales.date, incomes.journal_reference, 
                    CONCAT(treatments.name,'\n',voucher_start,' s/d ',voucher_end) AS description, 
                    COUNT(voucher.id) AS quantity,  sales_records.price, 
                    ROUND((COUNT(voucher.id)*sales_records.price)/1000)*1000 AS total"
                )->whereRaw("sales.date BETWEEN '$fromDate' AND '$toDate'")
                ->groupBy(
                    "sales.id", 
                    "sales.date", 
                    "incomes.journal_reference", 
                    "treatments.name", 
                    "voucher_start", 
                    "voucher_end", 
                    "sales_records.price"
                )
                ->orderBy("journal_reference")
                ->get();
        } else if ($request->input("variant") == "sales-by-date" || $request->input("variant") == "sales-by-treatment") {
            $fromDate = Carbon::parse($request->input("start"))->toDateString();
            $toDate = Carbon::parse($request->input("end"))->toDateString();
            $isByTreatment = $request->input("variant") == "sales-by-treatment";

            $salesQuery = SalesRecord::join('sales', 'sales_records.sales_id', '=', 'sales.id')
                ->join('treatments', 'sales_records.treatment_id', '=', 'treatments.id')
                ->whereBetween('sales.date', [$fromDate, $toDate])
                ->selectRaw("
                    " . ($isByTreatment ? "" : "sales.date,") . "
                    treatments.name as treatment,
                    sales_records.treatment_id,
                    SUM(IF(redeem_type='voucher', quantity, 0)) as voucher_quantity,
                    SUM(IF(redeem_type='voucher', total_price, 0)) as voucher_price,
                    SUM(IF(redeem_type='walkin', quantity, 0)) as walkin_quantity,
                    SUM(IF(redeem_type='walkin', total_price, 0)) as walkin_price,
                    SUM(quantity) as total_quantity,
                    SUM(total_price) as total_price
                ");

            if ($isByTreatment) {
                $salesQuery->groupBy('sales_records.treatment_id', 'treatments.name');
            } else {
                $salesQuery->groupBy('sales.date', 'sales_records.treatment_id', 'treatments.name');
            }
            $sales = $salesQuery->get();

            $usageQuery = Session::join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
                ->whereBetween('sessions.date', [$fromDate, $toDate])
                ->selectRaw("
                    " . ($isByTreatment ? "" : "sessions.date,") . "
                    sessions.treatment_id,
                    SUM(IF(payment='voucher', 1, 0)) as voucher_usage
                ");

            if ($isByTreatment) {
                $usageQuery->groupBy('sessions.treatment_id');
            } else {
                $usageQuery->groupBy('sessions.date', 'sessions.treatment_id');
            }
            $usages = $usageQuery->get();

            $results = collect();
            
            // Map sales
            foreach ($sales as $sale) {
                $key = $isByTreatment ? $sale->treatment_id : $sale->date . '_' . $sale->treatment_id;
                $data = $sale->toArray();
                $data['voucher_usage'] = 0; // Initialize
                $results->put($key, $data);
            }

            // Map usages
            foreach ($usages as $usage) {
                $key = $isByTreatment ? $usage->treatment_id : $usage->date . '_' . $usage->treatment_id;
                if ($results->has($key)) {
                    $item = $results->get($key);
                    $item['voucher_usage'] = $usage->voucher_usage;
                    $results->put($key, $item);
                } else {
                    $treatment = Treatment::find($usage->treatment_id);
                    $item = [
                        'date' => $isByTreatment ? null : $usage->date,
                        'treatment' => $treatment ? $treatment->name : $usage->treatment_id,
                        'voucher_quantity' => 0,
                        'voucher_price' => 0,
                        'walkin_quantity' => 0,
                        'walkin_price' => 0,
                        'voucher_usage' => $usage->voucher_usage,
                        'total_quantity' => 0,
                        'total_price' => 0,
                    ];
                    
                    $results->put($key, $item);
                }
            }

            return $results->values();
        } else return Voucher::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $treatment = $request->input("treatment_id");
        $voucherStart = $request->input("start");
        $start = (int)explode($treatment, $voucherStart)[1];
        $voucherEnd = $request->input("end");
        $end = (int)explode($treatment, $voucherEnd)[1];
        $date = date("Y-m-d");
        $time = date("H:i:s");
        $treatmentInfo = Treatment::where("id", $treatment);
        $existingVouchers = Voucher::whereBetween("id", [$voucherStart, $voucherEnd])->get();
        
        if ($treatmentInfo->first()) {
            if ($existingVouchers->count() > 0) {
                return response()->json($existingVouchers, 200);
            } else {
                $voucher = collect();
                for ($i=$start; $i <= $end; $i++) {
                    $voucherCode = $treatment.sprintf('%06d', $i);
                    $voucher = Voucher::create([
                        "id" => $voucherCode,
                        "treatment_id" => $treatmentInfo->first()->id,
                        "register_date" => $date,
                        "register_time" => $time,
                    ]);
                    $voucher->push($voucher);
                }
                return response()->json($voucher, 201);
            }
        } else {
            return response()->json([
                "message" => "Treatment not found"
            ], 500);
        }
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
                'voucher.*', 
                'sales.income_id', 'sales.date AS sales_date', 
                'sessions.id', 'sessions.date AS session_date',
                'employees.name'
            )->leftJoin('sales', 'sales.id', '=', 'voucher.sales_id')
            ->leftJoin('sessions', 'sessions.id', '=', 'voucher.session_id')
            ->leftJoin('employees', 'employees.id', '=', 'sessions.employee_id')
            ->whereBetween("voucher.id", [$id, $voucherEnd])->get();
        } else {
            $voucher = Voucher::leftJoin('sales', 'sales.id', '=', 'voucher.sales_id')
                ->leftJoin('sessions', 'sessions.id', '=', 'voucher.session_id')
                ->leftJoin('incomes', 'incomes.id', '=', 'sales.income_id')
                ->leftJoin('employees', 'employees.id', '=', 'sessions.employee_id')
                ->select(
                    'voucher.*', 
                    'sessions.date AS session_date', 
                    'incomes.journal_reference', 
                    'employees.name AS therapist_name'
                )
                ->findOrFail($id);

            if (!$voucher) return response()->json(['message' => 'Not found'], 404);

            return [
                'amount'        => $voucher->amount,
                'id'            => $voucher->id,
                'customer_id'   => $voucher->customer_id,
                'treatment_id'  => $voucher->treatment_id,
                'register_date' => $voucher->register_date,
                'sales_info'    => ($voucher->sales_id > 0) 
                    ? "Date : " . date('d-m-Y', strtotime($voucher->purchase_date)) . "\n" .
                    "Income Reference : " . $voucher->journal_reference
                    : "-------",
                'usage_info'    => ($voucher->session_id > 0)
                    ? "ID : " . $voucher->session_id . "\n" .
                    "Date : " . date('d-m-Y', strtotime($voucher->session_date)) . "\n" .
                    "Therapist : " . $voucher->therapist_name
                    : "-------",
            ];
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