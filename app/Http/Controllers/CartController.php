<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CartRecords;

class CartController extends Controller
{
    public function index(Request $request) {
        abort_unless($request->user()->customer, 403, 'A customer account is required.');
        return CartRecords::with(['treatment', 'employee', 'room'])
        ->where('customer_id', auth()->user()->customer->id)
        ->get();
    }

    public function bookSession(Request $request) {
        abort_unless($request->user()->customer, 403, 'A customer account is required.');
        $data = $request->validate([
            'treatment_id' => 'required|integer|exists:treatments,id',
            'employee_id' => 'required|integer|exists:employees,id',
            'room_id' => 'required|integer|exists:rooms,id',
            'session_date' => 'required|date|after_or_equal:today',
            'session_time' => 'required|date_format:H:i',
        ]);
        $treatment = \App\Models\Treatment::findOrFail($data['treatment_id']);
        $employee = \App\Models\Employee::findOrFail($data['employee_id']);
        $room = \App\Models\Room::findOrFail($data['room_id']);
        abort_unless((int) $employee->branch_id === (int) $room->branch_id, 422, 'The therapist and room must belong to the same branch.');

        $cart = CartRecords::create([
            "customer_id" => auth()->user()->customer->id,
            "treatment_id" => $treatment->id,
            "room_id" => $room->id,
            "employee_id" => $employee->id,
            "quantity" => 1,
            "price" => $treatment->price,
            "session_type" => 'walkin',
            "session_date" => $data["session_date"],
            "session_time" => $data["session_time"]
        ]);
        
        if ($cart) {
            return response()->json($cart, 201);
        } else {
            return response()->json(['message' => 'Failed to create cart'], 500);
        }
    }

    public function buyVoucher(Request $request) {
        abort_unless($request->user()->customer, 403, 'A customer account is required.');
        $customer = auth()->user()->customer->id;
        $treatment = $request->input("treatment_id");
        $quantity = $request->input("quantity");
        $price = $request->input("price");
        $normal_quantity = $request->input("voucher_normal_quantity");
        $purchase_quantity = $request->input("voucher_purchase_quantity");

        $existingCart = CartRecords::where("customer_id", $customer)
            ->where("treatment_id", $treatment)
            ->first();

        if ($existingCart) {
            $existingCart->update([
                "quantity" => $existingCart->quantity + $quantity,
                "price" => $existingCart->price + $price
            ]);
            return response()->json($existingCart, 200);
        } else {
            $cart = CartRecords::create([
                "customer_id" => $customer,
                "treatment_id" => $treatment,
                "session_type" => "voucher",
                "quantity" => $quantity,
                "price" => $price,
                "voucher_normal_quantity" => $normal_quantity,
                "voucher_purchase_quantity" => $purchase_quantity
            ]);

            if ($cart) {
                return response()->json($cart->with('treatment'), 201);
            } else {
                return response()->json(['message' => 'Failed to create cart'], 500);
            }
        }
    }

    public function destroy(Request $request, CartRecords $cart) {
        abort_unless((int) $cart->customer_id === (int) $request->user()->customer?->id, 404);
        if ($cart->delete()) {
            return response()->json(['message' => 'Cart deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete cart'], 500);
        }
    }
}
