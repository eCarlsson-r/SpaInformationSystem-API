<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Discount::all();
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
    public function show(Discount $discount)
    {
        return $discount;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $discount = Discount::updateOrCreate(
            ['id' => $id],
            $request->all()
        );

        if ($discount->wasRecentlyCreated) {
            return response()->json($discount, 201);
        } else if ($discount->wasChanged()) {
            return response()->json($discount, 200);
        } else {
            return response()->json(['message' => 'Failed to update discount'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Discount $discount)
    {
        if ($discount->delete()) {
            return response()->json(['message' => 'Discount deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete discount'], 500);
        }
    }
}
