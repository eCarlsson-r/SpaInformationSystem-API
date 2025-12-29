<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Category::with('treatment')->get();
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
        return Category::with('treatment')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $updateData = $request->all();
        if ($request->hasFile('header_img') && $request->file('header_img')->isValid()) {
            $path = $request->file('header_img')->storePubliclyAs(
                'images', 
                $request->file('header_img')->getClientOriginalName(), 
                'public'
            );

            $updateData['header_img'] = Storage::url($path);
        }

        if ($request->hasFile('body_img1') && $request->file('body_img1')->isValid()) {
            $path = $request->file('body_img1')->storePubliclyAs(
                'images', 
                $request->file('body_img1')->getClientOriginalName(), 
                'public'
            );

            $updateData['body_img1'] = Storage::url($path);
        }

        if ($request->hasFile('body_img2') && $request->file('body_img2')->isValid()) {
            $path = $request->file('body_img2')->storePubliclyAs(
                'images', 
                $request->file('body_img2')->getClientOriginalName(), 
                'public'
            );

            $updateData['body_img2'] = Storage::url($path);
        }

        if ($request->hasFile('body_img3') && $request->file('body_img3')->isValid()) {
            $path = $request->file('body_img3')->storePubliclyAs(
                'images', 
                $request->file('body_img3')->getClientOriginalName(), 
                'public'
            );

            $updateData['body_img3'] = Storage::url($path);
        }

        $category = Category::updateOrCreate(['id' => $id], $updateData);

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
