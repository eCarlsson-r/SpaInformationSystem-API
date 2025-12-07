<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Branch::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $branch = Branch::create($request->all());
        
        if ($branch) {
            return response()->json($branch, 201);
        } else {
            return response()->json(['message' => 'Failed to create branch'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Branch::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Branch $branch)
    {
        if ($branch->update($request->all())) {
            return response()->json($branch, 200);
        } else {
            return response()->json(['message' => 'Failed to update branch'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch)
    {
        if ($branch->delete()) {
            return response()->json(['message' => 'Branch deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete branch'], 500);
        }
    }
}
