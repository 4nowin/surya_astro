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
      'astrologer_id' => 'required|string',
      'fcm_token' => 'required|string',
    ]);

    Astrologer::updateOrInsert(
      ['id' => $request->astrologer_id],
      ['fcm_token' => $request->fcm_token, 'updated_at' => now()]
    );

    return response()->json(['status' => 'success']);
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
    $credentialsPath = config('services.firebase.credentials_path');
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
    $path = config('services.firebase.credentials_path');

    $client = new Google_Client();
    $client->setAuthConfig($path);
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
    $client->useApplicationDefaultCredentials();

    $token = $client->fetchAccessTokenWithAssertion();

    return Response::json([
      'access_token' => $token['access_token'],
    ]);
  }

  public function sendToAstrologer(Request $request)
  {
    // Add this log
    \Log::info('🔔 [LARAVEL] Received notification request', $request->all());

    $request->validate([
      'astrologer_id' => 'required|string',
      'title' => 'required|string',
      'body' => 'required|string',
      'data' => 'sometimes|array',
    ]);

    $astrologer = Astrologer::find($request->astrologer_id);
    if (!$astrologer || !$astrologer->fcm_token) {
      // Add this log
      \Log::warning('🔔 [LARAVEL] Astrologer not found or no FCM token', [
        'astrologer_id' => $request->astrologer_id,
        'found' => $astrologer ? true : false,
        'has_token' => $astrologer && $astrologer->fcm_token ? true : false
      ]);

      return response()->json(['error' => 'Astrologer not found or no token'], 404);
    }

    // Add this log
    \Log::info('🔔 [LARAVEL] Found astrologer with FCM token', [
      'astrologer_id' => $astrologer->id,
      'fcm_token' => $astrologer->fcm_token
    ]);

    $firebase = new FirebaseService();
    $result = $firebase->sendToToken(
      $astrologer->fcm_token,
      $request->title,
      $request->body,
      $request->data ?? []
    );

    // Add this log
    \Log::info('🔔 [LARAVEL] FCM result', ['result' => $result]);

    return $result;
  }
}
