<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;
    protected $table = 'attendance';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'date',
        'clock_in',
        'clock_out'
    ];

    protected $guarded = [
        'id'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function getDeductionAttribute()
    {
        $deduction = 0;
        $employee = $this->employee;
        $shift = $this->shift;

        if (!$employee || !$shift) return 0;
        if (in_array($shift->id, ['OFF', 'L'])) return 0;

        $date = Carbon::parse($this->date);

        // Late In
        if ($this->clock_in) {
            $shiftStart = Carbon::parse($this->date . ' ' . $shift->start_time);
            $clockIn = Carbon::parse($this->date . ' ' . $this->clock_in);
            
            if ($clockIn->gt($shiftStart)) {
                $diffInMinutes = floor($shiftStart->diffInMinutes($clockIn));
                $diffInHours = floor($shiftStart->diffInHours($clockIn));
                
                if ($diffInHours >= 2) {
                     $deduction += 2 * $employee->late_deduction;
                } elseif ($diffInHours >= 1 || $diffInMinutes > 5) {
                     $deduction += ($diffInHours + 1) * $employee->late_deduction;
                }
            }
        }
        
        // Absent
        if (!$this->clock_in && $date->lt(Carbon::yesterday())) {
             if ($date->isWeekend()) {
                 $deduction += 2 * $employee->absent_deduction;
             } else {
                 $deduction += $employee->absent_deduction;
             }
        }

        return $deduction;
    }
}
