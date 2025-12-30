<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Session;
use App\Models\Sales;
use App\Models\Treatment;
use App\Models\Customer;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\SalesRecord;
use App\Models\IncomePayment;
use App\Models\ExpensePayment;
use App\Models\Voucher;
use App\Models\Walkin;
use App\Models\Bonus;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected function employee_metadata(Employee $employee, $date, $profit_year)
    {
        $metadata = array(
            "active_sessions" => $employee->sessions()->where('date', $date)->where('status', 'ongoing')->count(),
            "completed_sessions" => 0,
            "sessions_improve" => 0,
            "today_commision" => 0,
            "hot_treatment" => $employee->sessions()
                ->join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
                ->whereMonth('date', date('m', strtotime($date)))
                ->whereYear('date', date('Y', strtotime($date)))
                ->groupBy('treatments.name')
                ->orderByRaw('COUNT(*) DESC')
                ->value('treatments.name'),
            "completed_guests" => $employee->sessions()
                ->whereMonth('date', date('m', strtotime($date)))
                ->whereYear('date', date('Y', strtotime($date)))->count(),
            "current_commision" => 0,
            "current_deduction" => 0,
            "uncontacted" => $employee->sessions()
                ->join('customers', 'sessions.customer_id', '=', 'customers.id')
                ->where('customer_id','>',5)->whereNotNull('customers.mobile')
                ->groupBy('customers.id', 'customers.name', 'customers.gender', 'customers.mobile', 'sessions.date', 'sessions.employee_id')
                ->orderByRaw('MAX(sessions.date) DESC')
                ->select('customers.id', 'customers.name', 'customers.gender', 'customers.mobile', 'sessions.date', 'sessions.employee_id')->get()
        );

        $today_sessions = $employee->sessions()->where('date', $date)->where('status', 'completed')->count();
        $yesterday_sessions = $employee->sessions()->where('date', date("Y-m-d", strtotime("yesterday")))->where('status', 'completed')->count();
        $metadata["completed_sessions"] = $today_sessions;
        $metadata["sessions_improved"] = $yesterday_sessions;

        $today_commision = $employee->sessions()
            ->leftJoin('voucher', 'sessions.id', '=', 'voucher.session_id')
            ->leftJoin('walkin', 'sessions.id', '=', 'walkin.session_id')
            ->leftJoin('sales', 'walkin.sales_id', '=', 'sales.id')
            ->join('grades', function($join) use ($date) {
                $join->on('sessions.employee_id', '=', 'grades.employee_id')
                    ->where('grades.start_date', '<=', $date)
                    ->where(function($query) use ($date) {
                        $query->where('grades.end_date', '>=', $date)
                            ->orWhereNull('grades.end_date');
                    });
            })
            ->join('bonus', function($join) {
                $join->on('grades.grade', '=', 'bonus.grade')
                    ->on('bonus.treatment_id', '=', 'sessions.treatment_id');
            })
            ->join('treatments', 'bonus.treatment_id', '=', 'treatments.id')
            ->where('sessions.date', $date)
            ->sum('bonus.gross_bonus');

        $metadata["today_commision"] = $today_commision;

        $current_commision = $employee->sessions()
            ->leftJoin('voucher', 'sessions.id', '=', 'voucher.session_id')
            ->leftJoin('walkin', 'sessions.id', '=', 'walkin.session_id')
            ->leftJoin('sales', 'walkin.sales_id', '=', 'sales.id')
            ->join('grades', function($join) use ($date) {
                $join->on('sessions.employee_id', '=', 'grades.employee_id')
                    ->where('grades.start_date', '<=', $date)
                    ->where(function($query) use ($date) {
                        $query->where('grades.end_date', '>=', $date)
                            ->orWhereNull('grades.end_date');
                    });
            })
            ->join('bonus', function($join) {
                $join->on('grades.grade', '=', 'bonus.grade')
                    ->on('bonus.treatment_id', '=', 'sessions.treatment_id');
            })
            ->join('treatments', 'bonus.treatment_id', '=', 'treatments.id')
            ->whereMonth('sessions.date', date('m', strtotime($date)))
            ->whereYear('sessions.date', date('Y', strtotime($date)))
            ->sum('bonus.gross_bonus');

        $metadata["current_commision"] = $current_commision;

        $monthly_commision = $employee->sessions()
            ->leftJoin('voucher', 'sessions.id', '=', 'voucher.session_id')
            ->leftJoin('walkin', 'sessions.id', '=', 'walkin.session_id')
            ->leftJoin('sales', 'walkin.sales_id', '=', 'sales.id')
            ->join('grades', function($join) {
                $join->on('sessions.employee_id', '=', 'grades.employee_id')
                    ->whereRaw('grades.start_date <= sessions.date')
                    ->where(function($query) {
                        $query->whereRaw('grades.end_date >= sessions.date')
                            ->orWhereNull('grades.end_date');
                    });
            })
            ->join('bonus', function($join) {
                $join->on('grades.grade', '=', 'bonus.grade')
                    ->on('bonus.treatment_id', '=', 'sessions.treatment_id');
            })
            ->join('treatments', 'bonus.treatment_id', '=', 'treatments.id')
            ->whereYear('sessions.date', $profit_year)
            ->selectRaw('MONTH(sessions.date) as month, SUM(bonus.gross_bonus) as income')
            ->groupBy('month')->get();

        $metadata["monthly_commision"] = $monthly_commision;

        $current_deduction = $employee->attendance()
            ->with(['shift', 'employee'])
            ->whereMonth('date', date('m', strtotime($date)))
            ->whereYear('date', date('Y', strtotime($date)))
            ->get()->sum('deduction');

        $metadata["current_deduction"] = $current_deduction;

        $monthly_attendance = $employee->attendance()
            ->with(['shift', 'employee'])
            ->whereYear('date', $profit_year)
            ->get()
            ->groupBy(function($attendance) {
                // Group by month (e.g., "01", "02")
                return date('m', strtotime($attendance->date));
            })
            ->map(function ($monthAttendances, $key) {
                return (object)array("month"=>intval($key), "expense"=>$monthAttendances->sum('deduction'));
            });

        $metadata["monthly_attendance"] = $monthly_attendance->flatten(1)->toArray();

        return $metadata;
    }

    protected function admin_metadata($date, $profit_year)
    {
        $metadata = array(
            "active_sessions" => Session::where('date', $date)->where('status', 'ongoing')->count(),
            "completed_sessions" => 0,
            "sessions_improve" => 0,
            "today_sales" => Sales::where('date', $date)->where('income_id', '>', 0)->sum('total'),
            "hot_treatment" => Session::join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
                ->whereMonth('date', date('m', strtotime($date)))
                ->whereYear('date', date('Y', strtotime($date)))
                ->groupBy('treatments.name')
                ->orderByRaw('COUNT(*) DESC')
                ->value('treatments.name'),
            "hot_therapist" => Session::join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
                ->join('employees', 'sessions.employee_id', '=', 'employees.id')
                ->whereMonth('date', date('m', strtotime($date)))
                ->whereYear('date', date('Y', strtotime($date)))
                ->groupBy('employee_id')
                ->orderByRaw('COUNT(*) DESC')
                ->value('employees.complete_name'),
            "voucher_sold" => 0,
            "voucher_improve" => 0,
            "monthly_income" => IncomePayment::join('incomes', 'income_payments.income_id', '=', 'incomes.id')
                ->whereYear('incomes.date', $profit_year)
                ->selectRaw('MONTH(incomes.date) as month, SUM(income_payments.amount) as income')
                ->groupBy('month')->get(),
            "monthly_expense" => ExpensePayment::join('expenses', 'expense_payments.expense_id', '=', 'expenses.id')
                ->whereNotLike('expense_payments.description', 'Profit%')->whereYear('expenses.date', $profit_year)
                ->selectRaw('MONTH(expenses.date) as month, SUM(expense_payments.amount) as expense')
                ->groupBy('month')->get()
        );

        $today_sessions = Session::where('date', $date)->where('status', 'completed')->count();
        $yesterday_sessions = Session::where('date', date("Y-m-d", strtotime("yesterday")))->where('status', 'completed')->count();
        $metadata["completed_sessions"] = $today_sessions;
        $metadata["sessions_improved"] = $today_sessions - $yesterday_sessions;

        $vouchersSold = SalesRecord::join('sales', 'sales_records.sales_id', '=', 'sales.id')
            ->join('treatments', 'sales_records.treatment_id', '=', 'treatments.id')
            ->where('sales_records.redeem_type', 'voucher')
            ->selectRaw('MONTH(sales.date) as month, YEAR(sales.date) as year, COUNT(*) as vouchers')
            ->groupByRaw('YEAR(sales.date), MONTH(sales.date)')
            ->orderByRaw('year DESC, month DESC')
            ->get();

        if ($vouchersSold[0]["year"]==date("Y") && $vouchersSold[0]["month"]==date("m")) {
            $metadata["voucher_sold"] = $vouchersSold[0]["vouchers"];
            $metadata["voucher_improve"] = $vouchersSold[0]["vouchers"]-$vouchersSold[1]["vouchers"];
        } else {
            $metadata["voucher_improve"] = 0-$vouchersSold[0]["vouchers"];
        }

        $metadata["today"] = Employee::getDailyReport($date);

        return $metadata;
    }

    protected function branch_metadata($branch, $date, $profit_year)
    {
        $metadata = array(
            "active_sessions" => $branch->employee()->join('sessions', 'employees.id', '=', 'sessions.employee_id')->where('date', $date)->where('sessions.status', 'ongoing')->count(),
            "completed_sessions" => 0,
            "sessions_improve" => 0,
            "today_sales" => $branch->sales()->where('date', $date)->where('income_id', '>', 0)->sum('total'),
            "hot_treatment" => $branch->employee()->join('sessions', 'employees.id', '=', 'sessions.employee_id')
                ->join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
                ->whereMonth('date', date('m', strtotime($date)))
                ->whereYear('date', date('Y', strtotime($date)))
                ->groupBy('treatments.name')
                ->orderByRaw('COUNT(*) DESC')
                ->value('treatments.name'),
            "hot_therapist" => $branch->employee()->join('sessions', 'employees.id', '=', 'sessions.employee_id')
                ->whereMonth('date', date('m', strtotime($date)))
                ->whereYear('date', date('Y', strtotime($date)))
                ->groupBy('employee_id')
                ->orderByRaw('COUNT(*) DESC')
                ->value('complete_name'),
            "voucher_sold" => 0,
            "voucher_improve" => 0,
            "monthly_income" => $branch->sales()
                ->join('incomes', 'sales.income_id', '=', 'incomes.id')
                ->join('income_payments', 'income_payments.income_id', '=', 'incomes.id')
                ->selectRaw('MONTH(incomes.date) as month, SUM(income_payments.amount) as amount')
                ->whereYear('incomes.date', $profit_year)
                ->groupBy('month')->get(),
            "uncontacted" => $branch->employee()
                ->join('sessions', 'employees.id', '=', 'sessions.employee_id')
                ->join('customers', 'sessions.customer_id', '=', 'customers.id')
                ->where('customer_id','>',5)->whereNotNull('customers.mobile')
                ->groupBy('customer_id')
                ->value('customers.id', 'customers.name', 'customers.gender', 'customers.mobile', 'sessions.date', 'sessions.employee_id')
        );

        $today_sessions = $branch->employee()->join('sessions', 'employees.id', '=', 'sessions.employee_id')->where('date', $date)->where('sessions.status', 'completed')->count();
        $yesterday_sessions = $branch->employee()->join('sessions', 'employees.id', '=', 'sessions.employee_id')->where('date', date("Y-m-d", strtotime("yesterday")))->where('sessions.status', 'completed')->count();
        $metadata["completed_sessions"] = $today_sessions;
        $metadata["sessions_improved"] = $today_sessions - $yesterday_sessions;

        $vouchersSold = $branch->sales()->join('sales_records', 'sales.id', '=', 'sales_records.sales_id')->where('redeem_type', 'voucher')
            ->selectRaw('MONTH(sales.date) as month, YEAR(sales.date) as year, COUNT(*) as vouchers')
            ->groupByRaw('YEAR(sales.date), MONTH(sales.date)')
            ->orderByRaw('year DESC, month DESC')
            ->get();

        if ($vouchersSold[0]["year"]==date("Y") && $vouchersSold[0]["month"]==date("m")) {
            $metadata["voucher_sold"] = $vouchersSold[0]["vouchers"];
            $metadata["voucher_improve"] = $vouchersSold[0]["vouchers"]-$vouchersSold[1]["vouchers"];
        } else {
            $metadata["voucher_improve"] = 0-$vouchersSold[0]["vouchers"];
        }

        $metadata["today"] = Employee::getDailyReport($date, $branch->id);

        return $metadata;
    }

    protected function sortArray(&$contents, $props) {
        usort($contents, function($b, $a) use ($props) {
            if($a[$props[0]] == $b[$props[0]])
                return $a[$props[1]] < $b[$props[1]] ? 1 : -1;
            return $a[$props[0]] < $b[$props[0]] ? 1 : -1;
        });
    }
    public function daily(Request $request)
    {
        $date = ($request->input("job_date")) ? $request->input("job_date") : date('Y-m-d');
        $branch = $request->input("branch");
        if (isset($branch)) {
            return Employee::getDailyReport($date, $branch);
        } else {
            return Employee::getDailyReport($date);
        }
    }

    public function dashboard(Request $request)
    {
        $employee = $request->input("employee_id");
        $branch = $request->input("branch_id");
        $profit_year = $request->input("profit_year");
        
        $date = ($request->input("job_date")) ? $request->input("job_date") : date('Y-m-d');
        if (isset($employee)) {
            return $this->employee_metadata(Employee::find($employee), $date, $profit_year);
        } else if (isset($branch)) {
            return $this->branch_metadata(Branch::find($branch), $date, $profit_year);
        } else {
            return $this->admin_metadata($date, $profit_year);
        }
    }
}
