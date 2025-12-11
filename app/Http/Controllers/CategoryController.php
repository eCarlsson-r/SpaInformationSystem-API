<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Category::all();
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
        return Category::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $category = Category::updateOrCreate(
            ['id' => $id],
            $request->all()
        );

        if ($category->wasRecentlyCreated) {
            return response()->json($category, 201);
        } else if ($category->wasChanged()) {
            return response()->json($category, 200);
        } else {
            return response()->json(['message' => 'Failed to update category'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        if ($category->delete()) {
            return response()->json(['message' => 'Category deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete category'], 500);
        }
    }
}
