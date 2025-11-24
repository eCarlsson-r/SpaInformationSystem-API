<?php

namespace App\Http\Controllers;

use App\Models\Walkin;
use Illuminate\Http\Request;

class WalkinController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Walkin::where('session_id', '0');
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
        return Walkin::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Walkin $walkin)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Walkin $walkin)
    {
        //
    }
}
