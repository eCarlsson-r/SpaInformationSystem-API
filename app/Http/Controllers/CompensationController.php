<?php

namespace App\Http\Controllers;

use App\Models\Compensation;
use App\Models\CompensationPeriod;
use Illuminate\Http\Request;

class CompensationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return Compensation::with('employee')->where('period_id', $request->input('period_id'))->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
