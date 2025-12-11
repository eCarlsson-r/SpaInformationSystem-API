<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;

class BankController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Bank::all();
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
        return Bank::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $bank = Bank::updateOrCreate(
            ['id' => $id],
            $request->all()
        );

        if ($bank->wasRecentlyCreated) {
            return response()->json($bank, 201);
        } else if ($bank->wasChanged()) {
            return response()->json($bank, 200);
        } else {
            return response()->json(['message' => 'Failed to update bank'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bank $bank)
    {
        if ($bank->delete()) {
            return response()->json(['message' => 'Bank deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete bank'], 500);
        }
    }
}
