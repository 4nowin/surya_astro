<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Admin;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AblyAuthService
{
    protected ?string $apiKey;
    protected mixed $ablyClient = null;

    public function __construct(?string $apiKey = null, mixed $client = null)
    {
        $this->apiKey = $apiKey 
            ?: config('broadcasting.connections.ably.key') 
            ?: config('services.ably.key') 
            ?: env('ABLY_KEY');

        $this->ablyClient = $client;
    }

    /**
     * Resolve the active AblyRest client if available.
     */
    public function getAblyClient(): mixed
    {
        if ($this->ablyClient !== null) {
            return $this->ablyClient;
        }

        if (empty($this->apiKey) || !str_contains($this->apiKey, ':')) {
            throw new InvalidArgumentException('ABLY_KEY is missing or invalid in server configuration.');
        }

        if (class_exists(\Ably\AblyRest::class)) {
            try {
                $this->ablyClient = new \Ably\AblyRest([
                    'key' => $this->apiKey,
                    'httpRequestTimeout' => 3000,
                ]);
                return $this->ablyClient;
            } catch (\Throwable $e) {
                Log::warning('AblyRest initialization failed: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Generate an Ably TokenRequest using native HMAC-SHA256 signing.
     *
     * @param string $key The Ably API key (keyName:keySecret)
     * @param array<string, mixed> $tokenParams Token parameters (ttl, capability, clientId, timestamp, nonce)
     * @return array<string, mixed> Signed TokenRequest structure
     */
    public function generateTokenRequest(string $key, array $tokenParams): array
    {
        if (empty($key) || !str_contains($key, ':')) {
            throw new InvalidArgumentException('ABLY_KEY is missing or invalid in server configuration.');
        }

        [$keyName, $keySecret] = explode(':', $key, 2);

        $ttl = (int) ($tokenParams['ttl'] ?? (3600 * 1000));
        $capability = $tokenParams['capability'] ?? [];
        if (is_array($capability)) {
            ksort($capability);
            $capabilityJson = json_encode($capability);
        } else {
            $capabilityJson = (string) $capability;
        }

        $clientId = (string) ($tokenParams['clientId'] ?? '');
        $timestamp = (int) ($tokenParams['timestamp'] ?? round(microtime(true) * 1000));
        $nonce = (string) ($tokenParams['nonce'] ?? bin2hex(random_bytes(16)));

        $signText = implode("\n", [
            $keyName,
            $ttl,
            $capabilityJson,
            $clientId,
            $timestamp,
            $nonce,
        ]) . "\n";

        $mac = base64_encode(hash_hmac('sha256', $signText, $keySecret, true));

        return [
            'keyName' => $keyName,
            'ttl' => $ttl,
            'capability' => $capabilityJson,
            'clientId' => $clientId,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'mac' => $mac,
        ];
    }

    /**
     * Authorize participation and generate a signed TokenRequest.
     *
     * @param Authenticatable $user The authenticated User or Admin
     * @param int|string $sessionId The ChatSession ID
     * @return array<string, mixed> The serializable TokenRequest payload
     */
    public function createTokenRequestForSession(Authenticatable $user, int|string $sessionId): array
    {
        $session = ChatSession::with(['user', 'astrologer.admin'])->find($sessionId);

        if (!$session) {
            throw new NotFoundHttpException('Chat session not found.');
        }

        $participantType = $this->resolveParticipantType($user, $session);
        if (!$participantType) {
            throw new AccessDeniedHttpException('You are not an authorized participant of this chat session.');
        }

        // Auto-reactivate session if an authorized participant connects to it
        if ($session->ended_at !== null) {
            $session->ended_at = null;
            $session->save();
            Log::info("Session {$session->id} auto-reactivated for Ably token request by {$participantType}");
        }

        $channelName = 'chat:session:' . $session->id;
        $clientId = $participantType === 'user'
            ? 'user_' . $user->getAuthIdentifier()
            : 'astrologer_' . $user->getAuthIdentifier();

        try {
            if (empty($this->apiKey) || !str_contains($this->apiKey, ':')) {
                throw new InvalidArgumentException('ABLY_KEY is missing or invalid in server configuration.');
            }

            // Scope token to subscribe permission only on this specific channel
            $tokenParams = [
                'clientId' => $clientId,
                'capability' => [
                    $channelName => ['subscribe'],
                ],
                'ttl' => 3600 * 1000, // 1 hour in ms
            ];

            Log::info("[ABLY-DIAG-LARAVEL] Generating TokenRequest", [
                'session_id' => $session->id,
                'channel' => $channelName,
                'client_id' => $clientId,
                'participant_type' => $participantType,
                'capabilities' => $tokenParams['capability'],
            ]);

            $tokenRequest = null;
            if ($this->ablyClient !== null && isset($this->ablyClient->auth)) {
                $tokenRequest = $this->ablyClient->auth->createTokenRequest($tokenParams);
            } elseif (class_exists(\Ably\AblyRest::class)) {
                try {
                    $client = $this->getAblyClient();
                    if ($client !== null && isset($client->auth)) {
                        $tokenRequest = $client->auth->createTokenRequest($tokenParams);
                    }
                } catch (\Throwable $sdkError) {
                    Log::warning("[ABLY-DIAG-LARAVEL] Ably SDK TokenRequest generation fallback: " . $sdkError->getMessage());
                }
            }

            if ($tokenRequest === null) {
                $tokenRequest = $this->generateTokenRequest($this->apiKey, $tokenParams);
            }

            Log::info("[ABLY-DIAG-LARAVEL] TokenRequest generated successfully", [
                'session_id' => $session->id,
                'channel' => $channelName,
                'client_id' => $clientId,
                'key_name' => is_array($tokenRequest) ? ($tokenRequest['keyName'] ?? 'set') : ($tokenRequest->keyName ?? 'set'),
                'timestamp' => is_array($tokenRequest) ? ($tokenRequest['timestamp'] ?? null) : ($tokenRequest->timestamp ?? null),
            ]);

            return [
                'token_request' => $tokenRequest,
                'channel_name' => $channelName,
                'client_id' => $clientId,
                'participant_type' => $participantType,
                'session_id' => $session->id,
            ];
        } catch (\Throwable $e) {
            Log::error('[ABLY-DIAG-LARAVEL] Failed to create Ably TokenRequest: ' . $e->getMessage(), [
                'session_id' => $session->id,
                'user_id' => $user->getAuthIdentifier(),
                'type' => $participantType,
                'error_code' => $e->getCode(),
            ]);
            throw $e;
        }
    }

    /**
     * Determine whether the authenticated actor is the user or astrologer for this session.
     */
    public function resolveParticipantType(Authenticatable $user, ChatSession $session): ?string
    {
        $authId = (int) $user->getAuthIdentifier();

        if ($user instanceof User) {
            return (int) $session->user_id === $authId ? 'user' : null;
        }

        if ($user instanceof Admin) {
            $sessionAdminId = (int) $session->admin_id;
            $astrologerAdminId = (int) ($session->astrologer?->admin_id ?? 0);

            if ($sessionAdminId === $authId || $astrologerAdminId === $authId) {
                return 'astrologer';
            }
        }

        return null;
    }
}
