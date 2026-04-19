<?php

namespace App\Http\Controllers;

use App\Models\Walkin;
use Illuminate\Http\Request;

class WalkinController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Walkin::with(['customer', 'treatment'])
        ->whereNull('session_id')
        ->get()
        ->map(function ($walkin) {
            return [
                // Frontend Select components work best with string values
                'value' => $walkin->id,
                'customer_id' => $walkin->customer_id,
                'treatment_id' => $walkin->treatment_id,
                // Format: "Aromatherapy Massage... atas nama Umum"
                'label' => sprintf(
                    '%s atas nama %s',
                    $walkin->treatment->name ?? 'Unknown Treatment',
                    $walkin->customer->name ?? 'Umum'
                ),
            ];
        });
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
        return Walkin::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Walkin $walkin)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Walkin $walkin)
    {
        //
    }
}
