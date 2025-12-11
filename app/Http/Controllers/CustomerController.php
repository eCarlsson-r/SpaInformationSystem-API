<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Customer::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $customer = Customer::create($request->all());
        
        if ($customer) {
            return response()->json($customer, 201);
        } else {
            return response()->json(['message' => 'Failed to create customer'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Customer::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        if ($customer->update($request->all())) {
            return response()->json($customer, 200);
        } else {
            return response()->json(['message' => 'Failed to update customer'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        if ($customer->delete()) {
            return response()->json(['message' => 'Customer deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete customer'], 500);
        }
    }
}
