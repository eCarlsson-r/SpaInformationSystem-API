<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
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

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Handle login logic here
        $credentials = $request->only('username', 'password');
        $user = User::where('username', $request->username)->first();
        $employee = Employee::where('user_id', $user->id)->first();

        if (auth()->attempt($credentials)) {
            if ($employee) return response()->json(['data' => $user, 'employee' => $employee], 200);
            else return response()->json(['data' => $user], 200);
        } else if (!$user) {
            return response()->json(['message' => 'No account exist with the given username.'], 401);
        } else if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password is incorrect.'], 401);
        } else {
            return response()->json(['message' => 'Username or Password is invalid'], 401);
        }
    }

    public function subscribe(Request $request)
    {
        $user = User::find($request->input('user_id'));
        $user->updatePushSubscription(
            $request->input('endpoint'), 
            $request->input('public_key'), 
            $request->input('auth_token'), 
            $request->input('content_encoding')
        );

        return response()->json(['success' => true]);
    }

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
                ->groupBy('customers.id', 'customers.name', 'customers.gender', 'customers.mobile', 'sessions.date', 'sessions.employee_id')->orderByRaw('MAX(sessions.date) DESC')
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
            ->join('grade', function($join) use ($date) {
                $join->on('sessions.employee_id', '=', 'grade.employee_id')
                    ->where('grade.start_date', '<=', $date)
                    ->where(function($query) use ($date) {
                        $query->where('grade.end_date', '>=', $date)
                            ->orWhereNull('grade.end_date');
                    });
            })
            ->join('bonus', function($join) {
                $join->on('grade.grade', '=', 'bonus.grade')
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
            ->join('grade', function($join) use ($date) {
                $join->on('sessions.employee_id', '=', 'grade.employee_id')
                    ->where('grade.start_date', '<=', $date)
                    ->where(function($query) use ($date) {
                        $query->where('grade.end_date', '>=', $date)
                            ->orWhereNull('grade.end_date');
                    });
            })
            ->join('bonus', function($join) {
                $join->on('grade.grade', '=', 'bonus.grade')
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
            ->join('grade', function($join) {
                $join->on('sessions.employee_id', '=', 'grade.employee_id')
                    ->whereRaw('grade.start_date <= sessions.date')
                    ->where(function($query) {
                        $query->whereRaw('grade.end_date >= sessions.date')
                            ->orWhereNull('grade.end_date');
                    });
            })
            ->join('bonus', function($join) {
                $join->on('grade.grade', '=', 'bonus.grade')
                    ->on('bonus.treatment_id', '=', 'sessions.treatment_id');
            })
            ->join('treatments', 'bonus.treatment_id', '=', 'treatments.id')
            ->whereYear('sessions.date', $profit_year)
            ->select('sessions.*', 'bonus.gross_bonus')
            ->get()
            ->groupBy(function($session) {
                return date('m', strtotime($session->date));
            })
            ->map(function ($monthSessions) {
                return $monthSessions->sum('gross_bonus');
            });

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
            ->map(function ($monthAttendances) {
                return $monthAttendances->sum('deduction');
            });

        $metadata["monthly_attendance"] = $monthly_attendance;

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
            "monthly_income" => IncomePayment::join('income', 'income_payments.income_id', '=', 'income.id')
                ->whereYear('income.date', $profit_year)
                ->selectRaw('MONTH(income.date) as month, SUM(income_payments.amount) as amount')
                ->groupBy('month')->get(),
            "monthly_expense" => ExpensePayment::join('expense', 'expense_payments.expense_id', '=', 'expense.id')
                ->whereLike('expense_payments.description', 'Profit%')->whereYear('date', $profit_year)
                ->selectRaw('MONTH(expense.date) as month, SUM(expense_payments.amount) as amount')
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

        $sessionCountSub = Employee::select('employees.id as employee_id')
            ->selectRaw('COALESCE(COUNT(DISTINCT sessions.customer_id, sessions.date), 0) as session_count')
            ->join('grade', function($join) use ($date) {
                $join->on('employees.id', '=', 'grade.employee_id')
                    ->where('grade.start_date', '<=', $date)
                    ->where(function($query) use ($date) {
                        $query->where('grade.end_date', '>=', $date)
                            ->orWhereNull('grade.end_date');
                    });
            })
            ->join('sessions', 'sessions.employee_id', '=', 'employees.id')
            ->join('bonus', function($join) {
                $join->on('grade.grade', '=', 'bonus.grade')
                    ->on('bonus.treatment_id', '=', 'sessions.treatment_id');
            })
            ->join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
            ->whereBetween('sessions.date', [
                date('Y-m-d', strtotime("last day of previous month", strtotime($date))),
                $date
            ])
            ->groupBy('employees.id');

        $today = Employee::select(
            'employees.complete_name',
            'employees.nickname as emp_nickname',
            DB::raw("COALESCE(ss.session_count, 0) as session_count"),
            DB::raw("IF(attendance.shift_id='OFF', 'OFF', attendance.clock_in) as clock_in"),
            DB::raw("COUNT(DISTINCT CASE WHEN sessions.status='completed' THEN sessions.customer_id END) as completed_sessions"),
            DB::raw("COUNT(DISTINCT CASE WHEN sessions.status='ongoing' THEN sessions.customer_id END) as ongoing_sessions"),
            DB::raw("
                IF(attendance.shift_id!='OFF' AND attendance.shift_id!='L' AND SUBTIME(attendance.clock_in, shifts.start_time)>0 AND HOUR(SUBTIME(attendance.clock_in, shifts.start_time))>1, 2*employees.late_deduction,
                    IF(attendance.shift_id!='OFF' AND attendance.shift_id!='L' AND SUBTIME(attendance.clock_in, shifts.start_time)>0 AND (HOUR(SUBTIME(attendance.clock_in, shifts.start_time))>0 OR MINUTE(SUBTIME(attendance.clock_in, shifts.start_time))>5), (HOUR(SUBTIME(attendance.clock_in, shifts.start_time))+1)*employees.late_deduction, 0)
                ) +
                IF(((attendance.shift_id='M' OR attendance.shift_id='N') AND HOUR(SUBTIME(attendance.clock_out, shifts.end_time))>=1) OR ((attendance.shift_id='A' OR attendance.shift_id='D') AND HOUR(SUBTIME(attendance.clock_out, shifts.end_time))>1), employees.late_deduction, 0) +
                IF(attendance.shift_id!='OFF' AND attendance.shift_id!='L' AND attendance.clock_in IS NULL AND attendance.date <= DATE_SUB(CURDATE(), INTERVAL 1 DAY), IF(WEEKDAY(attendance.date)>4, employees.absent_deduction*2, employees.absent_deduction), 0)
                as emp_deduction
            ")
        )
        ->join('grade', function($join) use ($date) {
            $join->on('employees.id', '=', 'grade.employee_id')
                ->where('grade.start_date', '<=', $date)
                ->where(function($query) use ($date) {
                    $query->where('grade.end_date', '>=', $date)
                        ->orWhereNull('grade.end_date');
                });
        })
        ->join('attendance', function($join) use ($date) {
            $join->on('attendance.employee_id', '=', 'employees.id')
                ->where('attendance.date', '=', $date);
        })
        ->leftJoin('shifts', 'attendance.shift_id', '=', 'shifts.id')
        ->leftJoin('sessions', function($join) use ($date) {
            $join->on('employees.id', '=', 'sessions.employee_id')
                ->where('sessions.date', '=', $date);
        })
        ->leftJoinSub($sessionCountSub, 'ss', function($join) {
            $join->on('employees.id', '=', 'ss.employee_id');
        })
        ->where('grade.grade', '<>', 'K')
        ->where('attendance.date', $date)
        ->groupBy('employees.id', 'attendance.id', 'shifts.id', 'ss.session_count')
        ->orderByRaw('-attendance.clock_in DESC')
        ->get();
        
        $metadata["today"] = $today;

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
                ->join('income', 'sales.income_id', '=', 'income.id')
                ->join('income_payments', 'income_payments.income_id', '=', 'income.id')
                ->selectRaw('MONTH(income.date) as month, SUM(income_payments.amount) as amount')
                ->whereYear('income.date', $profit_year)
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

        $sessionCountSub = $branch->employee()->select('employees.id as employee_id')
            ->selectRaw('COALESCE(COUNT(DISTINCT sessions.customer_id, sessions.date), 0) as session_count')
            ->join('grade', function($join) use ($date) {
                $join->on('employees.id', '=', 'grade.employee_id')
                    ->where('grade.start_date', '<=', $date)
                    ->where(function($query) use ($date) {
                        $query->where('grade.end_date', '>=', $date)
                            ->orWhereNull('grade.end_date');
                    });
            })
            ->join('sessions', 'sessions.employee_id', '=', 'employees.id')
            ->join('bonus', function($join) {
                $join->on('grade.grade', '=', 'bonus.grade')
                    ->on('bonus.treatment_id', '=', 'sessions.treatment_id');
            })
            ->join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
            ->whereBetween('sessions.date', [
                date('Y-m-d', strtotime("last day of previous month", strtotime($date))),
                $date
            ])
            ->groupBy('employees.id');

        $today = $branch->employee()->select(
            'employees.complete_name',
            'employees.nickname as emp_nickname',
            DB::raw("COALESCE(ss.session_count, 0) as session_count"),
            DB::raw("IF(attendance.shift_id='OFF', 'OFF', attendance.clock_in) as clock_in"),
            DB::raw("COUNT(DISTINCT CASE WHEN sessions.status='completed' THEN sessions.customer_id END) as completed_sessions"),
            DB::raw("COUNT(DISTINCT CASE WHEN sessions.status='ongoing' THEN sessions.customer_id END) as ongoing_sessions"),
            DB::raw("
                IF(attendance.shift_id!='OFF' AND attendance.shift_id!='L' AND SUBTIME(attendance.clock_in, shifts.start_time)>0 AND HOUR(SUBTIME(attendance.clock_in, shifts.start_time))>1, 2*employees.late_deduction,
                    IF(attendance.shift_id!='OFF' AND attendance.shift_id!='L' AND SUBTIME(attendance.clock_in, shifts.start_time)>0 AND (HOUR(SUBTIME(attendance.clock_in, shifts.start_time))>0 OR MINUTE(SUBTIME(attendance.clock_in, shifts.start_time))>5), (HOUR(SUBTIME(attendance.clock_in, shifts.start_time))+1)*employees.late_deduction, 0)
                ) +
                IF(((attendance.shift_id='M' OR attendance.shift_id='N') AND HOUR(SUBTIME(attendance.clock_out, shifts.end_time))>=1) OR ((attendance.shift_id='A' OR attendance.shift_id='D') AND HOUR(SUBTIME(attendance.clock_out, shifts.end_time))>1), employees.late_deduction, 0) +
                IF(attendance.shift_id!='OFF' AND attendance.shift_id!='L' AND attendance.clock_in IS NULL AND attendance.date <= DATE_SUB(CURDATE(), INTERVAL 1 DAY), IF(WEEKDAY(attendance.date)>4, employees.absent_deduction*2, employees.absent_deduction), 0)
                as emp_deduction
            ")
        )
        ->join('grade', function($join) use ($date) {
            $join->on('employees.id', '=', 'grade.employee_id')
                ->where('grade.start_date', '<=', $date)
                ->where(function($query) use ($date) {
                    $query->where('grade.end_date', '>=', $date)
                        ->orWhereNull('grade.end_date');
                });
        })
        ->join('attendance', function($join) use ($date) {
            $join->on('attendance.employee_id', '=', 'employees.id')
                ->where('attendance.date', '=', $date);
        })
        ->leftJoin('shifts', 'attendance.shift_id', '=', 'shifts.id')
        ->leftJoin('sessions', function($join) use ($date) {
            $join->on('employees.id', '=', 'sessions.employee_id')
                ->where('sessions.date', '=', $date);
        })
        ->leftJoinSub($sessionCountSub, 'ss', function($join) {
            $join->on('employees.id', '=', 'ss.employee_id');
        })
        ->where('grade.grade', '<>', 'K')
        ->where('attendance.date', $date)
        ->groupBy('employees.id', 'attendance.id', 'shifts.id', 'ss.session_count')
        ->orderByRaw('-attendance.clock_in DESC')
        ->get();
        
        $metadata["today"] = $today;

        return $metadata;
    }

    public function dashboard(Request $request)
    {
        $employee = $request->input("employee");
        $branch = $request->input("branch");
        $profit_year = $request->input("profit_year");
        
        $date = '2021-03-08';//date('Y-m-d');
        if (isset($employee)) {
            return $this->employee_metadata(Employee::find($employee), $date, $profit_year);
        } else if (isset($branch)) {
            return $this->branch_metadata(Branch::find($branch), $date, $profit_year);
        } else {
            return $this->admin_metadata($date, $profit_year);
        }
    }
}
