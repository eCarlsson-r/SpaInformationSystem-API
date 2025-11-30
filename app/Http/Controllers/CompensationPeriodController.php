<?php

namespace App\Http\Controllers;

use App\Models\CompensationPeriod;
use Illuminate\Http\Request;

class CompensationPeriodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return CompensationPeriod::get();
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
    public function show(CompensationPeriod $compensationPeriod)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CompensationPeriod $compensationPeriod)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CompensationPeriod $compensationPeriod)
    {
        //
    }
}
