<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Wallet::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $wallet = Wallet::create($request->all());
        
        if ($wallet) {
            return response()->json($wallet, 201);
        } else {
            return response()->json(['message' => 'Failed to create wallet'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Wallet $wallet)
    {
        return $wallet;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Wallet $wallet)
    {
        if ($wallet->update($request->all())) {
            return response()->json($wallet, 200);
        } else {
            return response()->json(['message' => 'Failed to update wallet'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Wallet $wallet)
    {
        if ($wallet->delete()) {
            return response()->json(['message' => 'Wallet deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete wallet'], 500);
        }
    }
}
