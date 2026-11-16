<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Chat;
use App\Models\ChatBroadcastOutbox;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatBroadcaster
{
    protected mixed $ablyClient;

    public function __construct(mixed $client = null)
    {
        $this->ablyClient = $client;
    }

    /**
     * Resolve the Ably REST client instance if available.
     */
    protected function getAblyClient(): mixed
    {
        if ($this->ablyClient !== null) {
            return $this->ablyClient;
        }

        $apiKey = config('broadcasting.connections.ably.key') 
            ?: config('services.ably.key') 
            ?: env('ABLY_KEY');

        if (empty($apiKey) || !str_contains($apiKey, ':')) {
            Log::warning('ABLY_KEY is missing or invalid. Live broadcasting skipped.');
            return null;
        }

        if (class_exists(\Ably\AblyRest::class)) {
            try {
                $this->ablyClient = new \Ably\AblyRest([
                    'key' => $apiKey,
                    'httpRequestTimeout' => 2500, // 2.5 seconds timeout
                ]);
                return $this->ablyClient;
            } catch (Throwable $e) {
                Log::error('Could not initialize AblyRest: ' . $e->getMessage());
                return null;
            }
        }

        return null;
    }

    /**
     * Build the standard message payload broadcast to both mobile applications.
     *
     * @param Chat $chat
     * @return array<string, mixed>
     */
    public function formatPayload(Chat $chat): array
    {
        // Ensure relationships are available
        if (!$chat->relationLoaded('user') && $chat->user_id) {
            $chat->load('user:id,name');
        }
        if (!$chat->relationLoaded('astrologer') && $chat->astrologer_id) {
            $chat->load('astrologer:id,name');
        }

        $senderName = $chat->sender === 'user'
            ? ($chat->user?->name ?? 'User')
            : ($chat->astrologer?->name ?? 'Astrologer');

        $senderId = $chat->sender === 'user'
            ? (int) $chat->user_id
            : (int) $chat->astrologer_id;

        $sentAt = $chat->sent_at
            ? (is_string($chat->sent_at) ? $chat->sent_at : $chat->sent_at->toIso8601String())
            : ($chat->created_at ? $chat->created_at->toIso8601String() : now()->toIso8601String());

        return [
            'id' => (int) $chat->id,
            'chat_session_id' => (int) $chat->chat_session_id,
            'sender' => (string) $chat->sender,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'user_id' => (int) $chat->user_id,
            'admin_id' => (int) $chat->admin_id,
            'astrologer_id' => (int) $chat->astrologer_id,
            'message' => (string) $chat->message,
            'client_nonce' => $chat->client_nonce,
            'sent_at' => $sentAt,
        ];
    }

    /**
     * Publish a chat message to Ably with automatic Outbox queuing on failure.
     *
     * @param Chat $chat
     * @return bool True if live publish succeeded, false if queued in outbox
     */
    public function publish(Chat $chat): bool
    {
        $payload = $this->formatPayload($chat);
        $channelName = 'chat:session:' . $chat->chat_session_id;

        $apiKey = config('broadcasting.connections.ably.key') 
            ?: config('services.ably.key') 
            ?: env('ABLY_KEY');

        if (empty($apiKey) || !str_contains($apiKey, ':')) {
            Log::warning("[ABLY-DIAG-LARAVEL] Publish skipped: ABLY_KEY is missing or invalid", [
                'session_id' => (int) $chat->chat_session_id,
                'chat_id' => (int) $chat->id,
                'channel' => $channelName,
                'event' => 'message.created',
                'sender' => $chat->sender,
            ]);
            $this->queueOutbox($chat, $payload, 'Ably client unavailable (missing or invalid API key)');
            return false;
        }

        try {
            Log::info("[ABLY-DIAG-LARAVEL] Initiating publish HTTP request to Ably", [
                'session_id' => (int) $chat->chat_session_id,
                'chat_id' => (int) $chat->id,
                'channel' => $channelName,
                'event' => 'message.created',
                'sender' => $chat->sender,
            ]);

            $published = false;

            if ($this->ablyClient !== null && isset($this->ablyClient->channels)) {
                $channel = $this->ablyClient->channels->get($channelName);
                $res = $channel->publish('message.created', $payload);
                $published = true;
            } elseif (class_exists(\Ably\AblyRest::class)) {
                try {
                    $client = $this->getAblyClient();
                    if ($client !== null && isset($client->channels)) {
                        $channel = $client->channels->get($channelName);
                        $res = $channel->publish('message.created', $payload);
                        $published = true;
                    }
                } catch (\Throwable $sdkError) {
                    Log::warning("[ABLY-DIAG-LARAVEL] Ably SDK publish fallback: " . $sdkError->getMessage());
                }
            }

            if (!$published) {
                // Direct Ably REST API publish via HTTP
                [$keyName, $keySecret] = explode(':', $apiKey, 2);
                $response = Http::withBasicAuth($keyName, $keySecret)
                    ->timeout(3)
                    ->post("https://rest.ably.io/channels/{$channelName}/messages", [
                        'name' => 'message.created',
                        'data' => $payload,
                    ]);

                if (!$response->successful()) {
                    throw new \RuntimeException('Ably HTTP publish returned status ' . $response->status() . ': ' . $response->body());
                }
            }

            Log::info("[ABLY-DIAG-LARAVEL] Publish SUCCESS", [
                'session_id' => (int) $chat->chat_session_id,
                'chat_id' => (int) $chat->id,
                'channel' => $channelName,
                'event' => 'message.created',
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error("[ABLY-DIAG-LARAVEL] Publish FAILED", [
                'session_id' => (int) $chat->chat_session_id,
                'chat_id' => (int) $chat->id,
                'channel' => $channelName,
                'event' => 'message.created',
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
            ]);
            $this->queueOutbox($chat, $payload, $e->getMessage());
            return false;
        }
    }

    /**
     * Record a failed broadcast into the database-backed outbox for later retry.
     */
    public function queueOutbox(Chat $chat, array $payload, string $errorMessage): ChatBroadcastOutbox
    {
        return ChatBroadcastOutbox::updateOrCreate(
            ['chat_id' => $chat->id],
            [
                'chat_session_id' => $chat->chat_session_id,
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 1,
                'last_attempt_at' => now(),
                'last_error' => $errorMessage,
            ]
        );
    }

    /**
     * Retry publishing an item from the Outbox.
     */
    public function retryOutboxItem(ChatBroadcastOutbox $outbox): bool
    {
        $client = $this->getAblyClient();
        if ($client === null) {
            $outbox->increment('attempts');
            $outbox->update([
                'last_attempt_at' => now(),
                'last_error' => 'Ably client unavailable',
            ]);
            return false;
        }

        $channelName = 'chat:session:' . $outbox->chat_session_id;

        try {
            $channel = $client->channels->get($channelName);
            $channel->publish('message.created', $outbox->payload);

            $outbox->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'last_error' => null,
            ]);

            return true;
        } catch (Throwable $e) {
            $outbox->increment('attempts');
            $outbox->update([
                'status' => $outbox->attempts >= 5 ? 'failed' : 'pending',
                'last_attempt_at' => now(),
                'last_error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
