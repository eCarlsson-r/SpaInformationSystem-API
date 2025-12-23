<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use Illuminate\Http\Request;

class BonusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->input('grade')) {
            return Bonus::with('treatment')->where('grade', $request->input('grade'))->get();
        }
        else return Bonus::with('treatment')->get();
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
    public function show(Bonus $bonus)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, String $id)
    {
        $bonus = Bonus::findOrFail($id);
        if ($bonus->update($request->all())) {
            return response()->json($bonus, 200);
        } else {
            return response()->json(['message' => 'Failed to update bonus'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(String $id)
    {
        $bonus = Bonus::findOrFail($id);
        if ($bonus->delete()) {
            return response()->json(['message' => 'Bonus deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete bonus'], 500);
        }
    }
}
