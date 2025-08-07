<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Astrologer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Google\Client as Google_Client;
use App\Services\FirebaseService;

class NotificationController extends Controller
{

  public function saveToken(Request $request)
  {
    $request->validate([
      'astrologer_id' => 'required|integer',
      'fcm_token' => 'required|string',
    ]);

    // Find the astrologer
    $astrologer = Astrologer::find($request->astrologer_id);

    if (!$astrologer) {
      return response()->json(['error' => 'Astrologer not found'], 404);
    }

    // Update the FCM token in the admins table
    $admin = $astrologer->admin;
    $admin->fcm_token = $request->fcm_token;
    $admin->save();

    return response()->json(['success' => true, 'message' => 'FCM token saved successfully']);
  }

  function getGoogleAccessToken($jsonKey)
  {
    $client = new \Google_Client();
    $client->setAuthConfig(json_decode($jsonKey, true));
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
    $client->fetchAccessTokenWithAssertion();
    return $client->getAccessToken()['access_token'];
  }

  function sendFCMTopicNotification($topic, $title, $body, $data = [])
  {
    $credentialsPath = storage_path('app/' . config('services.firebase.credentials_path'));
    if (!$credentialsPath) {
      Log::error("FIREBASE_CREDENTIALS_PATH is not set in config");
      return response()->json(['error' => 'Server misconfiguration.'], 500);
    }
    $accessToken = getGoogleAccessToken($credentialsPath);

    $message = [
      'message' => [
        'topic' => $topic, // e.g. 'horoscope-en'
        'notification' => [
          'title' => $title,
          'body' => $body,
        ],
        'data' => $data, // optional custom key-value data
      ]
    ];

    $response = Http::withToken($accessToken)
      ->post('https://fcm.googleapis.com/v1/projects/YOUR_PROJECT_ID/messages:send', $message);

    return $response->json();
  }

  public function getAccessToken()
  {
    $path = storage_path('app/' . config('services.firebase.credentials_path'));

    $client = new Google_Client();
    $client->setAuthConfig($path);
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
    $client->useApplicationDefaultCredentials();

    $token = $client->fetchAccessTokenWithAssertion();

    return Response::json([
      'access_token' => $token['access_token'],
    ]);
  }

  /**
   * Send notification to astrologer
   */
  public function sendToAstrologer(Request $request)
  {
    $request->validate([
      'astrologer_id' => 'required|string',
      'title' => 'required|string',
      'body' => 'required|string',
      'data' => 'sometimes|array',
    ]);

    $astrologer = Astrologer::find($request->astrologer_id);

    if (!$astrologer || !$astrologer->admin || !$astrologer->admin->fcm_token) {
      \Log::warning('🔔 [LARAVEL] Astrologer not found or no FCM token', [
        'astrologer_id' => $request->astrologer_id,
        'found' => $astrologer ? true : false,
        'has_admin' => $astrologer && $astrologer->admin ? true : false,
        'has_token' => $astrologer && $astrologer->admin && $astrologer->admin->fcm_token ? true : false
      ]);

      return response()->json(['error' => 'Astrologer not found or no token'], 404);
    }

    $fcmToken = $astrologer->admin->fcm_token;

    \Log::info('🔔 [LARAVEL] Found astrologer with FCM token', [
      'astrologer_id' => $astrologer->id,
      'admin_id' => $astrologer->admin->id,
      'fcm_token' => $fcmToken
    ]);

    $firebase = new FirebaseService();

    // Ensure the data includes the required fields for navigation
    $data = $request->data ?? [];
    $data['type'] = $data['type'] ?? 'chat_message';

    \Log::info('🔔 [LARAVEL] Sending notification with data', $data);

    $result = $firebase->sendToToken(
      $fcmToken,
      $request->title,
      $request->body,
      $data
    );

    \Log::info('🔔 [LARAVEL] FCM result', ['result' => $result]);

    return $result;
  }
}
