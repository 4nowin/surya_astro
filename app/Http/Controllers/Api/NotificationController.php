<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Astrologer;
use Illuminate\Support\Facades\Http;

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

  
}
