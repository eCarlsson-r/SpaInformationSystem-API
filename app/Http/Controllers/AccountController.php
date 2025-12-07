<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Account::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $account = Account::create($request->all());
        
        if ($account) {
            return response()->json($account, 201);
        } else {
            return response()->json(['message' => 'Failed to create account'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Account::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Account $account)
    {
        if ($account->update($request->all())) {
            return response()->json($account, 200);
        } else {
            return response()->json(['message' => 'Failed to update account'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Account $account)
    {
        if ($account->delete()) {
            return response()->json(['message' => 'Account deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete account'], 500);
        }
    }
}
