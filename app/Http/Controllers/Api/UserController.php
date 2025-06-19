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

    $localizedToEnum = [
      'पुरुष' => 'male',
      'महिला' => 'female',
      'अन्य' => 'other',
      'नहीं कहना पसंद करते हैं' => 'prefer_not_to_say',
      'अनुपलब्ध' => null,
    ];

    $requestData = $request->all();

    // Map gender
    if (!empty($requestData['gender']) && isset($localizedToEnum[$requestData['gender']])) {
      $requestData['gender'] = $localizedToEnum[$requestData['gender']];
    }

    // Handle image upload separately
    if ($request->hasFile('image')) {
      Log::info('Profile update request contains an image.', [
        'user_id' => $request->user()->id
      ]);
      $path = $request->file('image')->store('profile_images', 'public');
      $user->profile_image = asset('storage/' . $path);
      $user->save(); // Save image change
    }

    // Map dob, pob, birth_time, country, etc.
    $fieldsToNormalize = ['date_of_birth', 'place_of_birth', 'birth_time', 'phone', 'country', 'gender'];
    foreach ($fieldsToNormalize as $field) {
      if (!empty($requestData[$field]) && in_array($requestData[$field], ['अनुपलब्ध', ''])) {
        $requestData[$field] = null;
      }
    }
    $request->replace($requestData);

    // Validate only the fields that are present
    $validated = $request->validate([
      'name' => 'sometimes|required|string|max:255',
      'phone' => 'sometimes|nullable|string|max:20',
      'gender' => 'sometimes|nullable|in:male,female,other,prefer_not_to_say',
      'date_of_birth' => 'sometimes|nullable|date',
      'place_of_birth' => 'sometimes|nullable|string|max:255',
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

    Log::info('User updated their profile', [
      'user_id' => $user->id,
      'updated_fields' => array_keys($validated)
    ]);
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
