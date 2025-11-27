<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Employee;
use App\Models\Session;

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

    protected function employee_metadata(String $employee, $date)
    {
        $employee = Employee::find($employee);
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
                ->value('treatments.name')->first(),
            "completed_guests" => $employee->sessions()
                ->whereMonth('date', date('m', strtotime($date)))
                ->whereYear('date', date('Y', strtotime($date)))->count(),
            "current_commision" => 0,
            "current_deduction" => 0,
            "uncontacted" => $employee->session()
                ->join('customer', 'sessions.customer_id', '=', 'customer.id')
                ->where('customer_id','>',5)->where('customer.mobile', '<>', '')
                ->groupBy('customer_id')->orderBy('date', 'desc')->get()
        );

        $today_sessions = $employee->sessions()->where('date', $date)->where('status', 'completed')->count();
        $yesterday_sessions = $employee->sessions()->where('date', date("Y-m-d", strtotime("yesterday")))->where('status', 'completed')->count();
        $metadata["completed_sessions"] = $today_sessions;
        $metadata["sessions_improved"] = $yesterday_sessions;

        $today_commision = $employee->sessions()
            ->leftJoin('voucher', 'sessions.id', '=', 'voucher.session_id')
            ->leftJoin('walkin', 'sessions.id', '=', 'walkin.session_id')
            ->leftJoin('sales', 'walkin.sales_id', '=', 'sales.id')
            ->join('grade', function($join) {
                $join->on('employees.id', '=', 'grade.employee_id')
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
            ->join('grade', function($join) {
                $join->on('employees.id', '=', 'grade.employee_id')
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
            ->whereMonth('date', date('m', strtotime($date)))
            ->whereYear('date', date('Y', strtotime($date)))
            ->sum('bonus.gross_bonus');

        $metadata["current_commision"] = $current_commision;

        $current_deduction = $employee->attendance()
            ->whereMonth('date', date('m', strtotime($date)))
            ->whereYear('date', date('Y', strtotime($date)))
            ->sum('deduction');

        $metadata["current_deduction"] = $current_deduction;

        return $metadata;
    }

    protected function admin_metadata($date)
    {
        $metadata = array(
            "active_sessions" => Session::where('date', $date)->where('status', 'ongoing')->count(),
            "completed_sessions" => 0,
            "sessions_improve" => 0,
            "today_commision" => 0,
            "hot_treatment" => Session::join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
                ->whereMonth('date', date('m', strtotime($date)))
                ->whereYear('date', date('Y', strtotime($date)))
                ->groupBy('treatments.name')
                ->orderByRaw('COUNT(*) DESC')
                ->value('treatments.name')->first(),
            "completed_guests" => $employee->sessions()
                ->whereMonth('date', date('m', strtotime($date)))
                ->whereYear('date', date('Y', strtotime($date)))->count(),
            "current_commision" => 0,
            "current_deduction" => 0,
            "uncontacted" => $employee->session()
                ->join('customer', 'sessions.customer_id', '=', 'customer.id')
                ->where('customer_id','>',5)->where('customer.mobile', '<>', '')
                ->groupBy('customer_id')->orderBy('date', 'desc')->get()
        );

        $employee = Employee::find($employee);

        $today_sessions = $employee->sessions()->where('date', $date)->where('status', 'completed')->count();
        $yesterday_sessions = $employee->sessions()->where('date', date("Y-m-d", strtotime("yesterday")))->where('status', 'completed')->count();
        $metadata["completed_sessions"] = $today_sessions;
        $metadata["sessions_improved"] = $yesterday_sessions;

        $today_commision = $employee->sessions()
            ->leftJoin('voucher', 'sessions.id', '=', 'voucher.session_id')
            ->leftJoin('walkin', 'sessions.id', '=', 'walkin.session_id')
            ->leftJoin('sales', 'walkin.sales_id', '=', 'sales.id')
            ->join('grade', function($join) {
                $join->on('employees.id', '=', 'grade.employee_id')
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
            ->join('grade', function($join) {
                $join->on('employees.id', '=', 'grade.employee_id')
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
            ->whereMonth('date', date('m', strtotime($date)))
            ->whereYear('date', date('Y', strtotime($date)))
            ->sum('bonus.gross_bonus');

        $metadata["current_commision"] = $current_commision;

        $current_deduction = $employee->attendance()
            ->whereMonth('date', date('m', strtotime($date)))
            ->whereYear('date', date('Y', strtotime($date)))
            ->sum('deduction');

        $metadata["current_deduction"] = $current_deduction;

        return $metadata;
    }

    public function dashboard(Request $request)
    {
        $employee = $request->input("employee");
        $branch = $request->input("branch");
        $profit_year = $request->input("profit_year");
        $traffic_year = $request->input("traffic_year");
        
        $date = date('Y-m-d');
        $error = 0;
        $message = "";
        $dashboard = array();
        if (isset($employee)) {
            return employee_metadata($employee, $date);
        } else {
            /*if (isset($branch)) $vouchersSold = selectDB($connection,"MONTH(`sales-date`) AS `month`,YEAR(`sales-date`) AS `year`,COUNT(`sales-record-id`) AS `vouchers`", "`sales-record` AS sr INNER JOIN `sales` AS s ON sr.`sales-id`=s.`sales-id` INNER JOIN `treatment` AS t ON sr.`item-code`=t.`treatment-code`","`redeem-type`='voucher' GROUP BY YEAR(`sales-date`),MONTH(`sales-date`) ORDER BY YEAR(`sales-date`) DESC,MONTH(`sales-date`) DESC");
            else */$vouchersSold = selectDB($connection,"MONTH(`sales-date`) AS `month`,YEAR(`sales-date`) AS `year`,COUNT(`sales-record-id`) AS `vouchers`", "`sales-record` AS sr INNER JOIN `sales` AS s ON sr.`sales-id`=s.`sales-id` INNER JOIN `treatment` AS t ON sr.`item-code`=t.`treatment-code`","`redeem-type`='voucher' GROUP BY YEAR(`sales-date`),MONTH(`sales-date`) ORDER BY YEAR(`sales-date`) DESC,MONTH(`sales-date`) DESC");
            $dashboard["vouchers-sold"] = 0;
            $dashboard["voucher-improve"] = 0;
            
            if ($vouchersSold["err"] == 0) {
                if ($vouchersSold["data"][0]["year"]==date("Y") && $vouchersSold["data"][0]["month"]==date("m")) {
                    $dashboard["vouchers-sold"] = $vouchersSold["data"][0]["vouchers"];
                    $dashboard["voucher-improve"] = $vouchersSold["data"][0]["vouchers"]-$vouchersSold["data"][1]["vouchers"];
                } else {
                    $dashboard["vouchers-sold"] = 0;
                    $dashboard["voucher-improve"] = 0-$vouchersSold["data"][0]["vouchers"];
                }
            }
            
            if (isset($branch)) {
                $todaySales = selectDB($connection,"COALESCE(SUM(`sales-total`),0) AS `sales`", "`sales`","`sales-branch`='$branch' AND `income-no`>0 AND `sales-date`='$date'");
                $hotTreatment = selectDB(
                    $connection,
                    "`treatment-name` AS `treatment`,COUNT(`session-treatment`) AS `count`", 
                    "`session` AS ss INNER JOIN `treatment` AS t ON ss.`session-treatment`=t.`treatment-code`",
                    "MONTH(`session-date`)=MONTH('$date') AND YEAR(`session-date`)=YEAR('$date') GROUP BY `session-treatment` ORDER BY `count` DESC LIMIT 1");$hotTreatment = selectDB($connection,"`treatment-name` AS `treatment`,COUNT(`session-treatment`) AS `count`", "`session` AS ss INNER JOIN `treatment` AS t ON ss.`session-treatment`=t.`treatment-code`","MONTH(`session-date`)=MONTH('$date') AND YEAR(`session-date`)=YEAR('$date') GROUP BY `session-treatment` ORDER BY `count` DESC LIMIT 1"
                );
                $hotTherapist = selectDB(
                    $connection,
                    "`complete-name` AS `therapist`,COUNT(`session-therapist`) AS `count`", 
                    "`session` AS ss INNER JOIN `employee` AS e ON ss.`session-therapist`=e.`emp-code`",
                    "MONTH(`session-date`)=MONTH('$date') AND YEAR(`session-date`)=YEAR('$date') GROUP BY `session-therapist` ORDER BY `count` DESC LIMIT 1"
                );
                
                
                $today = selectDB(
                    $connection, 
                    "`complete-name`, `emp-nickname`, COALESCE(ss.`session-count`,0) AS `session-count`, IF(`att-shift`='OFF','OFF',`att-in`) AS `att-in`, COUNT(DISTINCT CASE WHEN `session-status`='completed' THEN `session-customer` END) AS `completed-sessions`, COUNT(DISTINCT CASE WHEN `session-status`='ongoing' THEN `session-customer` END) AS `ongoing-sessions`, IF(`att-shift`!='OFF' AND `att-shift`!='L' AND SUBTIME(`att-in`, `shift-start-time`)>0 AND HOUR(SUBTIME(`att-in`, `shift-start-time`))>1,2*e.`emp-telat-deduct`, IF(`att-shift`!='OFF' AND `att-shift`!='L' AND SUBTIME(`att-in`, `shift-start-time`)>0 AND (HOUR(SUBTIME(`att-in`, `shift-start-time`))>0 OR MINUTE(SUBTIME(`att-in`, `shift-start-time`))>5), (HOUR(SUBTIME(`att-in`, `shift-start-time`))+1)*e.`emp-telat-deduct`,0))+IF(((`att-shift`='M' OR `att-shift`='N') AND HOUR(SUBTIME(`att-out`, `shift-end-time`))>=1) OR ((`att-shift`='A' OR `att-shift`='D') AND HOUR(SUBTIME(`att-out`, `shift-end-time`))>1),e.`emp-telat-deduct`,0)+IF(`att-shift`!='OFF' AND `att-shift`!='L' AND `att-in` IS NULL AND `att-date`<=CURDATE() - INTERVAL 1 day,IF(WEEKDAY(`att-date`)>4,e.`emp-absen-deduct`*2,e.`emp-absen-deduct`),0) AS `emp-deduction`",
                    "`attendance` a INNER JOIN `att-shift` ash ON a.`att-shift`=ash.`shift-code` INNER JOIN `employee` e INNER JOIN `emp-grade` AS eg ON e.`emp-code`=eg.`emp-code` AND '$date'>=eg.`start-date` AND '$date'<=IFNULL(eg.`end-date`,'$date') ON a.`att-emp`=e.`emp-code` LEFT JOIN `session` s ON e.`emp-code`=s.`session-therapist` AND `session-date`='$date' LEFT JOIN (SELECT ss.`session-therapist`,COALESCE(COUNT(DISTINCT `session-customer`,`session-date`),0) AS `session-count` FROM `employee` AS e INNER JOIN `emp-grade` AS eg ON e.`emp-code`=eg.`emp-code` AND '$date'>=eg.`start-date` AND '$date'<=IFNULL(eg.`end-date`,'$date') INNER JOIN `session` AS ss ON ss.`session-therapist`=e.`emp-code` LEFT JOIN `voucher` AS v ON ss.`session-code`=v.`voucher-session` LEFT JOIN `walkin` AS w ON ss.`session-code`=w.`walkin-session` INNER JOIN `bonus-therapist` AS b ON eg.`emp-grade`=b.`grade` AND b.`treatment-code`=ss.`session-treatment` INNER JOIN `treatment` AS t ON ss.`session-treatment`=t.`treatment-code` WHERE ss.`session-date` BETWEEN date_add(date_add(LAST_DAY('$date'),interval 1 DAY),interval -1 MONTH) AND '$date' GROUP BY e.`emp-code`) AS ss ON e.`emp-code`=ss.`session-therapist`", 
                    "`att-date`='$date' AND `emp-grade`<>'K' AND `emp-branch`='$branch' GROUP BY a.`att-emp` ORDER BY -`att-in` DESC"
                );
                
                $uncontacted = selectDB(
                    $connection, "c.`customer-code`, c.`customer-name`, c.`customer-gender`, c.`customer-mobile`, s.`session-date`, e.`emp-nickname` AS `session-therapist`",
                    "(SELECT `session`.* FROM `session`, (SELECT `session-customer`,MAX(`session-date`) AS `session-date` FROM `session` GROUP BY `session-customer`) `last-visit` WHERE `session`.`session-customer`=`last-visit`.`session-customer` AND `session`.`session-date`=`last-visit`.`session-date`) AS s INNER JOIN `customer` c ON s.`session-customer`=c.`customer-code` AND c.`customer-mobile`<>'' INNER JOIN `employee` e ON s.`session-therapist`=e.`emp-code`",
                    "`session-customer`>5 AND `emp-branch`='$branch' GROUP BY `session-customer` ORDER BY s.`session-date`"
                );
            } else {
                $todaySales = selectDB($connection,"COALESCE(SUM(`sales-total`),0) AS `sales`", "`sales`","`income-no`>0 AND `sales-date`='$date'");
                $hotTreatment = selectDB($connection,"`treatment-name` AS `treatment`,COUNT(`session-treatment`) AS `count`", "`session` AS ss INNER JOIN `treatment` AS t ON ss.`session-treatment`=t.`treatment-code`","MONTH(`session-date`)=MONTH('$date') AND YEAR(`session-date`)=YEAR('$date') GROUP BY `session-treatment` ORDER BY `count` DESC LIMIT 1");
                $hotTherapist = selectDB($connection,"`complete-name` AS `therapist`,COUNT(`session-therapist`) AS `count`", "`session` AS ss INNER JOIN `employee` AS e ON ss.`session-therapist`=e.`emp-code`","MONTH(`session-date`)=MONTH('$date') AND YEAR(`session-date`)=YEAR('$date') GROUP BY `session-therapist` ORDER BY `count` DESC LIMIT 1");
                
                $today = selectDB(
                    $connection, 
                    "`complete-name`, `emp-nickname`, COALESCE(ss.`session-count`,0) AS `session-count`, IF(`att-shift`='OFF','OFF',`att-in`) AS `att-in`, COUNT(DISTINCT CASE WHEN `session-status`='completed' THEN `session-customer` END) AS `completed-sessions`, COUNT(DISTINCT CASE WHEN `session-status`='ongoing' THEN `session-customer` END) AS `ongoing-sessions`, IF(`att-shift`!='OFF' AND `att-shift`!='L' AND SUBTIME(`att-in`, `shift-start-time`)>0 AND HOUR(SUBTIME(`att-in`, `shift-start-time`))>1,2*e.`emp-telat-deduct`, IF(`att-shift`!='OFF' AND `att-shift`!='L' AND SUBTIME(`att-in`, `shift-start-time`)>0 AND (HOUR(SUBTIME(`att-in`, `shift-start-time`))>0 OR MINUTE(SUBTIME(`att-in`, `shift-start-time`))>5), (HOUR(SUBTIME(`att-in`, `shift-start-time`))+1)*e.`emp-telat-deduct`,0))+IF(((`att-shift`='M' OR `att-shift`='N') AND HOUR(SUBTIME(`att-out`, `shift-end-time`))>=1) OR ((`att-shift`='A' OR `att-shift`='D') AND HOUR(SUBTIME(`att-out`, `shift-end-time`))>1),e.`emp-telat-deduct`,0)+IF(`att-shift`!='OFF' AND `att-shift`!='L' AND `att-in` IS NULL AND `att-date`<=CURDATE() - INTERVAL 1 day,IF(WEEKDAY(`att-date`)>4,e.`emp-absen-deduct`*2,e.`emp-absen-deduct`),0) AS `emp-deduction`",
                    "`employee` e INNER JOIN `emp-grade` AS eg ON e.`emp-code`=eg.`emp-code` AND '$date'>=eg.`start-date` AND '$date'<=IFNULL(eg.`end-date`,'$date') INNER JOIN `attendance` a ON a.`att-emp`=e.`emp-code` AND `att-date`='$date' LEFT JOIN `att-shift` ash ON a.`att-shift`=ash.`shift-code` LEFT JOIN `session` s ON e.`emp-code`=s.`session-therapist` AND `session-date`='$date' LEFT JOIN (SELECT ss.`session-therapist`,COALESCE(COUNT(DISTINCT `session-customer`,`session-date`),0) AS `session-count` FROM `employee` AS e INNER JOIN `emp-grade` AS eg ON e.`emp-code`=eg.`emp-code` AND '$date'>=eg.`start-date` AND '$date'<=IFNULL(eg.`end-date`,'$date') INNER JOIN `session` AS ss ON ss.`session-therapist`=e.`emp-code` LEFT JOIN `voucher` AS v ON ss.`session-code`=v.`voucher-session` LEFT JOIN `walkin` AS w ON ss.`session-code`=w.`walkin-session` INNER JOIN `bonus-therapist` AS b ON eg.`emp-grade`=b.`grade` AND b.`treatment-code`=ss.`session-treatment` INNER JOIN `treatment` AS t ON ss.`session-treatment`=t.`treatment-code` WHERE ss.`session-date` BETWEEN date_add(date_add(LAST_DAY('$date'),interval 1 DAY),interval -1 MONTH) AND '$date' GROUP BY e.`emp-code`) AS ss ON e.`emp-code`=ss.`session-therapist`", 
                    "`emp-grade`<>'K' GROUP BY e.`emp-code` ORDER BY -`att-in` DESC"
                );
            }
            
            if(isset($uncontacted["data"])) $dashboard["uncontacted"] = $uncontacted["data"];
            $dashboard["today"] = $today["data"];
            $dashboard["today-sales"] = $todaySales["data"][0]["sales"];
            $dashboard["hot-treatment"] = (count($hotTreatment["data"])>0)?$hotTreatment["data"][0]["treatment"]:'';
            $dashboard["hot-therapist"] = (count($hotTherapist["data"])>0)?$hotTherapist["data"][0]["therapist"]:'';
            
            if (isset($branch)) {
                $monthlyIncome = selectDB(
                    $connection,
                    "MONTH(`income-date`) AS `month`,SUM(`income-pay-amount`) AS `amount`", 
                    "`income-payment` AS ip INNER JOIN `income` AS i ON ip.`income-id`=i.`income-id` INNER JOIN `sales` s ON s.`income-no`=i.`income-id`",
                    "`sales-branch`='$branch' AND YEAR(`income-date`)='$profitYear' GROUP BY MONTH(`income-date`)"
                );
            } else {
                $monthlyIncome = selectDB(
                    $connection,
                    "MONTH(`income-date`) AS `month`,SUM(`income-pay-amount`) AS `amount`", 
                    "`income-payment` AS ip INNER JOIN `income` AS i ON ip.`income-id`=i.`income-id`",
                    "YEAR(`income-date`)='$profitYear' GROUP BY MONTH(`income-date`)"
                );
            }
            $dashboard["monthly-income"] = $monthlyIncome["data"];
            
            if (!isset($branch)) {
                $monthlyExpense = selectDB(
                    $connection,
                    "MONTH(`expense-date`) AS `month`,SUM(`expense-pay-amount`) AS `amount`", 
                    "`expense-payment` AS ep INNER JOIN `expense` AS e ON ep.`expense-id`=e.`expense-id`",
                    "`expense-pay-description` NOT LIKE 'Profit%' AND YEAR(`expense-date`)='$profitYear' GROUP BY MONTH(`expense-date`)"
                );
                $dashboard["monthly-expense"] = $monthlyExpense["data"];
            }
        }
        return $dashboard;
    }
}
