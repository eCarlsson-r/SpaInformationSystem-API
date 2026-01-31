<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Session;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\Walkin;
use App\Models\Voucher;
use App\Notifications\SessionMade;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->input("variant") == "walkin-voucher-usage") {
            $fromDate = Carbon::parse($request->input("start"))->toDateString();
            $toDate = Carbon::parse($request->input("end"))->toDateString();
            
            return Session::join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
                ->join('employees', 'sessions.employee_id', '=', 'employees.id')
                ->leftJoin('voucher', 'sessions.id', '=', 'voucher.session_id')
                ->leftJoin('walkin', 'sessions.id', '=', 'walkin.session_id')
                ->leftJoin('sales', 'walkin.sales_id', '=', 'sales.id')
                ->leftJoin('sales_records', function($join) {
                    $join->on('sales.id', '=', 'sales_records.sales_id')
                         ->on('sales_records.treatment_id', '=', 'sessions.treatment_id');
                })
                ->leftJoin('incomes', 'sales.income_id', '=', 'incomes.id')
                ->selectRaw("
                    sessions.date, 
                    sessions.start as time,
                    MAX(IF(sessions.payment = 'voucher', voucher.id, incomes.journal_reference)) as `reference`,
                    employees.name as `therapist_name`,
                    treatments.name as description,
                    MAX(IF(sessions.payment = 'voucher', voucher.amount, ROUND((sales_records.price - (COALESCE(sales_records.discount, 0)))/1000)*1000)) as `price`
                ")
                ->whereBetween('sessions.date', [$fromDate, $toDate])
                ->whereIn('sessions.status', ['completed', 'ongoing'])
                ->where(function($query) {
                    $query->where('voucher.amount', '>', 0)
                          ->orWhere('sales_records.price', '>', 0);
                })
                ->groupBy('sessions.id', 'sessions.date', 'sessions.start', 'employees.name', 'treatments.name')
                ->orderBy('sessions.id')
                ->get();
        } else if (auth()->user()->customer) {
            return Session::with('treatment', 'employee', 'bed', 'bed.room.branch')
                ->where('sessions.customer_id', auth()->user()->customer->id)
                ->where('sessions.status', 'waiting')
                ->get();
        } else {
            $sessions = Session::whereIn('sessions.status', json_decode($request->input('status')))
                ->join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
                ->join('customers', 'sessions.customer_id', '=', 'customers.id')
                ->join('employees', 'sessions.employee_id', '=', 'employees.id')
                ->join('beds', 'sessions.bed_id', '=', 'beds.id')
                ->leftJoin('walkin', 'walkin.session_id', '=', 'sessions.id')
                ->leftJoin('voucher', 'voucher.session_id', '=', 'sessions.id')
                ->leftJoin('sales', 'sales.id', '=', 'walkin.sales_id')
                ->leftJoin('incomes', 'incomes.id', '=', 'sales.income_id')
                ->select(
                    'sessions.*',
                    'customers.name AS customer_name', 
                    'treatments.name AS treatment_name', 
                    'treatments.duration AS treatment_duration', 
                    'employees.name AS therapist_name', 
                    'beds.name AS bed_name',
                    'walkin.id AS walkin_id',
                    'voucher.id AS voucher_id',
                    'incomes.journal_reference AS reference'
                );
            
            if ($request->input('branch_id')) {
                return $sessions->where('sessions.branch_id', $request->input('branch_id'))->get();
            } else if ($request->input('employee_id')) {
                return $sessions->where('sessions.employee_id', $request->input('employee_id'))->get();
            } else if ($request->input("start") && $request->input("end") && $request->input("from_employee") && $request->input("to_employee") && $request->input("order_by")) {
                return $sessions->whereBetween('sessions.date', [
                    Carbon::parse($request->input("start"))->toDateString(), 
                    Carbon::parse($request->input("end"))->toDateString()
                ])
                    ->whereBetween('sessions.employee_id', [$request->input("from_employee"), $request->input("to_employee")])
                    ->orderBy($request->input("order_by"))
                    ->get();
            } else {
                return $sessions->get();
            }
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $startDate = explode("T", $request->start);

        $session = Session::create([
            'order_time' => date('H:i:s'),
            'bed_id' => $request->bed_id,
            'customer_id' => $request->customer_id,
            'payment' => $request->payment,
            'date' => (isset($request->start)) ? $startDate[0] : null,
            'start' => (isset($request->start)) ? $startDate[1] : null,
            'employee_id' => $request->employee_id,
            'treatment_id' => $request->treatment_id,
            'status' => (isset($request->start)) ? 'ongoing' : 'waiting'
        ]);

        if (isset($request->voucher_id)) {
            $updateVoucher = Voucher::updateOrCreate(
                ['id' => $request->voucher_id],
                ['session_id' => $session->id]
            );

            $voucher = Voucher::find($request->voucher_id);

            $journal = Journal::where('reference', $voucher->sales->income->journal_reference)->first();

            $journal->records()->create([
                "account_id" => $voucher->sales->branch->voucher_usage_account,
                "debit" => $voucher->amount,
                "credit" => 0,
                "description" => "Treat Sess [{$session->id}, {$session->customer->name}, Voucher No. {$request->voucher_id}]"
            ]);
        } else {
            $updateWalkin = Walkin::updateOrCreate(
                ['id' => $request->walkin_id],
                ['session_id' => $session->id]
            );
        }
        
        if ($session) {
            $employee = Employee::find($request->employee_id)->user;
            if ($employee) $employee->notify(new SessionMade($session));
            $customer = Customer::find($request->customer_id)->user;
            if ($customer) $customer->notify(new SessionMade($session));
            $admin = User::find(1);
            if ($admin) $admin->notify(new SessionMade($session));
            return response()->json($session, 201);
        } else {
            return response()->json(['message' => 'Failed to create session'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Session::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Session $session)
    {
        $session->update($request->all());
        
        if ($session) {
            return response()->json($session, 200);
        } else {
            return response()->json(['message' => 'Failed to update session'], 500);
        }
    }

    public function start(Session $session)
    {
        // We only update the status and record the start time
        $session->update([
            'status' => 'ongoing',
            'start' => now(),
        ]);

        if ($session) {
            return response()->json($session, 200);
        } else {
            return response()->json(['message' => 'Failed to start session'], 500);
        }
    }

    public function finish(Session $session)
    {
        $session->update([
            'status' => 'completed',
            'end' => now(),
        ]);

        if ($session) {
            return response()->json($session, 200);
        } else {
            return response()->json(['message' => 'Failed to finish session'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Session $session)
    {
        if ($session->delete()) {
            return response()->json(['message' => 'Session deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete session'], 500);
        }
    }
}
