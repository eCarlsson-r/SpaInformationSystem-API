<?php

namespace App\Http\Controllers;

use App\Models\Compensation;
use App\Services\CompensationService;
use Illuminate\Http\Request;

class CompensationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $compensation = Compensation::with('employee')->where('period_id', $request->input('period_id'))->get();
        
        \Illuminate\Support\Facades\Log::info('Compensation Query Params:', $request->all());
        
        if ($compensation->isNotEmpty()) {
            return response()->json($compensation, 200);
        } else if ($request->start && $request->end && $request->has('employees')) {
            // [NEW] Check if we want a specific report for specific employees
            $service = new CompensationService($request->start, $request->end);
            // Automatically determine report type (Voucher for Cashier, Session for Therapist)
            return response()->json($service->calculateDetailedBonuses(explode(",", $request->employees)));
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
    public function show()
    {
        //
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
