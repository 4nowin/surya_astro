<?php

namespace App\Services;

use Google\Auth\OAuth2;
use Illuminate\Support\Facades\Http;

class FirebaseService
{
    protected $clientEmail;
    protected $privateKey;
    protected $projectId;

    public function __construct()
    {
        $json = json_decode(file_get_contents(storage_path('app/firebase/navgarah-bba2c-firebase-adminsdk-fbsvc-a2292dc6bc.json')), true);
        $this->clientEmail = $json['client_email'];
        $this->privateKey = $json['private_key'];
        $this->projectId = $json['project_id'];
    }

    protected function getAccessToken()
    {
        $oauth = new OAuth2([
            'audience' => 'https://oauth2.googleapis.com/token',
            'issuer' => $this->clientEmail,
            'signingAlgorithm' => 'RS256',
            'signingKey' => $this->privateKey,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ]);

        $token = $oauth->fetchAuthToken();

        return $token['access_token'] ?? null;
    }

    public function sendNotification($token, $title, $body)
    {
        $accessToken = $this->getAccessToken();

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $response = Http::withToken($accessToken)
            ->post($url, [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'android' => [
                        'priority' => 'high',
                    ],
                ]
            ]);

        return $response->json();
    }
}
