<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Customer;
use App\Models\Files;
use App\Models\Employee;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Handle login logic here
        $credentials = $request->only('username', 'password');
        $user = User::where('username', $request->username)->first();

        if (auth()->attempt($credentials)) {
            if ($request->header('Origin') === env('STORE_URL') && $user->type == 'CUSTOMER') {
                $customer = Customer::where('user_id', $user->id)->first();
                $token = auth()->user()->createToken('web-token')->plainTextToken;
                if ($customer) $user->customer = $customer;
                return response()->json(['data' => $user, 'token' => $token, 'type' => $user->type], 200);
            } else if ($request->header('Origin') === env('POS_URL') && $user->type != 'CUSTOMER') {
                $employee = Employee::where('user_id', $user->id)->first();
                $token = auth()->user()->createToken('admin-token')->plainTextToken;
                if ($employee) $user->employee = $employee;
                return response()->json(['data' => $user, 'token' => $token, 'type' => $user->type], 200);
            } else {
                auth()->logout();
                return response()->json(['message' => 'Username or Password is invalid'], 401);
            }
        } else if (!$user) {
            return response()->json(['message' => 'No account exist with the given username.'], 401);
        } else if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password is incorrect.'], 401);
        } else {
            return response()->json(['message' => 'Username or Password is invalid'], 401);
        }
    }

    public function user(Request $request)
    {
        if ($request->header('Origin') === env('STORE_URL') && $request->user()->type == 'CUSTOMER') {
            return $request->user()->load('customer');
        } else if ($request->header('Origin') === env('POS_URL') && $request->user()->type != 'CUSTOMER') {
            return $request->user()->load('employee');
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out of the system.'], 200);
    }

    public function subscribe(Request $request)
    {
        $user = User::find($request->input('user_id'));
        $user->updatePushSubscription(
            $request->input('endpoint'), 
            $request->input('public_key'), 
            $request->input('auth_token'), 
            $request->input('content_encoding')
        );

        return response()->json(['success' => true]);
    }

    public function register(Request $request) {
        $customer = Customer::create([
            'name' => $request->input('name'),
            'gender' => $request->input('gender'),
            'date_of_birth' => $request->input('date_of_birth'),
            'place_of_birth' => $request->input('place_of_birth'),
            'email' => $request->input('email'),
            'mobile' => $request->input('mobile'),
            'user_id' => User::create([
                'username' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'type' => 'CUSTOMER'
            ])->id,
            'address' => $request->input('address'),
            'country' => $request->input('country'),
            'province' => $request->input('province'),
            'city' => $request->input('city')
        ]);

        if ($customer) {
            return response()->json($customer, 201);
        } else {
            return response()->json(['message' => 'Failed to register'], 500);
        }
    }
}
