<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use Illuminate\Http\Request;

class CashAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return CashAccount::all();
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
        return CashAccount::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CashAccount $cashAccount)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CashAccount $cashAccount)
    {
        //
    }
}
