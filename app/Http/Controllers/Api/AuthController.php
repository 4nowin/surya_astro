<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Google_Client;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{

  public function login($lang = 'en', Request $request)
  {
    // Validate the request
    $request->validate([
      'email' => 'required|string',
      'password' => 'required|string',
    ]);

    // Check if user exists
    $user = User::where('email', $request->email)->first();

    if (!$user) {
      return response()->json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }

    if (!$user || !Hash::check($request->password, $user->password)) {
      return response()->json([
        'success' => false,
        'message' => 'Invalid credentials'
      ], 401);
    }

    if ($user && $user->status === 'blocked') {
      return response()->json([
        'success' => false,
        'message' => 'Your account has been blocked. Contact support.'
      ], 403);
    }

    // Generate API token for the user
    $token = $user->createToken('authToken')->plainTextToken;
    $user->update(['language' => $lang]);

    return response()->json([
      'success' => true,
      'message' => 'Login successful',
      'token' => $token,
      'user' => [
        'id' => $user->id,
        'name' => $user->name,
        'language' => $user->language,
        'wallet_balance' => $user->wallet_balance,
        'email' => $user->email,
        'mobile' => $user->mobile,
        'gender' => $user->gender,
        'birth_time' => $user->birth_time,
        'date_of_birth' => $user->date_of_birth,
        'place_of_birth' => $user->place_of_birth,
        'country' => $user->country,
      ]
    ], 200);
  }

  public function loginWithGoogle(Request $request)
  {
    $request->validate([
      'id_token' => 'required|string',
      'email' => 'required|email',
      'name' => 'nullable|string',
      'photo_url' => 'nullable|string',
    ]);

    $client = new Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]); // Client ID from Google Console
    $payload = $client->verifyIdToken($request->id_token);

    if (!$payload) {
      return response()->json(['error' => 'Invalid ID token'], 401);
    }

    $email = $payload['email'];
    $name = $payload['name'] ?? '';

    $user = User::updateOrCreate(
      ['email' => $request->email],
      [
        'name' => $request->name,
        'profile_image' => $request->photo_url,
      ]
    );

    $token = $user->createToken('google_token')->plainTextToken;

    return response()->json([
      'token' => $token,
      'user' => $user,
    ]);
  }

  public function redirectToGoogle()
  {
    return Socialite::driver('google')->stateless()->redirect();
  }

  public function handleGoogleCallback()
  {
    $googleUser = Socialite::driver('google')->stateless()->user();

    $email = $googleUser->getEmail();
    $name = $googleUser->getName();
    $avatar = $googleUser->getAvatar();

    // Create or update the user
    $user = User::updateOrCreate(
      ['email' => $email],
      [
        'name' => $name,
        'profile_image' => $avatar,
      ]
    );

    // Generate a token
    $token = $user->createToken('google_token')->plainTextToken;

    // Redirect to frontend with token as query param
    return redirect()->away(env('APP_URL')."/oauth-success?token=$token");
  }

  public function logout(Request $request)
  {
    $request->user()->currentAccessToken()->delete();

    return response()->json([
      'success' => true,
      'message' => 'Logged out successfully',
    ]);
  }
}
