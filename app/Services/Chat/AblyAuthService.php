<?php

declare(strict_types=1);

namespace App\Services\Chat;

use Ably\AblyRest;
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
    protected ?AblyRest $ablyClient = null;

    public function __construct(?string $apiKey = null, ?AblyRest $client = null)
    {
        $this->apiKey = $apiKey 
            ?: config('broadcasting.connections.ably.key') 
            ?: config('services.ably.key') 
            ?: env('ABLY_KEY');

        $this->ablyClient = $client;
    }

    /**
     * Resolve the active AblyRest client.
     */
    public function getAblyClient(): AblyRest
    {
        if ($this->ablyClient !== null) {
            return $this->ablyClient;
        }

        if (empty($this->apiKey) || !str_contains($this->apiKey, ':')) {
            throw new InvalidArgumentException('ABLY_KEY is missing or invalid in server configuration.');
        }

        $this->ablyClient = new AblyRest([
            'key' => $this->apiKey,
            'httpRequestTimeout' => 3000,
        ]);

        return $this->ablyClient;
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

        // Verify session is active
        if ($session->ended_at !== null) {
            throw new UnprocessableEntityHttpException('Cannot generate Ably token for a terminated chat session.');
        }

        $channelName = 'chat:session:' . $session->id;
        $clientId = $participantType === 'user'
            ? 'user_' . $user->getAuthIdentifier()
            : 'astrologer_' . $user->getAuthIdentifier();

        try {
            $ably = $this->getAblyClient();

            // Scope token to subscribe permission only on this specific channel
            $tokenParams = [
                'clientId' => $clientId,
                'capability' => [
                    $channelName => ['subscribe'],
                ],
                'ttl' => 3600 * 1000, // 1 hour in ms
            ];

            $tokenRequest = $ably->auth->createTokenRequest($tokenParams);

            return [
                'token_request' => $tokenRequest,
                'channel_name' => $channelName,
                'client_id' => $clientId,
                'participant_type' => $participantType,
                'session_id' => $session->id,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create Ably TokenRequest: ' . $e->getMessage(), [
                'session_id' => $session->id,
                'user_id' => $user->getAuthIdentifier(),
                'type' => $participantType,
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
