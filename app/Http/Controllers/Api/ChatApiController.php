<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Chat;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\Chat\AblyAuthService;
use App\Services\Chat\ChatBroadcaster;
use App\Services\Chat\ChatNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ChatApiController extends Controller
{
    protected AblyAuthService $ablyAuthService;
    protected ChatBroadcaster $broadcaster;
    protected ChatNotificationService $notificationService;

    public function __construct(
        AblyAuthService $ablyAuthService,
        ChatBroadcaster $broadcaster,
        ChatNotificationService $notificationService
    ) {
        $this->ablyAuthService = $ablyAuthService;
        $this->broadcaster = $broadcaster;
        $this->notificationService = $notificationService;
    }

    /**
     * Resolve session and verify that the authenticated actor is an authorized participant.
     *
     * @return array{session: ChatSession, sender_type: string}
     */
    protected function authorizeSessionParticipant(Request $request, int|string $sessionId): array
    {
        $actor = $request->user();
        if (!$actor) {
            abort(401, 'Unauthenticated.');
        }

        $session = ChatSession::with(['user', 'astrologer.admin'])->find($sessionId);
        if (!$session) {
            abort(404, 'Chat session not found.');
        }

        $participantType = $this->ablyAuthService->resolveParticipantType($actor, $session);
        if (!$participantType) {
            abort(403, 'Unauthorized access to this chat session.');
        }

        return [
            'session' => $session,
            'sender_type' => $participantType,
        ];
    }

    /**
     * Mint an Ably TokenRequest scoped to the session's channel.
     *
     * POST /api/{lang?}/chat/ably-token
     */
    public function createAblyToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_session_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sessionId = (int) $request->input('chat_session_id');

        try {
            $tokenData = $this->ablyAuthService->createTokenRequestForSession($request->user(), $sessionId);

            return response()->json([
                'success' => true,
                'data' => $tokenData,
            ], 200);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Ably token generation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate real-time token. Please verify server configuration.',
            ], 500);
        }
    }

    /**
     * Send a chat message (MySQL persistence + Ably broadcast + FCM notification).
     *
     * POST /api/{lang?}/chat/message/send
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_session_id' => 'required|integer|exists:chat_sessions,id',
            'message' => 'required|string|max:2000',
            'client_nonce' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $sessionId = (int) $request->input('chat_session_id');
        $authData = $this->authorizeSessionParticipant($request, $sessionId);
        $session = $authData['session'];
        $senderType = $authData['sender_type']; // 'user' or 'astrologer'

        // Check if session has ended
        if ($session->ended_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'This chat session has already ended.',
            ], 422);
        }

        $clientNonce = $request->input('client_nonce');

        // Idempotency check: if client_nonce already exists for this session, return original
        if (!empty($clientNonce)) {
            $existing = Chat::where('chat_session_id', $sessionId)
                ->where('client_nonce', $clientNonce)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'status' => 'duplicate_returned',
                    'message' => $this->broadcaster->formatPayload($existing),
                ], 200);
            }
        }

        $cleanText = trim((string) $request->input('message'));
        $now = now();

        $chat = null;

        try {
            DB::transaction(function () use ($session, $cleanText, $senderType, $clientNonce, $now, &$chat) {
                $chat = Chat::create([
                    'chat_session_id' => $session->id,
                    'user_id' => $session->user_id,
                    'admin_id' => $session->admin_id,
                    'astrologer_id' => $session->astrologer_id,
                    'message' => $cleanText,
                    'sender' => $senderType,
                    'client_nonce' => $clientNonce,
                    'sent_at' => $now,
                ]);

                // Touch session updated_at
                $session->touch();
            });
        } catch (QueryException $e) {
            // Concurrent duplicate race condition check
            if (!empty($clientNonce) && str_contains($e->getMessage(), 'chats_session_nonce_unique')) {
                $existing = Chat::where('chat_session_id', $sessionId)
                    ->where('client_nonce', $clientNonce)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => true,
                        'status' => 'duplicate_returned',
                        'message' => $this->broadcaster->formatPayload($existing),
                    ], 200);
                }
            }
            throw $e;
        }

        // Post-commit: Broadcast to Ably
        $broadcastSuccess = $this->broadcaster->publish($chat);

        // Post-commit: Dispatch FCM push notification
        $notificationResult = $this->notificationService->notifyRecipient($chat);

        $formatted = $this->broadcaster->formatPayload($chat);

        return response()->json([
            'success' => true,
            'status' => 'created',
            'broadcast_live' => $broadcastSuccess,
            'notifications_dispatched' => $notificationResult['sent_count'],
            'message' => $formatted,
        ], 201);
    }

    /**
     * Retrieve paginated messages for a chat session.
     *
     * GET /api/{lang?}/chat/sessions/{sessionId}/messages
     */
    public function getMessages(Request $request, mixed ...$args): JsonResponse
    {
        $resolvedSessionId = (int) ($request->route('sessionId') ?? $request->route('chat_session_id') ?? end($args));
        $authData = $this->authorizeSessionParticipant($request, $resolvedSessionId);
        $session = $authData['session'];

        $limit = min(max((int) $request->query('limit', 25), 1), 100);
        $beforeId = $request->query('before_id');
        $afterId = $request->query('after_id');

        $query = Chat::with(['user:id,name', 'astrologer:id,name'])
            ->where('chat_session_id', $session->id);

        if (!empty($afterId)) {
            // Catch-up mode: fetch newest messages after the last known ID in ascending order
            $query->where('id', '>', (int) $afterId)->orderBy('id', 'asc');
            $messages = $query->limit($limit)->get();
            $hasMore = false;
        } elseif (!empty($beforeId)) {
            // Pagination mode (scrolling up): fetch older messages before the cursor
            $query->where('id', '<', (int) $beforeId)->orderBy('id', 'desc');
            $rawMessages = $query->limit($limit + 1)->get();
            $hasMore = $rawMessages->count() > $limit;
            $messages = $rawMessages->take($limit)->sortBy('id')->values();
        } else {
            // Initial load mode: fetch latest N messages in ascending chronological order
            $query->orderBy('id', 'desc');
            $rawMessages = $query->limit($limit + 1)->get();
            $hasMore = $rawMessages->count() > $limit;
            $messages = $rawMessages->take($limit)->sortBy('id')->values();
        }

        $formattedList = $messages->map(fn(Chat $c) => $this->broadcaster->formatPayload($c))->values();

        return response()->json([
            'success' => true,
            'chat_session_id' => (int) $session->id,
            'session_status' => $session->ended_at ? 'ended' : 'active',
            'count' => $formattedList->count(),
            'has_more' => $hasMore,
            'messages' => $formattedList,
        ], 200);
    }

    /**
     * End a chat session safely.
     *
     * POST /api/{lang?}/chat/session/end
     */
    public function endSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_session_id' => 'required|integer|exists:chat_sessions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sessionId = (int) $request->input('chat_session_id');
        $authData = $this->authorizeSessionParticipant($request, $sessionId);
        $session = $authData['session'];

        if ($session->ended_at === null) {
            $session->update(['ended_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'status' => 'ended',
            'chat_session_id' => $session->id,
        ], 200);
    }
}
