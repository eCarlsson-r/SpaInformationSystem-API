<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Shift::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $shift = Shift::create([
            'name' => $request->name,
            'description' => $request->description,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        if ($shift) {
            return response()->json($shift, 201);
        } else {
            return response()->json(['message' => 'Failed to create shift'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Shift $shift)
    {
        return Shift::findOrFail($shift->id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Shift $shift)
    {
        $shift->update([
            'name' => $request->name,
            'description' => $request->description,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        if ($shift) {
            return response()->json($shift, 200);
        } else {
            return response()->json(['message' => 'Failed to update shift'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Shift $shift)
    {
        if ($shift->delete()) {
            return response()->json(['message' => 'Shift deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete shift'], 500);
        }
    }
}