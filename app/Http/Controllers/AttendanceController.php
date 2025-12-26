<?php

namespace App\Http\Controllers;

use App\Models\Attendance; 
use App\Services\AttendanceSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Grade;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $employee = $request->employee_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if (isset($employee)) {
            $attendance = Attendance::with('employee')
                ->where('employee_id', $employee)
                ->whereBetween('date', [$start_date, $end_date])
                ->orderBy('employee_id', 'asc')->orderBy('date', 'asc')
                ->get();
        } else {
            $attendance = Attendance::with('employee')
                ->whereBetween('date', [$start_date, $end_date])
                ->orderBy('employee_id', 'asc')->orderBy('date', 'asc')
                ->get();
        }

        foreach($attendance as $data) {
            $notOnLeave = $data->shift_id != 'OFF' && $data->shift_id != 'L';
            $longShift = $data->shift_id == 'M' || $data->shift_id == 'N';
            $wholeDay = $data->shift_id == 'A' || $data->shift_id == 'D';
            $superLate = $notOnLeave && Carbon::parse($data->clock_in)->diffInHours(Carbon::parse($data->shift->start_time))>0 && Carbon::parse($data->clock_in)->diffInHours(Carbon::parse($data->shift->start_time))>1;
            $late = $notOnLeave && Carbon::parse($data->clock_in)->diffInHours(Carbon::parse($data->shift->start_time))>0 && (Carbon::parse($data->clock_in)->diffInHours(Carbon::parse($data->shift->start_time))>0 OR Carbon::parse($data->clock_in)->diffInMinutes(Carbon::parse($data->shift->start_time))>5);
            if ($superLate) $lateDeduction = 2 * $data->employee->late_deduction;
            else if ($late) $lateDeduction = (Carbon::parse($data->clock_in)->diffInHours(Carbon::parse($data->shift->start_time)) + 1) * $data->employee->late_deduction;
            else $lateDeduction = 0;

            $specialEarly = $longShift && Carbon::parse($data->clock_out)->diffInHours(Carbon::parse($data->shift->end_time))>=1;
            $early = $wholeDay && Carbon::parse($data->clock_out)->diffInHours(Carbon::parse($data->shift->end_time))>1;
            $earlyDeduction = $specialEarly || $early ? $data->employee->late_deduction : 0;

            $absent = $notOnLeave && Carbon::parse($data->date)->lte(Carbon::yesterday());
            if ($absent && Carbon::parse($data->date)->isWeekend()) $absentDeduction = 2 * $data->employee->absent_deduction;
            else if ($absent) $absentDeduction = $data->employee->absent_deduction;
            else $absentDeduction = 0;

            $data->deduction = $lateDeduction + $earlyDeduction + $absentDeduction;
        }

        if (!($attendance->isEmpty())) {
            return response()->json($attendance);
        } else {
            return response()->json(['message' => 'No attendance found'], 404);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $attendances = array();

        for ($date = $startDate; $date <= $endDate; $date->addDay()) {
            if (isset($request->employee_id) && $request->employee_id!='') {
                $attendance = Attendance::updateOrCreate([
                    'employee_id' => $request->employee_id,
                    'date' => $date->format("Y-m-d"),
                    'shift_id' => $request->shift_id,
                ]);
            } else {
                $attendance = Attendance::where('date', $date->format("Y-m-d"))->update([
                    'shift_id' => $request->shift_id
                ]);
            }
            array_push($attendances, $attendance);
        }

        if (count($attendances) > 0) {
            return response()->json($attendances, 201);
        } else {
            return response()->json(['message' => 'Failed to create attendance'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(String $id)
    {
        $attendance = Attendance::with('employee')
            ->whereBetween('date', [
                Carbon::parse($id."-1")->toDateString(), 
                Carbon::parse($id."-7")->toDateString()
            ])
            ->get();

        if (!($attendance->isEmpty())) {
            $employeeSchedule = array();
            foreach($attendance as $data) {
                $job_type = ($data->employee->grade->grade == 'K') ? 'cashier' : 'therapist';
                $employee_key = $data->employee_id."-".$data->employee->complete_name."-".$job_type;
                
                if (isset($employeeSchedule[$employee_key])) {
                    array_push($employeeSchedule[$employee_key], $data);
                } else {
                    $employeeSchedule[$employee_key] = array($data);
                }
            }

            $schedule = array();
            foreach($employeeSchedule as $idx => $data) {
                $employee = explode("-", $idx);
                $empSchedule = array("id"=>$employee[0], "name"=>$employee[1], "job_type"=>$employee[2]);
                foreach($data as $day) {
                    $empSchedule[strtolower(Carbon::parse($day->date)->format('l'))] = $day->shift_id;
                }
                array_push($schedule, $empSchedule);
            }

            return response()->json($schedule);
        } else {
            return response()->json(['message' => 'No attendance found'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Attendance $attendance)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attendance $attendance)
    {
        //
    }

    public function sync(Request $request)
    {
        $service = new AttendanceSyncService();
        $service->syncAttendance();
        return response()->json(['message' => 'Attendance synced successfully'], 200);
    }

    public function getSyncStatus()
    {
        $service = new AttendanceSyncService();
        return response()->json($service->getSyncStatus());
    }
}
