<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Files;
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
            $token = auth()->user()->createToken('pos-token')->plainTextToken;
            if ($employee) return response()->json(['data' => $user, 'employee' => $employee, 'token' => $token], 200);
            else return response()->json(['data' => $user, 'token' => $token], 200);
        } else if (!$user) {
            return response()->json(['message' => 'No account exist with the given username.'], 401);
        } else if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password is incorrect.'], 401);
        } else {
            return response()->json(['message' => 'Username or Password is invalid'], 401);
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

    public function upload(Request $request)
    {
        if ($request->input('files')) {
            $filesId = array();
            foreach($request->input('files') as $fileName=>$fileURI) {
                $file = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $fileURI));
                Storage::disk('public')->put($fileName, $file);
                $parts = explode(';', $fileURI);
                if (count($parts) > 1) {
                    $mime_type_part = $parts[0];
                    $mime_type = str_replace('data:', '', $mime_type_part);
                }

                $url = Storage::disk('public')->url($fileName);
                $document = Files::create([
                    'name' => $fileName,
                    'path' => $url,
                    'type' => $mime_type,
                    'size' => Storage::disk('public')->size($fileName)
                ]);
                
                array_push($filesId, $document->id);
            }

            if (count($filesId) > 0) return response()->json(['files_id' => $filesId], 201);
            else return response()->json(['message' => 'Failed to upload files.'], 500);
        } else {
            print_r($request->all());
            $decodedFile = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->input('file')));
            if ($decodedFile) {
                // Save the file to the default storage disk (e.g., 'local' or 's3')
                Storage::disk('public')->put($request->name, $decodedFile);

                // Optional: Get the public URL of the stored file
                $url = Storage::disk('public')->url($request->name);
                $document = Files::create([
                    'name' => $request->name,
                    'path' => $url,
                    'type' => $request->type,
                    'size' => Storage::disk('public')->size($request->name)
                ]);

                if ($document) return response()->json(['file_id' => $document->id], 201);
                else return response()->json(['message' => 'Failed to upload file.'], 500);
            } else {
                return response()->json(['message' => 'Failed to decode file.'], 500);
            }
        }
    }
}
