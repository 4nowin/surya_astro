<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\Chat;
use App\Models\ChatSession;
use App\Models\ProfessionalService;
use App\Models\ProfessionalTransaction;
use App\Models\User;
use App\Models\UserAstrologerChatAllowance;
use App\Models\UserWalletTransaction;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Services\Chat\AblyAuthService;
use App\Services\Chat\ChatBroadcaster;
use App\Services\Chat\ChatNotificationService;
use App\Services\Pricing\PricingService;
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
    protected PricingService $pricingService;

    public function __construct(
        AblyAuthService $ablyAuthService,
        ChatBroadcaster $broadcaster,
        ChatNotificationService $notificationService,
        PricingService $pricingService
    ) {
        $this->ablyAuthService = $ablyAuthService;
        $this->broadcaster = $broadcaster;
        $this->notificationService = $notificationService;
        $this->pricingService = $pricingService;
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
            Log::error('Ably token generation error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate real-time token: ' . $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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

        $chatService = null;
        if ($senderType === 'user') {
            $chatService = ProfessionalService::where('admin_id', $session->admin_id)
                ->where(function ($q) {
                    $q->where('pricing_model', 'per_message')
                      ->orWhereJsonContains('delivery_methods', 'chat');
                })
                ->first();

            // Auto-provision default chat service if missing for registered admin
            if (!$chatService && $session->admin_id) {
                $chatService = app(\App\Services\Chat\ChatServiceProvisioner::class)->provisionForAdmin((int) $session->admin_id);
            }

            // Do not silently substitute fallback pricing when service is unconfigured or disabled
            if (!$chatService || !$chatService->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat consultation service is currently not available for this astrologer.',
                ], 422);
            }

            $maxChars = (int) ($chatService->max_message_characters ?? 1000);
            if (mb_strlen($cleanText) > $maxChars) {
                return response()->json([
                    'success' => false,
                    'message' => "Message exceeds the maximum limit of {$maxChars} characters.",
                ], 422);
            }
        }

        $chat = null;
        $billingType = 'free';
        $basePrice = 0.00;
        $platformMarkup = 0.00;
        $customerPrice = 0.00;
        $balanceBefore = 0.00;
        $balanceAfter = 0.00;
        $freeRemaining = 0;

        try {
            DB::transaction(function () use (
                $session,
                $cleanText,
                $senderType,
                $clientNonce,
                $now,
                $chatService,
                &$chat,
                &$billingType,
                &$basePrice,
                &$platformMarkup,
                &$customerPrice,
                &$balanceBefore,
                &$balanceAfter,
                &$freeRemaining
            ) {
                if ($senderType === 'astrologer') {
                    $billingType = 'astrologer_reply';
                    $basePrice = 0.00;
                    $platformMarkup = 0.00;
                    $customerPrice = 0.00;
                } else {
                    // Lock or create one-time introductory allowance record for (user_id, astrologer_id)
                    $allowance = UserAstrologerChatAllowance::firstOrCreate(
                        [
                            'user_id' => $session->user_id,
                            'astrologer_id' => $session->astrologer_id,
                        ],
                        ['free_messages_used' => 0]
                    );

                    $allowance = UserAstrologerChatAllowance::where('id', $allowance->id)
                        ->lockForUpdate()
                        ->first();

                    $isExplicitFree = (bool) $chatService->is_free_chat;
                    $hasIntroductoryFree = !$isExplicitFree && ($allowance->free_messages_used < 5);

                    if ($isExplicitFree) {
                        $billingType = 'explicit_free';
                        $basePrice = 0.00;
                        $platformMarkup = 0.00;
                        $customerPrice = 0.00;
                        $freeRemaining = max(0, 5 - (int) $allowance->free_messages_used);
                    } elseif ($hasIntroductoryFree) {
                        $billingType = 'introductory_free';
                        $basePrice = 0.00;
                        $platformMarkup = 0.00;
                        $customerPrice = 0.00;

                        // Increment allowance only for accepted paid-service message
                        $allowance->increment('free_messages_used');
                        $freeRemaining = max(0, 5 - (int) $allowance->free_messages_used);
                    } else {
                        $billingType = 'paid';
                        $breakdown = $this->pricingService->calculateServicePrice($chatService->price, false);
                        $basePrice = $breakdown['base_price'];
                        $platformMarkup = $breakdown['platform_markup'];
                        $customerPrice = $breakdown['customer_price'];
                        $freeRemaining = 0;

                        // Lock user record and verify wallet balance
                        $userLocked = User::where('id', $session->user_id)->lockForUpdate()->first();
                        $balanceBefore = (float) $userLocked->wallet_balance;

                        if ($balanceBefore < $customerPrice) {
                            throw new InsufficientWalletBalanceException(
                                $customerPrice,
                                $balanceBefore,
                                'INR'
                            );
                        }

                        $balanceAfter = round($balanceBefore - $customerPrice, 2);
                        $userLocked->wallet_balance = $balanceAfter;
                        $userLocked->save();
                    }
                }

                // Create the chat message with immutable billing snapshot
                $chat = Chat::create([
                    'chat_session_id' => $session->id,
                    'user_id' => $session->user_id,
                    'admin_id' => $session->admin_id,
                    'astrologer_id' => $session->astrologer_id,
                    'message' => $cleanText,
                    'sender' => $senderType,
                    'client_nonce' => $clientNonce,
                    'billing_type' => $billingType,
                    'base_price' => $basePrice,
                    'platform_markup' => $platformMarkup,
                    'customer_price' => $customerPrice,
                    'sent_at' => $now,
                ]);

                // Record financial transaction ledger entries if paid
                if ($billingType === 'paid') {
                    UserWalletTransaction::create([
                        'user_id' => $session->user_id,
                        'transaction_type' => 'chat_message_debit',
                        'amount' => $customerPrice,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'chat_id' => $chat->id,
                        'chat_session_id' => $session->id,
                        'astrologer_id' => $session->astrologer_id,
                        'reference_id' => 'WALLET_CHAT_' . $chat->id,
                        'status' => 'completed',
                    ]);

                    // Check if a linked Jajman already exists in practitioner CRM for this customer
                    $linkedJajmanId = DB::table('professional_jajmans')
                        ->join('jajmans', 'jajmans.id', '=', 'professional_jajmans.jajman_id')
                        ->where('professional_jajmans.admin_id', $session->admin_id)
                        ->where('jajmans.user_id', $session->user_id)
                        ->value('jajmans.id');

                    // If not found yet and sender is user, ensure Jajman is linked
                    if (!$linkedJajmanId && $senderType === 'user') {
                        try {
                            $jajman = app(\App\Services\Jajman\JajmanAutoLinkService::class)->ensureJajmanForSession($session);
                            $linkedJajmanId = $jajman?->id;
                        } catch (\Throwable $e) {
                            Log::warning('JajmanAutoLinkService error resolving Jajman for chat transaction: ' . $e->getMessage());
                        }
                    }

                    ProfessionalTransaction::create([
                        'admin_id' => $session->admin_id,
                        'jajman_id' => $linkedJajmanId,
                        'user_id' => $session->user_id,
                        'service_id' => $chatService->id,
                        'chat_session_id' => $session->id,
                        'chat_id' => $chat->id,
                        'transaction_type' => 'chat_message',
                        'payment_method' => 'wallet',
                        'payment_status' => 'completed',
                        'amount' => $basePrice, // astrologer net earning
                        'gross_amount' => $customerPrice, // customer payment
                        'platform_fee' => $platformMarkup, // platform markup
                        'net_amount' => $basePrice, // astrologer net earning
                        'currency' => 'INR',
                        'transaction_at' => $now,
                        'reference_id' => 'EARN_CHAT_' . $chat->id,
                        'notes' => 'Chat consultation message #' . $chat->id,
                    ]);
                }

                // Touch session updated_at
                $session->touch();
            });
        } catch (InsufficientWalletBalanceException $e) {
            return response()->json([
                'success' => false,
                'error' => 'insufficient_balance',
                'required_amount' => $e->getRequiredAmount(),
                'wallet_balance' => $e->getWalletBalance(),
                'currency' => $e->getCurrency(),
                'message' => 'Insufficient wallet balance to send message. Please add funds to continue chatting.',
            ], 402);
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

        // Auto-create or link Jajman CRM record for customer on chat message initiation
        if ($senderType === 'user') {
            try {
                app(\App\Services\Jajman\JajmanAutoLinkService::class)->ensureJajmanForSession($session);
            } catch (\Throwable $e) {
                Log::warning('JajmanAutoLinkService error in sendMessage: ' . $e->getMessage());
            }
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
            'billing' => [
                'billing_type' => $billingType,
                'base_price' => $basePrice,
                'platform_markup' => $platformMarkup,
                'customer_price' => $customerPrice,
                'free_messages_remaining' => $freeRemaining,
            ],
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
            ->where(function ($q) use ($session) {
                $q->where('chat_session_id', $session->id)
                  ->orWhere(function ($sub) use ($session) {
                      $sub->where('user_id', $session->user_id)
                          ->where('astrologer_id', $session->astrologer_id);
                  });
            });

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

    /**
     * Get chat pricing details and remaining introductory free message allowance for an astrologer.
     *
     * GET /api/{lang?}/chat/pricing/{astrologerId}
     */
    public function getChatPricing(Request $request, mixed ...$args): JsonResponse
    {
        $actor = $request->user();
        if (!$actor) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $astrologerId = (int) ($request->route('astrologerId') ?? $request->route('astrologer_id') ?? end($args));
        $astrologer = Astrologer::find($astrologerId);
        if (!$astrologer) {
            return response()->json(['success' => false, 'message' => 'Astrologer not found.'], 404);
        }

        $chatService = ProfessionalService::where('admin_id', $astrologer->admin_id)
            ->where(function ($q) {
                $q->where('pricing_model', 'per_message')
                  ->orWhereJsonContains('delivery_methods', 'chat');
            })
            ->first();

        if (!$chatService && $astrologer->admin_id) {
            $chatService = app(\App\Services\Chat\ChatServiceProvisioner::class)->provisionForAdmin((int) $astrologer->admin_id);
        }

        $isEnabled = (bool) ($chatService && $chatService->is_active);
        $isFreeChat = (bool) ($chatService && $chatService->is_free_chat);
        $basePrice = $chatService ? (float) $chatService->price : 0.00;
        $maxChars = $chatService ? (int) ($chatService->max_message_characters ?? 1000) : 1000;

        $breakdown = $this->pricingService->calculateServicePrice($basePrice, $isFreeChat);

        $freeMessagesUsed = 0;
        if ($actor instanceof User) {
            $allowance = UserAstrologerChatAllowance::where('user_id', $actor->id)
                ->where('astrologer_id', $astrologer->id)
                ->first();
            $freeMessagesUsed = $allowance ? (int) $allowance->free_messages_used : 0;
        }

        $freeMessagesRemaining = max(0, 5 - $freeMessagesUsed);
        $userWalletBalance = $actor instanceof User ? (float) $actor->wallet_balance : 0.00;
        $canSendMessage = $isEnabled && ($isFreeChat || $freeMessagesRemaining > 0 || $userWalletBalance >= $breakdown['customer_price']);

        return response()->json([
            'success' => true,
            'data' => [
                'astrologer_id' => (int) $astrologer->id,
                'admin_id' => (int) $astrologer->admin_id,
                'is_enabled' => $isEnabled,
                'is_free_chat' => $isFreeChat,
                'astrologer_base_price' => $breakdown['base_price'],
                'platform_markup' => $breakdown['platform_markup'],
                'customer_price' => $breakdown['customer_price'],
                'currency' => 'INR',
                'free_messages_total' => 5,
                'free_messages_used' => $freeMessagesUsed,
                'free_messages_remaining' => $freeMessagesRemaining,
                'max_message_characters' => $maxChars,
                'user_wallet_balance' => $userWalletBalance,
                'can_send_message' => $canSendMessage,
            ],
        ]);
    }

    /**
     * Get chat pricing details and remaining free message allowance for an active chat session.
     *
     * GET /api/{lang?}/chat/sessions/{sessionId}/pricing
     */
    public function getSessionPricing(Request $request, mixed ...$args): JsonResponse
    {
        $resolvedSessionId = (int) ($request->route('sessionId') ?? $request->route('chat_session_id') ?? end($args));
        $authData = $this->authorizeSessionParticipant($request, $resolvedSessionId);
        $session = $authData['session'];

        return $this->getChatPricing($request, $session->astrologer_id);
    }
}
