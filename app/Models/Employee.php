<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Employee extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $table = 'employees';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'complete_name',
        'name',
        'status',
        'identity_type',
        'identity_number',
        'place_of_birth',
        'date_of_birth',
        'certified',
        'recruiter',
        'branch_id',
        'base_salary',
        'expertise',
        'gender', 'phone',
        'address', 'mobile', 'email',
        'bank_account',
        'bank'
    ];

    protected $attributes = [
        'absent_deduction' => 50000,
        'meal_fee' => 0,
        'late_deduction' => 20000
    ];

    protected $guarded = [
        'id'
    ];

    protected static function booted()
    {
        static::saved(fn () => event(new \App\Events\EntityUpdated('employees')));
        static::deleted(fn () => event(new \App\Events\EntityUpdated('employees')));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function sessions()
    {
        return $this->hasMany(Session::class);
    }

    public function grade()
    {
        return $this->hasOne(Grade::class)->latestOfMany();
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public static function getDailyReport($date, $branchId = null)
    {
        $startDate = date('Y-m-d', strtotime("last day of previous month", strtotime($date)));

        $employees = self::query()
            ->with(['attendance' => function($query) use ($date) {
                $query->where('date', $date)->with('shift');
            }])
            ->with(['sessions' => function($query) use ($date) {
                $query->where('date', $date);
            }])
            ->whereHas('grade', function($query) use ($date) {
                $query->where('start_date', '<=', $date)
                    ->where(function($q) use ($date) {
                        $q->where('end_date', '>=', $date)
                            ->orWhereNull('end_date');
                    })
                    ->where('grade', '<>', 'K');
            });

        if ($branchId) {
            $employees->where('branch_id', $branchId);
        }

        // Subquery for session count
        $sessionCountSub = Session::selectRaw('COALESCE(COUNT(DISTINCT sessions.customer_id, sessions.date), 0)')
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
            ->whereColumn('sessions.employee_id', 'employees.id')
            ->whereBetween('sessions.date', [$startDate, $date]);

        $employees->addSelect(['session_count' => $sessionCountSub]);

        return $employees->get()->map(function($employee) use ($date) {
            $attendance = $employee->attendance->first();
            $shift = $attendance ? $attendance->shift : null;

            $clockIn = ($attendance && $attendance->shift_id != 'OFF') ? $attendance->clock_in : ($attendance && $attendance->shift_id == 'OFF' ? 'OFF' : null);

            // Calculate Deduction
            $deduction = 0;
            if ($attendance && $shift && $shift->id != 'OFF' && $shift->id != 'L') {
                // Late In
                if ($attendance->clock_in) {
                    $shiftStart = Carbon::parse($date . ' ' . $shift->start_time);
                    $actualClockIn = Carbon::parse($date . ' ' . $attendance->clock_in);

                    if ($actualClockIn->gt($shiftStart)) {
                        $diffInMinutes = $actualClockIn->diffInMinutes($shiftStart);
                        $diffInHours = $actualClockIn->diffInHours($shiftStart);

                        if ($diffInHours > 1) { // > 1 hour (meaning 2 hours or more)
                             $deduction += 2 * $employee->late_deduction;
                        } elseif ($diffInHours > 0 || $diffInMinutes > 5) {
                             $deduction += ($diffInHours + 1) * $employee->late_deduction;
                        }
                    }
                }

                // "Late Out" / Penalty logic
                if ($attendance->clock_out) {
                     $shiftEnd = Carbon::parse($date . ' ' . $shift->end_time);
                     $actualClockOut = Carbon::parse($date . ' ' . $attendance->clock_out);

                     // The SQL used SUBTIME(clock_out, end_time). If positive, it means clock_out > end_time.
                     if ($actualClockOut->gt($shiftEnd)) {
                         $diffInHours = $actualClockOut->diffInHours($shiftEnd);

                         if (($shift->id == 'M' || $shift->id == 'N') && $diffInHours >= 1) {
                             $deduction += $employee->late_deduction;
                         } elseif (($shift->id == 'A' || $shift->id == 'D') && $diffInHours > 1) {
                             $deduction += $employee->late_deduction;
                         }
                     }
                }
            }

            // Absent Logic
            if ((!$attendance || !$attendance->clock_in) && Carbon::parse($date)->lte(Carbon::yesterday())) {
                 if ($attendance && $shift && $shift->id != 'OFF' && $shift->id != 'L') {
                     if (Carbon::parse($date)->isWeekend()) {
                         $deduction += 2 * $employee->absent_deduction;
                     } else {
                         $deduction += $employee->absent_deduction;
                     }
                 }
            }

            $completedSessions = $employee->sessions->where('status', 'completed')->unique('customer_id')->count();
            $ongoingSessions = $employee->sessions->where('status', 'ongoing')->unique('customer_id')->count();

            return [
                'complete_name' => $employee->complete_name,
                'name' => $employee->name,
                'session_count' => $employee->session_count,
                'clock_in' => $clockIn,
                'completed_sessions' => $completedSessions,
                'ongoing_sessions' => $ongoingSessions,
                'deduction' => $deduction
            ];
        })->sortByDesc('clock_in')->values();
    }
}
