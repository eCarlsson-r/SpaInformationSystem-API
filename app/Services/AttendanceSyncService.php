<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use Carbon\Carbon;
use ZktecoLib\ZktecoLib;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

class AttendanceSyncService
{
    public function syncAttendance()
    {
        $zk = new ZktecoLib(env('ZKTECO_IP'));
        $zk->connect();
        $users = $zk->getUser();
        $attendance = $zk->getAttendance();

        $this->sortArray($attendance, array(0, 1));

        foreach($attendance as $idx=>$att) {
            if (count($att)>1) {
            foreach ($users as $user) {
                if ($user[0] == $att[1]) {
                    $datetime = explode(" ",$att[3]);
                    $attendance[$idx] = array("name"=>preg_replace("/[^a-zA-Z]/", "", $user[1]),"date"=>$datetime[0],"time"=>$datetime[1]);
                }
            }
            
            } else {
            array_splice($attendance, $idx, 1);
            }
        }
        
        $attRecords = array();
        foreach($attendance as $att) {
            if (isset($att["name"]) && isset($att["date"])) {
            if (!isset($attRecords[$att["name"]." ".$att["date"]])) {
                $attRecords[$att["name"]." ".$att["date"]] = array($att);
            } else {
                array_push($attRecords[$att["name"]." ".$att["date"]],$att);
            }
            }
        }

        $zk->disconnect();

        foreach ($attRecords as $record=>$attendance) {
            $attRecords[$record] = array(
                "name"=>$attendance[0]["name"], 
                "date"=>$attendance[0]["date"], 
                "time-in"=>$attendance[0]["time"],
                "time-out"=>$attendance[count($attendance)-1]["time"]
            );

            $employee = Employee::where("name", $attendance[0]["name"])->value("id");
            if ($employee) {
                if (count($attendance) == 1) {
                    $attendance_date = $attendance[0]["date"];
                    $attendance_time = $attendance[0]["time"];

                    $shiftSchedule = Shift::join('attendance', 'shifts.id', '=', 'attendance.shift_id')
                        ->where("attendance.employee_id", $employee)
                        ->where("attendance.date", $attendance_date)
                        ->first();

                    if ($shiftSchedule) {
                        $attendanceTime = Carbon::parse($attendance_date . ' ' . $attendance_time);
                        $shiftStart = Carbon::parse($attendance_date . ' ' . $shiftSchedule->start_time);
                        $shiftEnd = Carbon::parse($attendance_date . ' ' . $shiftSchedule->end_time);

                        if (round($shiftStart->diffInHours($attendanceTime))<3) {
                            Attendance::updateOrCreate([
                                "employee_id"=>$employee,
                                "shift_id"=>$shiftSchedule->id,
                                "date"=>$attendance_date,
                                "clock_in"=>$attendance_time,
                                "clock_out"=>$shiftSchedule->end_time
                            ]);
                            unset($attRecords[$record]);
                        } else if (round($attendanceTime->diffInHours($shiftEnd))<3) {
                            Attendance::updateOrCreate([
                                "employee_id"=>$employee,
                                "shift_id"=>$shiftSchedule->id,
                                "date"=>$attendance_date,
                                "clock_in"=>$shiftSchedule->start_time,
                                "clock_out"=>$attendance_time
                            ]);
                            
                            unset($attRecords[$record]);
                        }
                    } else {
                        if (intval(explode(":",$attendance[0]["time"])[0])>16) {
                            Attendance::updateOrCreate([
                                "employee_id"=>$employee,
                                "date"=>$attendance_date,
                                "clock_out"=>$attendance_time
                            ]);
                            unset($attRecords[$record]);
                        } else {
                            Attendance::updateOrCreate([
                                "employee_id"=>$employee,
                                "date"=>$attendance_date,
                                "clock_in"=>$attendance_time
                            ]);
                            unset($attRecords[$record]);
                        }
                    }
                } else {
                    $attendance_date = $attendance[0]["date"];
                    $attendance_in = $attendance[0]["time"];
                    $attendance_out = $attendance[count($attendance)-1]["time"];
                    
                    Attendance::updateOrCreate([
                        "employee_id"=>$employee,
                        "date"=>$attendance_date,
                        "clock_in"=>$attendance_in,
                        "clock_out"=>$attendance_out
                    ]);
                    unset($attRecords[$record]);
                }
            }
        }  

        if ($syncSuccess) {
            // Store the timestamp in Redis
            // We don't set an expiration if we want it to last forever, 
            // or set one (e.g., 24 hours) to detect stale data.
            Cache::put('zkteco_last_sync_time', now()->toDateTimeString());
        }
    }

    public function getSyncStatus()
    {
        return response()->json([
            'last_sync' => Cache::get('zkteco_last_sync_time'),
            'is_online' => Cache::has('zkteco_last_sync_time') // Simple health check
        ]);
    }
}
?>