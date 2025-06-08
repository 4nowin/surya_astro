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

  private function authResponse(User $user, string $token)
  {
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
        'role' => $user->role,
        'status' => $user->status,
        'fcm_token' => $user->fcm_token,
        'referral_code' => $user->referral_code,
        'profile_image' => $user->profile_image,
      ]
    ], 200);
  }

  public function login($lang = 'en', Request $request)
  {
    // Validate the request
    $request->validate([
      'email' => 'required|string',
      'password' => 'required|string',
    ]);

    $email = trim($request->email);

    // Check if user exists
    $user = User::where('email', $email)->first();

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

    if (!$user->referral_code) {
      $user->referral_code = User::generateReferralCode();
      $user->save();
    }

    if ($request->has('referred_by')) {
      $referrer = User::where('referral_code', $request->input('referred_by'))->first();
      if ($referrer) {
        $user->referred_by = $referrer->referral_code;
        $referrer->wallet_balance += 10; // reward
        $referrer->save();
      }
    }

    // Generate API token for the user
    $token = $user->createToken('authToken')->plainTextToken;
    $user->update(['language' => $lang]);

    return $this->authResponse($user, $token);
  }

  public function loginWithGoogle($lang = 'en', Request $request)
  {
    \Log::info('Request data for google login', $request->all());
    $request->validate([
      'id_token' => 'required|string',
      'email' => 'required|email',
      'name' => 'nullable|string',
      'photo_url' => 'nullable|string',
    ]);

    $client = new Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]); // Client ID from Google Console
    $payload = $client->verifyIdToken($request->id_token);

    if (!$payload) {
      return response()->json(['success' => false, 'message' => 'Invalid ID token'], 401);
    }


    \Log::info('Google login payload', $payload);

    $user = User::updateOrCreate(
      ['email' => $request->email],
      [
        'name' => $request->name,
        'profile_image' => $request->photo_url,
        'language' => $lang,
      ]
    );

    if (!$user->referral_code) {
      $user->referral_code = User::generateReferralCode();
      $user->save();
    }

    if ($request->has('referred_by')) {
      $referrer = User::where('referral_code', $request->input('referred_by'))->first();
      if ($referrer) {
        $user->referred_by = $referrer->referral_code;
        $referrer->wallet_balance += 10; // reward
        $referrer->save();
      }
    }

    if ($user && $user->status === 'blocked') {
      return response()->json([
        'success' => false,
        'message' => 'Your account has been blocked. Contact support.'
      ], 403);
    }

    $token = $user->createToken('google_token')->plainTextToken;

    return $this->authResponse($user, $token);
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
    return redirect()->away(env('APP_URL') . "/oauth-success?token=$token");
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
