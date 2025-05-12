<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

  public function login(Request $request, $id)
  {
    // Validate the request
    $request->validate([
      'email' => 'required|string',
      'password' => 'required|string',
    ]);

    // Check if user exists
    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
      return response()->json([
        'success' => false,
        'message' => 'Invalid credentials'
      ], 401);
    }

    // Generate API token for the user
    $token = $user->createToken('authToken')->plainTextToken;

    return response()->json([
      'success' => true,
      'message' => 'Login successful',
      'token' => $token,
      'user' => [
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'role' => $user->role,
      ]
    ], 200);
  }

}
