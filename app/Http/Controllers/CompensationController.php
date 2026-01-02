<?php

namespace App\Http\Controllers;

use App\Models\Compensation;
use App\Models\Period;
use App\Services\CompensationService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CompensationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->has('period_id') && $request->has('employee_id')) {
            $period = Period::find($request->period_id);
            $service = new CompensationService($period->start, $period->end);
            $summary = array();
            $data = Compensation::where('period_id', $request->period_id)->where('employee_id', $request->employee_id)->first();
            
            array_push($summary, ["attribute"=>"therapist_bonus", "value"=>$data->therapist_bonus]);
            array_push($summary, ["attribute"=>"recruit_bonus", "value"=>$data->recruit_bonus]);
            
            if ($data->addition_description !== "") {
                $addition = explode('<br/>', $data->addition_description);
                foreach($addition as $add) {
                    $value = explode(' sebesar ', $add);
                    if (count($value) > 1 && $value[1] > 0) {
                        array_push($summary, ["attribute"=>$value[0], "value"=>$value[1]]);
                    }
                }
            }
            
            if ($data->deduction_description !== "") {
                $deduction = explode('<br/>', $data->deduction_description);
                foreach($deduction as $ded) {
                    $value = explode(' sebesar ', $ded);
                    if (count($value) > 1 && $value[1] > 0) {
                        array_push($summary, ["attribute"=>$value[0], "value"=>$value[1]]);
                    }
                }
            }

            array_push($summary, ["attribute"=>"total", "value"=>$data->total]);

            return response()->json($summary, 200);
        } else if ($request->start && $request->end && $request->has('employee_id')) {
            // [NEW] Check if we want a specific report for specific employees
            $service = new CompensationService(Carbon::parse($request->start)->toDateString(), Carbon::parse($request->end)->toDateString());
            // Automatically determine report type (Voucher for Cashier, Session for Therapist)
            return response()->json($service->calculateDetailedBonuses([$request->employee_id]));
        } else if ($request->start && $request->end && $request->has('employees')) {
            // [NEW] Check if we want a specific report for specific employees
            $service = new CompensationService($request->start, $request->end);
            // Automatically determine report type (Voucher for Cashier, Session for Therapist)
            return response()->json($service->calculateDetailedBonuses(json_decode($request->employees)));
        }
        else if ($request->start && $request->end) {
            // Calculate compensation on-the-fly if not stored yet
            $service = new CompensationService($request->start, $request->end);
            $calculated = $service->calculate();
            return response()->json($calculated, 200);
        } else {
            return response()->json([], 200);
        }
    }

    /** 
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'period_id' => 'required|exists:periods,id'
        ]);

        foreach ($request->compensations as $comp) {
            Compensation::create([
                'employee_id' => $comp['employee_id'],
                'period_id' => $request->period_id,
                'base_salary' => $comp['base_salary'],
                'therapist_bonus' => $comp['therapist_bonus'],
                'recruit_bonus' => $comp['recruit_bonus'],
                'addition' => $comp['addition'],
                'addition_description' => $comp['addition_description'],
                'deduction' => $comp['deduction'],
                'deduction_description' => $comp['deduction_description'],
                'total' => $comp['total'],
            ]);
        }

        return response()->json(['message' => 'Compensation calculated and stored successfully'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, String $id)
    {
        $compensation = Compensation::with('employee')->where('period_id', $id)->get();
        
        if ($compensation->isNotEmpty()) {
            return response()->json($compensation, 200);
        } else {
            return response()->json([], 200);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Compensation $compensation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (Compensation::where('period_id', $id)->delete()) {
            return response()->json(['message' => 'Compensation deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete compensation'], 500);
        }
    }
}
