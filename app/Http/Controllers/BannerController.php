<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Banner::all();
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

        $banner = Banner::create($createData);
        
        if ($banner) {
            return response()->json($banner, 201);
        } else {
            return response()->json(['message' => 'Failed to create banner'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(String $id)
    {
        return Banner::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Banner $banner)
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

        if ($banner->update($updateData)) {
            return response()->json($banner, 200);
        } else {
            return response()->json(['message' => 'Failed to update banner'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Banner $banner)
    {
        if ($banner->delete()) {
            return response()->json(['message' => 'Banner deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete banner'], 500);
        }
    }
}
