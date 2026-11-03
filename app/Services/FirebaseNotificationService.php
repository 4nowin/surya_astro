<?php

declare(strict_types=1);

namespace App\Services;

use Google\Auth\OAuth2;
use Google_Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected ?string $projectId;
    protected ?string $credentialsPath;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id') ?: env('FIREBASE_PROJECT_ID');
        $this->credentialsPath = config('services.firebase.credentials_path');
    }

    /**
     * Retrieve or cache the Google OAuth2 access token for FCM HTTP v1.
     */
    public function getAccessToken(): ?string
    {
        return Cache::remember('fcm_http_v1_access_token', 3500, function () {
            try {
                if (empty($this->credentialsPath)) {
                    Log::error('Firebase credentials path is not set in configuration.');
                    return null;
                }

                $fullPath = storage_path('app/' . $this->credentialsPath);
                if (!file_exists($fullPath)) {
                    $fullPath = base_path($this->credentialsPath);
                }

                if (!file_exists($fullPath)) {
                    Log::error("Firebase credentials file not found at: {$fullPath}");
                    return null;
                }

                $json = json_decode(file_get_contents($fullPath), true);
                if (empty($json['client_email']) || empty($json['private_key'])) {
                    Log::error('Invalid Firebase service account credentials JSON.');
                    return null;
                }

                $oauth = new OAuth2([
                    'audience' => 'https://oauth2.googleapis.com/token',
                    'issuer' => $json['client_email'],
                    'signingAlgorithm' => 'RS256',
                    'signingKey' => str_replace("\\n", "\n", $json['private_key']),
                    'tokenCredentialUri' => 'https://oauth2.googleapis.com/token',
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                ]);

                $token = $oauth->fetchAuthToken();
                return $token['access_token'] ?? null;
            } catch (\Throwable $e) {
                Log::error('Failed to obtain Firebase access token: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Send a notification to a specific device registration token using FCM HTTP v1.
     *
     * @param string $token FCM Device registration token
     * @param string $title Notification headline
     * @param string $body Notification body content
     * @param array<string, mixed> $data Custom key-value payload
     * @return array{success: bool, message_id: ?string, error: ?string, unregistered: bool}
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Authentication token unavailable',
                'unregistered' => false,
            ];
        }

        if (empty($this->projectId)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Firebase project ID is not configured',
                'unregistered' => false,
            ];
        }

        // All data payload values in FCM HTTP v1 must be strings
        $stringData = [];
        foreach ($data as $k => $v) {
            if ($v !== null) {
                $stringData[(string) $k] = is_string($v) ? $v : json_encode($v);
            }
        }

        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $stringData,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'astro_setu_daily_notifications',
                        'sound' => 'default',
                        'default_sound' => true,
                        'default_vibrate_timings' => true,
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post($url, $message);

            if ($response->successful()) {
                $json = $response->json();
                return [
                    'success' => true,
                    'message_id' => $json['name'] ?? null,
                    'error' => null,
                    'unregistered' => false,
                ];
            }

            $status = $response->status();
            $errorBody = $response->json();
            $errorCode = $errorBody['error']['details'][0]['errorCode'] ?? ($errorBody['error']['status'] ?? 'UNKNOWN');
            $errorMessage = $errorBody['error']['message'] ?? $response->body();

            // Check if token is invalid or uninstalled
            $isUnregistered = in_array($status, [404, 410], true)
                || in_array($errorCode, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)
                || str_contains(strtolower($errorMessage), 'not registered');

            Log::warning("FCM send failed [{$status}] {$errorCode}: {$errorMessage}");

            return [
                'success' => false,
                'message_id' => null,
                'error' => $errorMessage,
                'unregistered' => $isUnregistered,
            ];
        } catch (\Throwable $e) {
            Log::error('FCM HTTP exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'unregistered' => false,
            ];
        }
    }

    /**
     * Send a broadcast notification to a topic.
     *
     * @param string $topic Topic name
     * @param string $title Headline
     * @param string $body Body text
     * @param array<string, mixed> $data Key-value data
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken || empty($this->projectId)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'FCM credentials or project ID not configured',
            ];
        }

        $stringData = [];
        foreach ($data as $k => $v) {
            if ($v !== null) {
                $stringData[(string) $k] = is_string($v) ? $v : json_encode($v);
            }
        }

        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $stringData,
            ],
        ];

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        try {
            $response = Http::withToken($accessToken)->timeout(10)->post($url, $message);
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json()['name'] ?? null,
                    'error' => null,
                ];
            }
            return [
                'success' => false,
                'message_id' => null,
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
