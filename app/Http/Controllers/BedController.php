<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BedController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->input("show") == "session") {
            return Bed::where("room_id", $request->input("room_id"))
                ->leftJoin('sessions', function ($join) {
                    $join->on('beds.id', '=', 'sessions.bed_id')
                        ->whereIn('sessions.status', ['ongoing', 'waiting']);
                })->leftJoin('employees', 'sessions.employee_id', '=', 'employees.id')
                ->select('beds.*', 'sessions.*', 'employees.name as employee_name')->get();
        } else return Bed::all();
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
    public function show(string $id)
    {
        return Bed::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bed $bed)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bed $bed)
    {
        //
    }
}
