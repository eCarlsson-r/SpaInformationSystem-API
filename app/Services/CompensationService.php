<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Session;
use App\Models\Sales;
use App\Models\Bonus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Models\SalesRecord;
use Illuminate\Support\Facades\DB;

class CompensationService
{
    protected $startDate;
    protected $endDate;
    protected $numOfDays;

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->numOfDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
    }

    /**
     * Main entry point - calculates compensation for all employees
     */
    public function calculate(): Collection
    {
        $cashiers = $this->calculateCashierCompensation();
        $therapists = $this->calculateTherapistCompensation();

        return $cashiers->merge($therapists);
    }

    /**
     * Calculate treatment bonuses (Voucher + Trainer) grouping by Employee then Treatment
     * Accepts array of employee IDs
     */
    public function calculateTreatmentBonuses(array $employeeIds): Collection
    {
        $startDate = $this->startDate;
        $endDate = $this->endDate;

        // PART 1: Voucher Query
        $voucherData = SalesRecord::query()
            ->join('sales', 'sales_records.sales_id', '=', 'sales.id')
            ->join('treatments', 'sales_records.treatment_id', '=', 'treatments.id')
            ->whereIn('sales.employee_id', $employeeIds)
            ->where('sales_records.redeem_type', 'voucher')
            ->whereBetween('sales.date', [$startDate, $endDate])
            ->select([
                'sales.employee_id',
                'treatments.name as treatment_name',
                DB::raw('SUM(sales_records.price) as voucher_price'),
                DB::raw('COUNT(sales_records.id) as voucher_qty'),
                DB::raw('FLOOR(SUM(CASE WHEN sales_records.total_price > 0 THEN 1 ELSE 0 END) * 10000 / 1000) * 1000 as voucher_total'),
                DB::raw('0 as recruit_bonus')
            ])
            ->groupBy('sales.employee_id', 'treatments.name');

        // PART 2: Trainer/Recruit Bonus Query
        $trainerData = Session::query()
            ->join('employees as term_emp', 'sessions.employee_id', '=', 'term_emp.id')
            ->join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
            ->join('grades', function($join) use ($startDate, $endDate) {
                $join->on('term_emp.id', '=', 'grades.employee_id')
                     ->where('grades.start_date', '<=', $endDate)
                     ->where(function($q) use ($startDate) {
                         $q->where('grades.end_date', '>=', $startDate)
                           ->orWhereNull('grades.end_date');
                     });
            })
            ->join('bonus', function($join) {
                $join->on('grades.grade', '=', 'bonus.grade')
                     ->on('sessions.treatment_id', '=', 'bonus.treatment_id');
            })
            ->whereIn('term_emp.recruiter', $employeeIds)
            ->whereBetween('sessions.date', [$startDate, $endDate])
            ->select([
                'term_emp.recruiter as employee_id',
                'treatments.name as treatment_name',
                DB::raw('0 as voucher_price'),
                DB::raw('0 as voucher_qty'),
                DB::raw('0 as voucher_total'),
                DB::raw('COALESCE(SUM(bonus.trainer_deduction), 0) as recruit_bonus')
            ])
            ->groupBy('term_emp.recruiter', 'treatments.name');

        // Combine
        $merged = $trainerData->unionAll($voucherData)->get();

        // Group by Employee ID first
        return $merged->groupBy('employee_id')->map(function ($empItems) {
            // Then Group by Treatment Name
            return $empItems->groupBy('treatment_name')->map(function ($items, $name) {
                return [
                    'treatment_name' => $name,
                    'voucher_price' => $items->sum('voucher_price'),
                    'voucher_qty' => $items->sum('voucher_qty'),
                    'voucher_bonus' => $items->sum('voucher_total'),
                    'recruit_bonus' => $items->sum('recruit_bonus'),
                ];
            })->values();
        });
    }

    /**
     * PART 1: Cashiers (Grade = 'K')
     */
    protected function calculateCashierCompensation(): Collection
    {
        return Employee::query()
            ->where('status', 'fixed')
            ->whereHas('grade', fn($q) => $this->activeGradeScope($q, 'K'))
            ->with(['attendance' => fn($q) => $q->whereBetween('date', [$this->startDate, $this->endDate])->with('shift')])
            ->get()
            ->map(function ($employee) {
                $attendanceData = $this->calculateCashierAttendance($employee);
                $voucherSalesCount = $this->getVoucherSalesCount($employee->id);
                $recruitBonus = $this->getRecruitBonus($employee->id);

                $therapistBonus = floor($voucherSalesCount * 10000 / 1000) * 1000;

                return [
                    'employee_id' => $employee->id,
                    'complete_name' => $employee->complete_name,
                    'base_salary' => $attendanceData['base_salary'],
                    'emp_grade' => 'K',
                    'therapist_bonus' => $therapistBonus,
                    'recruit_bonus' => $recruitBonus,
                    'addition' => $attendanceData['addition'],
                    'addition_description' => $attendanceData['addition_description'],
                    'deduction' => 0,
                    'deduction_description' => '',
                    'total' => $attendanceData['net_salary'] + $therapistBonus + $recruitBonus,
                ];
            });
    }

    /**
     * Calculate cashier attendance-based salary
     */
    protected function calculateCashierAttendance(Employee $employee): array
    {
        $attendance = $employee->attendance;
        $lastDayOfMonth = Carbon::parse($this->endDate)->daysInMonth;

        // Count working days (shift != OFF and clock_in exists)
        $workingDays = $attendance->filter(fn($att) => 
            $att->shift_id !== 'OFF' && $att->clock_in !== null
        )->pluck('date')->unique()->count();

        // Base salary calculation (half if < half month worked)
        $baseSalary = round($workingDays < ($lastDayOfMonth / 2) 
            ? $employee->base_salary / 2 
            : $employee->base_salary);

        // Meal fee calculation
        $mealFee = $workingDays * $employee->meal_fee;

        // Diligence bonus (200k if no absences)
        $hasAbsences = $this->hasAbsences($attendance, $employee);
        $diligenceBonus = $hasAbsences ? 0 : 200000;

        $addition = $mealFee + $diligenceBonus;
        $additionDesc = $this->buildCashierAdditionDesc($diligenceBonus, $mealFee);

        return [
            'base_salary' => $baseSalary,
            'addition' => $addition,
            'addition_description' => $additionDesc,
            'net_salary' => $baseSalary + $addition,
        ];
    }

    /**
     * Calculate detailed bonuses for a list of employees, automatically determining report type based on Grade.
     * Cashiers (K) -> Treatment Bonuses (Voucher)
     * Therapists -> Therapist Treatment Bonuses (Session)
     */
    public function calculateDetailedBonuses(array $employeeIds): Collection
    {
        // 1. Fetch Employees with their active grade
        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->with(['grade' => fn($q) => $this->activeGradeScope($q)])
            ->get();

        $cashierIds = [];
        $therapistIds = [];

        foreach ($employees as $emp) {
            $grade = $emp->grade->first()?->grade;
            if ($grade === 'K') {
                array_push($cashierIds, $emp->id);
            } else {
                array_push($therapistIds, $emp->id);
            }
        }

        $results = collect();

        // 2. Calculate for Cashiers
        if (!empty($cashierIds)) {
            $cashierBonuses = $this->calculateTreatmentBonuses($cashierIds);
            foreach ($cashierBonuses as $empId => $data) {
                $results->put($empId, $data);
            }
        }

        // 3. Calculate for Therapists
        if (!empty($therapistIds)) {
            $therapistBonuses = $this->calculateTherapistTreatmentBonuses($therapistIds);
            foreach ($therapistBonuses as $empId => $data) {
                $results->put($empId, $data);
            }
        }

        return $results;
    }

    /**
     * Calculate therapist treatment bonuses (Session Bonus + Trainer) grouping by Employee then Treatment
     * Accepts array of employee IDs
     */
    public function calculateTherapistTreatmentBonuses(array $employeeIds): Collection
    {
        $startDate = $this->startDate;
        $endDate = $this->endDate;

        // PART 1: Therapist Session Bonus Query
        $therapistData = Session::query()
            ->join('employees', 'sessions.employee_id', '=', 'employees.id')
            ->join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
            ->join('grades', function($join) use ($startDate, $endDate) {
                $join->on('employees.id', '=', 'grades.employee_id')
                     ->where('grades.start_date', '<=', $endDate)
                     ->where(function($q) use ($startDate) {
                         $q->where('grades.end_date', '>=', $startDate)
                           ->orWhereNull('grades.end_date');
                     });
            })
            ->join('bonus', function($join) {
                $join->on('grades.grade', '=', 'bonus.grade')
                     ->on('sessions.treatment_id', '=', 'bonus.treatment_id');
            })
            ->whereIn('employees.id', $employeeIds)
            ->whereBetween('sessions.date', [$startDate, $endDate])
            ->select([
                'employees.id as employee_id',
                'treatments.name as treatment_name',
                DB::raw('SUM(treatments.price) as treatment_price'),
                DB::raw('COALESCE(SUM(bonus.gross_bonus), 0) as therapist_bonus'),
                DB::raw('0 as recruit_bonus')
            ])
            ->groupBy('employees.id', 'treatments.name');

        // PART 2: Trainer/Recruit Bonus Query
        $trainerData = Session::query()
            ->join('employees as term_emp', 'sessions.employee_id', '=', 'term_emp.id')
            ->join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
            ->join('grades', function($join) use ($startDate, $endDate) {
                $join->on('term_emp.id', '=', 'grades.employee_id')
                     ->where('grades.start_date', '<=', $endDate)
                     ->where(function($q) use ($startDate) {
                         $q->where('grades.end_date', '>=', $startDate)
                           ->orWhereNull('grades.end_date');
                     });
            })
            ->join('bonus', function($join) {
                $join->on('grades.grade', '=', 'bonus.grade')
                     ->on('sessions.treatment_id', '=', 'bonus.treatment_id');
            })
            ->whereIn('term_emp.recruiter', $employeeIds)
            ->whereBetween('sessions.date', [$startDate, $endDate])
            ->select([
                'term_emp.recruiter as employee_id',
                'treatments.name as treatment_name',
                DB::raw('0 as treatment_price'),
                DB::raw('0 as therapist_bonus'),
                DB::raw('COALESCE(SUM(bonus.trainer_deduction), 0) as recruit_bonus')
            ])
            ->groupBy('term_emp.recruiter', 'treatments.name');

        // Combine
        $merged = $trainerData->unionAll($therapistData)->get();

        return $merged->groupBy('employee_id')->map(function ($empItems) {
            return $empItems->groupBy('treatment_name')->map(function ($items, $name) {
                return [
                    'treatment_name' => $name,
                    'treatment_price' => $items->sum('treatment_price'),
                    'therapist_bonus' => $items->sum('therapist_bonus'),
                    'recruit_bonus' => $items->sum('recruit_bonus'),
                ];
            })->values();
        });
    }

    protected function calculateTherapistCompensation(): Collection
    {
        return Employee::query()
            ->where('status', 'fixed')
            ->whereHas('grade', fn($q) => $this->activeGradeScope($q)->where('grade', '<>', 'K'))
            ->with(['attendance' => fn($q) => $q->whereBetween('date', [$this->startDate, $this->endDate])->with('shift')])
            ->get()
            ->map(function ($employee) {
                $attendanceData = $this->calculateTherapistAttendance($employee);
                $sessionData = $this->getTherapistSessionData($employee->id);
                $recruitBonus = $this->getRecruitBonus($employee->id);

                $sessionCount = $sessionData['session_count'];
                $therapistBonus = $sessionData['therapist_bonus'];

                // Bonus for high session count (>=70)
                $highSessionBonus = $sessionCount >= 70 ? 300000 : 0;

                // Diligence bonus based on session count
                $diligenceBonus = 0;
                if ($attendanceData['is_diligent']) {
                    $diligenceBonus = match(true) {
                        $sessionCount >= 60 => 700000,
                        $sessionCount >= 40 => 500000,
                        $sessionCount >= 20 => 300000,
                        default => 0,
                    };
                }

                $addition = $highSessionBonus + $diligenceBonus;
                $additionDesc = $this->buildTherapistAdditionDesc($sessionCount, $attendanceData['is_diligent']);

                $netSalary = $employee->base_salary + $therapistBonus + $addition + $recruitBonus - $attendanceData['deduction'];

                return [
                    'employee_id' => $employee->id,
                    'complete_name' => $employee->complete_name,
                    'base_salary' => $employee->base_salary,
                    'emp_grade' => $attendanceData['grade'],
                    'therapist_bonus' => $therapistBonus,
                    'recruit_bonus' => $recruitBonus,
                    'addition' => $addition,
                    'addition_description' => $additionDesc,
                    'deduction' => $attendanceData['deduction'],
                    'deduction_description' => $attendanceData['deduction_description'],
                    'total' => $netSalary,
                ];
            });
    }

    /**
     * Calculate therapist attendance deductions
     */
    protected function calculateTherapistAttendance(Employee $employee): array
    {
        $attendance = $employee->attendance;
        $lateDeduction = 0;
        $absentDeduction = 0;
        $earlyLeaveDeduction = 0;

        foreach ($attendance as $att) {
            $shift = $att->shift;
            if (!$shift || in_array($shift->id, ['OFF', 'L'])) continue;

            $date = Carbon::parse($att->date);

            // Late check
            if ($att->clock_in) {
                $shiftStart = Carbon::parse($att->date . ' ' . $shift->start_time);
                $clockIn = Carbon::parse($att->date . ' ' . $att->clock_in);

                if ($clockIn->gt($shiftStart)) {
                    $diffHours = $clockIn->diffInHours($shiftStart);
                    $diffMinutes = $clockIn->diffInMinutes($shiftStart);

                    if ($diffHours > 1) {
                        $lateDeduction += 2 * $employee->late_deduction;
                    } elseif ($diffHours > 0 || $diffMinutes > 5) {
                        $lateDeduction += ($diffHours + 1) * $employee->late_deduction;
                    }
                }
            }

            // Absent check (no clock_in on work day before yesterday)
            if (!$att->clock_in && $date->lte(Carbon::yesterday())) {
                $multiplier = $date->isWeekend() ? 2 : 1;
                $absentDeduction += $multiplier * $employee->absent_deduction;
            }

            // Early leave check
            if ($att->clock_out && $shift->end_time) {
                $shiftEnd = Carbon::parse($att->date . ' ' . $shift->end_time);
                $clockOut = Carbon::parse($att->date . ' ' . $att->clock_out);

                if ($clockOut->gt($shiftEnd)) {
                    $diffHours = $clockOut->diffInHours($shiftEnd);
                    if ((in_array($shift->id, ['M', 'N']) && $diffHours >= 1) ||
                        (in_array($shift->id, ['A', 'D']) && $diffHours > 1)) {
                        $earlyLeaveDeduction += $employee->late_deduction;
                    }
                }
            }
        }

        $deduction = $lateDeduction + $absentDeduction + $earlyLeaveDeduction;
        $isDiligent = $attendance->count() == $this->numOfDays && $absentDeduction == 0;

        return [
            'deduction' => $deduction,
            'deduction_description' => $this->buildDeductionDesc($lateDeduction, $absentDeduction, $earlyLeaveDeduction),
            'is_diligent' => $isDiligent,
            'grade' => $employee->grade->first()?->grade,
        ];
    }

    /**
     * Get therapist session data (session count and bonus)
     */
    protected function getTherapistSessionData(int $employeeId): array
    {
        $sessions = Session::query()
            ->where('sessions.employee_id', $employeeId)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->join('grades', function ($join) {
                $join->on('sessions.employee_id', '=', 'grades.employee_id');
                $this->activeGradeJoinScope($join);
            })
            ->join('bonus', function ($join) {
                $join->on('grades.grade', '=', 'bonus.grade')
                    ->on('bonus.treatment_id', '=', 'sessions.treatment_id');
            })
            ->selectRaw('COUNT(DISTINCT sessions.customer_id, sessions.date) as session_count')
            ->selectRaw('COALESCE(SUM(bonus.gross_bonus), 0) as therapist_bonus')
            ->first();
        
        return [
            'session_count' => $sessions?->session_count ?? 0,
            'therapist_bonus' => $sessions?->therapist_bonus ?? 0,
        ];
    }

    /**
     * Get voucher sales count for cashiers
     */
    protected function getVoucherSalesCount(int $employeeId): int
    {
        return Sales::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->whereHas('records', fn($q) => $q->where('redeem_type', 'voucher'))
            ->count();
    }

    /**
     * Get recruit bonus for any employee
     */
    protected function getRecruitBonus(int $recruiterId): int
    {
        return Session::query()
            ->join('employees', 'sessions.employee_id', '=', 'employees.id')
            ->where('employees.recruiter', $recruiterId)
            ->whereBetween('sessions.date', [$this->startDate, $this->endDate])
            ->join('grades', function ($join) {
                $join->on('sessions.employee_id', '=', 'grades.employee_id');
                $this->activeGradeJoinScope($join);
            })
            ->join('bonus', function ($join) {
                $join->on('grades.grade', '=', 'bonus.grade')
                    ->on('bonus.treatment_id', '=', 'sessions.treatment_id');
            })
            ->sum('bonus.trainer_deduction') ?? 0;
    }

    /**
     * Scope for active grade within date range
     */
    protected function activeGradeScope($query, ?string $grade = null)
    {
        $query->where('start_date', '<=', $this->endDate)
            ->where(fn($q) => $q->where('end_date', '>=', $this->startDate)->orWhereNull('end_date'));

        if ($grade) {
            $query->where('grade', $grade);
        }

        return $query;
    }

    protected function activeGradeJoinScope($join)
    {
        $join->where('grades.start_date', '<=', $this->endDate)
            ->where(fn($q) => $q->where('grades.end_date', '>=', $this->startDate)
                ->orWhereNull('grades.end_date'));
    }

    /**
     * Check if employee has any absences
     */
    protected function hasAbsences($attendance, $employee): bool
    {
        foreach ($attendance as $att) {
            $shift = $att->shift;
            if ($shift && $shift->id !== 'OFF' && !$att->clock_in) {
                $date = Carbon::parse($att->date);
                if ($date->lte(Carbon::yesterday())) {
                    return true;
                }
            }
        }
        return false;
    }

    // Helper methods for building description strings
    protected function buildCashierAdditionDesc(int $diligence, int $mealFee): string
    {
        $parts = [];
        if ($diligence > 0) {
            $parts[] = 'KERAJINAN sebesar Rp. ' . number_format($diligence, 0, ',', '.') . ',-';
        }
        if ($mealFee > 0) {
            $parts[] = 'UANG MAKAN sebesar Rp.' . number_format($mealFee, 0, ',', '.') . ',-';
        }
        return implode('<br/>', $parts);
    }

    protected function buildTherapistAdditionDesc(int $sessionCount, bool $isDiligent): string
    {
        $parts = [];
        if ($sessionCount >= 70) {
            $parts[] = 'BONUS sebesar Rp. ' . number_format(300000, 0, ',', '.') . ',-';
        }
        if ($isDiligent) {
            $diligence = match(true) {
                $sessionCount >= 60 => 700000,
                $sessionCount >= 40 => 500000,
                $sessionCount >= 20 => 300000,
                default => 0,
            };
            if ($diligence > 0) {
                $parts[] = 'KERAJINAN sebesar Rp. ' . number_format($diligence, 0, ',', '.') . ',-';
            }
        }
        return implode('<br/>', $parts);
    }

    protected function buildDeductionDesc(int $late, int $absent, int $earlyLeave): string
    {
        $parts = [];
        if ($late > 0) {
            $parts[] = 'TELAT sebesar Rp. ' . number_format($late, 0, ',', '.') . ',-';
        }
        if ($absent > 0) {
            $parts[] = 'ABSENSI sebesar Rp. ' . number_format($absent, 0, ',', '.') . ',-';
        }
        if ($earlyLeave > 0) {
            $parts[] = 'PULANG CEPAT sebesar Rp. ' . number_format($earlyLeave, 0, ',', '.') . ',-';
        }
        return implode('<br/>', $parts);
    }
}
