<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CartRecords;

class CartController extends Controller
{
    public function index(Request $request) {
        return CartRecords::with(['treatment', 'employee', 'room'])
        ->where('customer_id', auth()->user()->customer->id)
        ->get();
    }

    public function bookSession(Request $request) {
        $cart = CartRecords::create([
            "customer_id" => auth()->user()->customer->id,
            "treatment_id" => $request->input("treatment_id"),
            "room_id" => $request->input("room_id"),
            "employee_id" => $request->input("employee_id"),
            "quantity" => $request->input("quantity"),
            "price" => $request->input("price"),
            "session_type" => $request->input("session_type"),
            "session_date" => $request->input("session_date"),
            "session_time" => $request->input("session_time")
        ]);
        
        if ($cart) {
            return response()->json($cart, 201);
        } else {
            return response()->json(['message' => 'Failed to create cart'], 500);
        }
    }

    public function buyVoucher(Request $request) {
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

    public function destroy(CartRecords $cart) {
        if ($cart->delete()) {
            return response()->json(['message' => 'Cart deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete cart'], 500);
        }
    }
}