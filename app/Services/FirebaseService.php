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
        // Add this log
        \Log::info('🔔 [LARAVEL] Preparing to send FCM notification', [
            'token' => $token,
            'title' => $title,
            'body' => $body,
            'data' => $data
        ]);

        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'chat_channel',
                        'sound' => 'default',
                        'priority' => 'high',
                        'default_sound' => true,
                        'default_vibrate_timings' => true,
                    ]
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                            'content-available' => 1,
                        ],
                    ],
                ],
            ]
        ];

        // Add this log
        \Log::info('🔔 [LARAVEL] FCM message payload', $message);

        $result = $this->send($message);

        // Add this log
        \Log::info('🔔 [LARAVEL] FCM response', ['response' => $result]);

        return $result;
    }

    protected function send($message)
    {
        try {
            $accessToken = $this->getAccessToken();
            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            // Add these logs
            \Log::info('🔔 [LARAVEL] FCM request URL: ' . $url);
            \Log::info('🔔 [LARAVEL] Access token obtained successfully');
            \Log::info('🔔 [LARAVEL] Sending FCM request', ['message' => $message]);

            $response = Http::withToken($accessToken)
                ->post($url, $message);

            // Add these logs
            \Log::info('🔔 [LARAVEL] FCM HTTP response status: ' . $response->status());
            \Log::info('🔔 [LARAVEL] FCM HTTP response body: ' . $response->body());

            return $response->json();
        } catch (\Exception $e) {
            // Add this log
            \Log::error('🔔 [LARAVEL] Error sending FCM notification: ' . $e->getMessage());
            throw $e;
        }
    }
}
