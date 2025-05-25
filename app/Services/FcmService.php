<?php

namespace App\Services;

use Google_Client;
use Illuminate\Support\Facades\Http;

class FcmService
{
    protected static function getAccessToken()
    {
        return cache()->remember('fcm_access_token', 3500, function () {
            $credentialsPath = storage_path(str_replace('storage/', '', env('FIREBASE_CREDENTIALS_PATH')));

            $client = new Google_Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->fetchAccessTokenWithAssertion();

            return $client->getAccessToken()['access_token'] ?? null;
        });
    }

    public static function sendNotification($token, $title, $body, $data = [])
    {
        $accessToken = self::getAccessToken();
        $projectId = env('FIREBASE_PROJECT_ID');

        $message = [
            "message" => [
                "token" => $token,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],
                "data" => $data
            ]
        ];

        $response = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $message);

        return $response->json();
    }

    public static function sendToTopic($topic, $title, $body, $data = [])
    {
        $accessToken = self::getAccessToken();
        $projectId = env('FIREBASE_PROJECT_ID');

        $message = [
            "message" => [
                "topic" => $topic,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],
                "data" => $data
            ]
        ];

        $response = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $message);

        return $response->json();
    }
}
