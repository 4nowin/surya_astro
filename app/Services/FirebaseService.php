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
        $json = json_decode(
            file_get_contents(storage_path(config('services.firebase.credentials_path'))),
            true
        );

        $this->clientEmail = $json['client_email'];
        $this->privateKey = str_replace("\\n", "\n", $json['private_key']);
        $this->projectId = config('services.firebase.project_id');
    }

    protected function getAccessToken()
    {
        $oauth = new OAuth2([
            'audience' => 'https://oauth2.googleapis.com/token',
            'issuer' => $this->clientEmail,
            'signingAlgorithm' => 'RS256',
            'signingKey' => $this->privateKey,
            'tokenCredentialUri' => 'https://oauth2.googleapis.com/token', // ✅ Add this
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ]);

        $token = $oauth->fetchAuthToken();

        if (!isset($token['access_token'])) {
            throw new \Exception('Unable to fetch Firebase access token');
        }

        return $token['access_token'];
    }

    public function sendNotification($token, $title, $body)
    {
        $accessToken = $this->getAccessToken();

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $response = Http::withToken($accessToken)->post($url, [
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

    public function sendToTopic($topic, $title, $body, $data = [])
    {

        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
            ],
        ];

        return $this->send($message);
    }

    public function sendToToken($token, $title, $body, $data = [])
    {
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
            ]
        ];

        return $this->send($message);
    }

    protected function send($message)
    {
        $accessToken = $this->getAccessToken();

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $response = Http::withToken($accessToken)
            ->post($url, $message);

        return $response->json();
    }
}
