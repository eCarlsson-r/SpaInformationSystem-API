<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Employee;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Handle login logic here
        $credentials = $request->only('username', 'password');
        $user = User::where('username', $request->username)->first();
        $employee = Employee::where('user_id', $user->id)->first();

        if (auth()->attempt($credentials)) {
            if ($employee) return response()->json(['data' => $user, 'employee' => $employee], 200);
            else return response()->json(['data' => $user], 200);
        } else if (!$user) {
            return response()->json(['message' => 'No account exist with the given username.'], 401);
        } else if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password is incorrect.'], 401);
        } else {
            return response()->json(['message' => 'Username or Password is invalid'], 401);
        }
    }
}
