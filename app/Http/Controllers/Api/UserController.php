<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Google_Client;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{

  public function updateProfile(Request $request)
  {
    Log::debug('Request payload:', $request->all());
    $user = $request->user();

    // Handle image upload separately
    if ($request->hasFile('image')) {
      $path = $request->file('image')->store('profile_images', 'public');
      $user->image = asset('storage/' . $path);
      $user->save(); // Save image change
    }

    $input = $request->all();
    foreach (['dob', 'birth_time', 'pob', 'phone', 'country', 'gender'] as $field) {
      if (isset($input[$field]) && $input[$field] === '') {
        $input[$field] = null;
      }
    }
    $request->replace($input);

    // Validate only the fields that are present
    $validated = $request->validate([
      'name' => 'sometimes|required|string|max:255',
      'phone' => 'sometimes|nullable|string|max:20',
      'gender' => 'sometimes|nullable|string',
      'dob' => 'sometimes|nullable|date',
      'pob' => 'sometimes|nullable|string|max:255',
      'birth_time' => 'sometimes|nullable|string',
      'country' => 'sometimes|nullable|string|max:255',
    ]);

    // Update only the validated fields
    if (!empty($validated)) {
      $user->update($validated);
    }

    if (empty($validated) && !$request->hasFile('image')) {
      return response()->json([
        'success' => false,
        'message' => 'No data to update'
      ], 422);
    }

    Log::info('User updated their profile', ['user_id' => $user->id]);
    return response()->json([
      'success' => true,
      'message' => 'Profile updated successfully',
      'user' => $user
    ]);
  }

  public function userProfile()
  {
    return response()->json(auth()->user());
  }

  public function updateFcmToken(Request $request)
  {
    $request->validate([
      'fcm_token' => 'required|string',
    ]);

    $user = $request->user();

    $user->fcm_token = $request->fcm_token;
    $user->save();

    return response()->json(['status' => 'token_updated']);
  }
}
