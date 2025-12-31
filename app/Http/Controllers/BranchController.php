<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $createData = $request->all();
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $path = $request->file('image')->storePubliclyAs(
                'images', 
                $request->file('image')->getClientOriginalName(), 
                'public'
            );
            $createData['image'] = Storage::url($path);
        }

        $branch = Branch::create($createData);
        
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
        $updateData = $request->all();
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $path = $request->file('image')->storePubliclyAs(
                'images', 
                $request->file('image')->getClientOriginalName(), 
                'public'
            );

            $updateData['image'] = Storage::url($path);
        }

        if ($branch->update($updateData)) {
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
