<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\Review;
use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Traits\HasRoles;
use Kreait\Firebase\Auth as FirebaseAuth;

class AdminAuthController extends Controller
{

  public function astrologerFirebaseToken(Request $request)
  {
    $admin = $request->user(); // Authenticated astrologer (admin)

    $uid = 'astrologer_' . $admin->id; // Unique UID for Firebase
    try {
      $auth = app(FirebaseAuth::class);
      $customToken = $auth->createCustomToken($uid);

      return response()->json([
        'firebase_token' => $customToken->toString(),
      ]);
    } catch (\Throwable $e) {
      \Illuminate\Support\Facades\Log::warning('Firebase token generation failed: ' . $e->getMessage());
      return response()->json([
        'firebase_token' => null,
        'message' => 'Firebase token currently unavailable.',
      ], 200);
    }
  }

  public function me(Request $request)
  {
    $admin = $request->user();

    if (!$admin->hasRole('Astrologer', 'admin')) {
      return response()->json(['message' => 'Unauthorized. Not an astrologer.'], 403);
    }

    $astrologer = Astrologer::where('admin_id', $admin->id)->first();

    return response()->json([
      'admin' => $admin,
      'astrologer' => $astrologer
    ]);
  }

  public function login(Request $request)
  {
    $validated = $request->validate([
      'email' => 'required|email',
      'password' => 'required|string'
    ]);

    $admin = Admin::where('email', $validated['email'])->first();

    if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
      return response()->json([
        'message' => 'The provided credentials are incorrect.',
      ], 401);
    }

    if (! $admin->hasRole('Astrologer', 'admin')) {
      return response()->json(['message' => 'You are not authorized as an astrologer.'], 403);
    }

    $token = $admin->createToken('admin-token')->plainTextToken;

    // 🔥 Firebase custom token generation (safe fallback)
    $firebaseToken = null;
    try {
      if (class_exists(\Kreait\Firebase\Auth::class)) {
        $uid = 'astrologer_' . $admin->id;
        $auth = app(\Kreait\Firebase\Auth::class);
        $firebaseToken = $auth->createCustomToken($uid)->toString();
      }
    } catch (\Throwable $e) {
      \Illuminate\Support\Facades\Log::warning('Firebase custom token generation skipped during login: ' . $e->getMessage());
    }

    $data = Astrologer::where('admin_id', $admin->id)->get();

    return response()->json([
      'message' => 'Login successful.',
      'token' => $token,
      'admin' => $admin,
      'firebase_token' => $firebaseToken,
      'data' => $data
    ]);
  }

  public function register(Request $request)
  {
    $validated = $request->validate([
      'name' => 'required|string',
      'email' => 'required|email|unique:admins,email',
      'password' => 'required|string|min:8',
    ]);

    $admin = Admin::create([
      'name' => $validated['name'],
      'email' => $validated['email'],
      'password' => bcrypt($validated['password']),
    ]);

    $astrologerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);
    $admin->assignRole($astrologerRole);

    $token = $admin->createToken('admin-token')->plainTextToken;

    return response()->json([
      'message' => 'Astrologer registered successfully.',
      'token' => $token,
      'admin' => $admin
    ]);
  }

  public function logout(Request $request)
  {
    $request->user()->tokens()->delete();
    return response()->json(['message' => 'Logged out successfully']);
  }
}
