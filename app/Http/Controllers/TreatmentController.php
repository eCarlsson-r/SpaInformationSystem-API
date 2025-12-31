<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use Illuminate\Http\Request;

class TreatmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->has('vouchers')) {
            $query = Treatment::where('voucher_normal_quantity', '>', 0)->get();
            if ($query->isEmpty()) {
                return response()->json(['message' => 'No vouchers found'], 404);
            }
            return $query;
        } else {
            $query = Treatment::query();

            $query->when(request()->has('id'), function ($query) {
                return $query->with('category')->where('id', request('id'));
            });

            $query->when(request()->has('active_only'), function ($query) {
                return $query->with('category')->whereColumn('applicable_time_start', '<>', 'applicable_time_end');
            });

            return $query->with('category')->get();
        }
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $createData = $request->all();

        if ($request->hasFile('body_img') && $request->file('body_img')->isValid()) {
            $path = $request->file('body_img')->storePubliclyAs(
                'images', 
                $request->file('body_img')->getClientOriginalName(), 
                'public'
            );
            $createData['body_img'] = Storage::url($path);
        }

        if ($request->hasFile('icon_img') && $request->file('icon_img')->isValid()) {
            $path = $request->file('icon_img')->storePubliclyAs(
                'images', 
                $request->file('icon_img')->getClientOriginalName(), 
                'public'
            );
            $createData['icon_img'] = Storage::url($path);
        }
        
        $treatment = Treatment::create($createData);

        if ($treatment) {
            return response()->json($treatment, 201);
        } else {
            return response()->json(['message' => 'Failed to create treatment'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Treatment::with('category')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $treatment = Treatment::updateOrCreate(
            ['id' => $id],
            $request->all()
        );

        if ($treatment->wasRecentlyCreated) {
            return response()->json($treatment, 201);
        } else if ($treatment->wasChanged()) {
            return response()->json($treatment, 200);
        } else {
            return response()->json(['message' => 'Failed to update treatment'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Treatment $treatment)
    {
        if ($treatment->delete()) {
            return response()->json(['message' => 'Treatment deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete treatment'], 500);
        }
    }
}
