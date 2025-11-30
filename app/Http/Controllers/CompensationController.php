<?php

namespace App\Http\Controllers;

use App\Models\Compensation;
use Illuminate\Http\Request;

class CompensationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Compensation::all();
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
    public function destroy(Compensation $compensation)
    {
        //
    }
}
