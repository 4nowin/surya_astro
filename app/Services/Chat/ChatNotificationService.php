<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Admin;
use App\Models\Chat;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatNotificationService
{
    protected FirebaseNotificationService $firebaseService;

    public function __construct(?FirebaseNotificationService $firebaseService = null)
    {
        $this->firebaseService = $firebaseService ?? app(FirebaseNotificationService::class);
    }

    /**
     * Send FCM push notification to the recipient of a chat message.
     *
     * @param Chat $chat The newly persisted Chat message
     * @return array{sent_count: int, recipient_type: string}
     */
    public function notifyRecipient(Chat $chat): array
    {
        // Ensure relations are loaded
        if (!$chat->relationLoaded('user') && $chat->user_id) {
            $chat->load('user:id,name,fcm_token');
        }
        if (!$chat->relationLoaded('astrologer') && $chat->astrologer_id) {
            $chat->load('astrologer.admin:id,name,fcm_token');
        }

        $senderType = $chat->sender; // 'user' or 'astrologer'

        if ($senderType === 'user') {
            return $this->notifyAstrologer($chat);
        } else {
            return $this->notifyUser($chat);
        }
    }

    /**
     * Send push notification to the astrologer.
     */
    protected function notifyAstrologer(Chat $chat): array
    {
        $admin = $chat->astrologer?->admin ?? Admin::find($chat->admin_id);
        $fcmToken = $admin?->fcm_token;

        if (empty($fcmToken)) {
            Log::info("FCM skipped for astrologer admin {$chat->admin_id}: no FCM token registered.");
            return ['sent_count' => 0, 'recipient_type' => 'astrologer'];
        }

        $senderName = $chat->user?->name ?? 'User';
        $title = "New message from {$senderName}";
        $body = $chat->message;

        $data = [
            'type' => 'chat_message',
            'session_id' => (string) $chat->chat_session_id,
            'user_id' => (string) $chat->user_id,
            'admin_id' => (string) $chat->admin_id,
            'astrologer_id' => (string) $chat->astrologer_id,
            'sender_name' => (string) $senderName,
        ];

        try {
            $result = $this->firebaseService->sendToToken($fcmToken, $title, $body, $data);
            Log::info("FCM push dispatched to astrologer {$chat->astrologer_id}", [
                'success' => $result['success'] ?? false,
                'chat_id' => $chat->id,
            ]);

            return [
                'sent_count' => ($result['success'] ?? false) ? 1 : 0,
                'recipient_type' => 'astrologer',
            ];
        } catch (Throwable $e) {
            Log::warning("FCM dispatch failed for astrologer {$chat->astrologer_id}: " . $e->getMessage());
            return ['sent_count' => 0, 'recipient_type' => 'astrologer'];
        }
    }

    /**
     * Send push notification to the user.
     */
    protected function notifyUser(Chat $chat): array
    {
        $userId = (int) $chat->user_id;

        // Retrieve tokens from active user_devices table and user model fallback
        $deviceTokens = UserDevice::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->toArray();

        $legacyToken = $chat->user?->fcm_token ?? User::where('id', $userId)->value('fcm_token');
        if (!empty($legacyToken) && !in_array($legacyToken, $deviceTokens, true)) {
            $deviceTokens[] = $legacyToken;
        }

        $validTokens = array_values(array_filter(array_unique($deviceTokens)));

        if (empty($validTokens)) {
            Log::info("FCM skipped for user {$userId}: no active FCM token registered.");
            return ['sent_count' => 0, 'recipient_type' => 'user'];
        }

        $senderName = $chat->astrologer?->name ?? 'Astrologer';
        $title = "New message from {$senderName}";
        $body = $chat->message;

        $data = [
            'type' => 'chat_message',
            'session_id' => (string) $chat->chat_session_id,
            'user_id' => (string) $chat->user_id,
            'admin_id' => (string) $chat->admin_id,
            'astrologer_id' => (string) $chat->astrologer_id,
            'sender_name' => (string) $senderName,
        ];

        $sentCount = 0;
        foreach ($validTokens as $token) {
            try {
                $result = $this->firebaseService->sendToToken($token, $title, $body, $data);
                if ($result['success'] ?? false) {
                    $sentCount++;
                }
            } catch (Throwable $e) {
                Log::warning("FCM dispatch failed for user token: " . $e->getMessage());
            }
        }

        Log::info("FCM push dispatched to user {$userId} ({$sentCount}/" . count($validTokens) . " devices)", [
            'chat_id' => $chat->id,
        ]);

        return [
            'sent_count' => $sentCount,
            'recipient_type' => 'user',
        ];
    }
}
