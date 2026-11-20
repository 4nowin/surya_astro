<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\Chat;
use App\Models\ChatSession;
use App\Models\ProfessionalService;
use App\Models\ProfessionalTransaction;
use App\Models\User;
use App\Models\UserAstrologerChatAllowance;
use App\Models\UserWalletTransaction;
use App\Services\Chat\ChatServiceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatMonetizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Admin $adminA;
    protected Astrologer $astrologerA;
    protected Admin $adminB;
    protected Astrologer $astrologerB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        // Astrologer A (Base price ₹0.10)
        $this->adminA = Admin::create([
            'name' => 'Acharya Raman',
            'email' => 'raman@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $this->adminA->assignRole('Astrologer');

        $this->astrologerA = Astrologer::create([
            'admin_id' => $this->adminA->id,
            'name' => 'Acharya Raman',
            'language' => 'en',
            'astrologer_language' => 'Hindi, English',
            'expertise' => 'Vedic Astrology',
            'experience' => '12 years',
            'price' => 0.10,
            'status' => 'online',
            'active' => 1,
        ]);

        app(ChatServiceProvisioner::class)->provisionForAstrologer($this->astrologerA);

        // Astrologer B (Base price ₹0.20)
        $this->adminB = Admin::create([
            'name' => 'Pandit Vijay',
            'email' => 'vijay@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $this->adminB->assignRole('Astrologer');

        $this->astrologerB = Astrologer::create([
            'admin_id' => $this->adminB->id,
            'name' => 'Pandit Vijay',
            'language' => 'en',
            'astrologer_language' => 'Hindi',
            'expertise' => 'Vastu Shastra',
            'experience' => '8 years',
            'price' => 0.20,
            'status' => 'online',
            'active' => 1,
        ]);

        app(ChatServiceProvisioner::class)->provisionForAstrologer($this->astrologerB);

        // User
        $this->user = User::create([
            'name' => 'Kavita Sharma',
            'email' => 'kavita@example.com',
            'password' => bcrypt('secret123'),
            'wallet_balance' => 0.00,
        ]);
    }

    public function test_user_gets_5_free_introductory_messages_without_wallet_balance(): void
    {
        Sanctum::actingAs($this->user);

        // Start chat session with Astrologer A
        $startRes = $this->postJson('/api/chat/start-session', [
            'admin_id' => $this->adminA->id,
            'astrologer_id' => $this->astrologerA->id,
        ]);
        $startRes->assertStatus(200);
        $sessionId = $startRes->json('chat_session_id');

        // Check initial pricing endpoint
        $pricingRes = $this->getJson("/api/chat/astrologers/{$this->astrologerA->id}/pricing");
        $pricingRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'astrologer_base_price' => 0.10,
                    'platform_markup' => 0.02,
                    'customer_price' => 0.12,
                    'free_messages_remaining' => 5,
                    'can_send_message' => true,
                ],
            ]);

        // Send 5 introductory free messages
        for ($i = 1; $i <= 5; $i++) {
            $sendRes = $this->postJson('/api/chat/message/send', [
                'chat_session_id' => $sessionId,
                'message' => "Hello message number {$i}",
                'client_nonce' => "client-nonce-free-{$i}",
            ]);

            $sendRes->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'billing' => [
                        'billing_type' => 'introductory_free',
                        'customer_price' => 0.0,
                        'free_messages_remaining' => 5 - $i,
                    ],
                ]);
        }

        // Verify database state: 5 messages, 0 debit, allowance used = 5
        $this->assertEquals(5, Chat::where('chat_session_id', $sessionId)->count());
        $this->assertEquals(5, Chat::where('billing_type', 'introductory_free')->count());

        $allowance = UserAstrologerChatAllowance::where('user_id', $this->user->id)
            ->where('astrologer_id', $this->astrologerA->id)
            ->first();
        $this->assertNotNull($allowance);
        $this->assertEquals(5, $allowance->free_messages_used);

        // User wallet remains 0.00
        $this->assertEquals(0.00, (float) $this->user->fresh()->wallet);

        // No ledger debit or practitioner transactions created for free messages
        $this->assertEquals(0, UserWalletTransaction::count());
        $this->assertEquals(0, ProfessionalTransaction::count());
    }

    public function test_6th_message_without_wallet_balance_returns_http_402_and_does_not_save_message(): void
    {
        Sanctum::actingAs($this->user);

        // Manually mark 5 free messages as used
        UserAstrologerChatAllowance::create([
            'user_id' => $this->user->id,
            'astrologer_id' => $this->astrologerA->id,
            'free_messages_used' => 5,
            'first_message_at' => now()->subDay(),
            'last_message_at' => now(),
        ]);

        $session = ChatSession::create([
            'admin_id' => $this->adminA->id,
            'astrologer_id' => $this->astrologerA->id,
            'user_id' => $this->user->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        // Pricing check should show 0 free messages remaining, can_send_message = false
        $pricingRes = $this->getJson("/api/chat/astrologers/{$this->astrologerA->id}/pricing");
        $pricingRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'free_messages_remaining' => 0,
                    'can_send_message' => false,
                ],
            ]);

        // Sending 6th message with 0 balance must return 402
        $sendRes = $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $session->id,
            'message' => 'This 6th message should fail due to insufficient funds',
            'client_nonce' => 'nonce-fail-6th',
        ]);

        $sendRes->assertStatus(402)
            ->assertJson([
                'success' => false,
                'error' => 'insufficient_balance',
                'required_amount' => 0.12,
                'wallet_balance' => 0.0,
            ]);

        // Message should NOT be persisted in chats table
        $this->assertEquals(0, Chat::where('client_nonce', 'nonce-fail-6th')->count());
        $this->assertEquals(0, UserWalletTransaction::count());
    }

    public function test_6th_message_with_sufficient_wallet_is_charged_correctly_with_20_percent_markup(): void
    {
        Sanctum::actingAs($this->user);

        // Set user wallet to ₹1.00
        $this->user->update(['wallet_balance' => 1.00]);

        // Mark 5 free messages as used
        UserAstrologerChatAllowance::create([
            'user_id' => $this->user->id,
            'astrologer_id' => $this->astrologerA->id,
            'free_messages_used' => 5,
        ]);

        $session = ChatSession::create([
            'admin_id' => $this->adminA->id,
            'astrologer_id' => $this->astrologerA->id,
            'user_id' => $this->user->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        // Send 6th message
        $sendRes = $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $session->id,
            'message' => 'This 6th message should be debited',
            'client_nonce' => 'nonce-charge-6th',
        ]);

        $sendRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'billing' => [
                    'billing_type' => 'paid',
                    'base_price' => 0.10,
                    'platform_markup' => 0.02,
                    'customer_price' => 0.12,
                    'free_messages_remaining' => 0,
                ],
            ]);

        // Verify message immutable snapshot
        $chat = Chat::where('client_nonce', 'nonce-charge-6th')->first();
        $this->assertNotNull($chat);
        $this->assertEquals('paid', $chat->billing_type);
        $this->assertEquals(0.10, (float) $chat->base_price);
        $this->assertEquals(0.02, (float) $chat->platform_markup);
        $this->assertEquals(0.12, (float) $chat->customer_price);

        // Verify user wallet balance updated to 0.88
        $this->assertEquals(0.88, (float) $this->user->fresh()->wallet);

        // Verify UserWalletTransaction ledger entry
        $walletTx = UserWalletTransaction::where('user_id', $this->user->id)->first();
        $this->assertNotNull($walletTx);
        $this->assertEquals(0.12, (float) $walletTx->amount);
        $this->assertEquals('chat_message_debit', $walletTx->transaction_type);
        $this->assertEquals(1.00, (float) $walletTx->balance_before);
        $this->assertEquals(0.88, (float) $walletTx->balance_after);

        // Verify ProfessionalTransaction ledger entry
        $profTx = ProfessionalTransaction::where('admin_id', $this->adminA->id)->first();
        $this->assertNotNull($profTx);
        $this->assertEquals($this->user->id, $profTx->user_id);
        $this->assertEquals($session->id, $profTx->chat_session_id);
        $this->assertEquals($chat->id, $profTx->chat_id);
        $this->assertEquals('chat_message', $profTx->transaction_type);
        $this->assertEquals('wallet', $profTx->payment_method);
        $this->assertEquals('completed', $profTx->payment_status);
        $this->assertEquals(0.12, (float) $profTx->gross_amount);
        $this->assertEquals(0.02, (float) $profTx->platform_fee);
        $this->assertEquals(0.10, (float) $profTx->net_amount);
        $this->assertEquals(0.10, (float) $profTx->amount);
    }

    public function test_allowance_persists_across_sessions_for_same_astrologer(): void
    {
        Sanctum::actingAs($this->user);

        // Session 1: User sends 3 messages
        $startRes1 = $this->postJson('/api/chat/start-session', [
            'admin_id' => $this->adminA->id,
            'astrologer_id' => $this->astrologerA->id,
        ]);
        $sessionId1 = $startRes1->json('chat_session_id');

        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/chat/message/send', [
                'chat_session_id' => $sessionId1,
                'message' => "Session 1 message {$i}",
                'client_nonce' => "s1-nonce-{$i}",
            ])->assertStatus(201);
        }

        // End session 1
        $this->postJson('/api/chat/session/end', [
            'chat_session_id' => $sessionId1,
        ])->assertStatus(200);

        // Session 2: User starts new session with same Astrologer
        $startRes2 = $this->postJson('/api/chat/start-session', [
            'admin_id' => $this->adminA->id,
            'astrologer_id' => $this->astrologerA->id,
        ]);
        $sessionId2 = $startRes2->json('chat_session_id');

        // Check pricing in session 2: exactly 2 free messages should remain
        $pricingRes = $this->getJson("/api/chat/astrologers/{$this->astrologerA->id}/pricing");
        $pricingRes->assertJson([
            'success' => true,
            'data' => [
                'free_messages_remaining' => 2,
            ],
        ]);

        // Send 2 remaining free messages in Session 2
        for ($i = 4; $i <= 5; $i++) {
            $this->postJson('/api/chat/message/send', [
                'chat_session_id' => $sessionId2,
                'message' => "Session 2 message {$i}",
                'client_nonce' => "s2-nonce-{$i}",
            ])->assertStatus(201)->assertJson([
                'billing' => ['billing_type' => 'introductory_free'],
            ]);
        }

        // 6th overall message across sessions now fails if user has 0 balance
        $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $sessionId2,
            'message' => 'Should fail now',
            'client_nonce' => 's2-nonce-6',
        ])->assertStatus(402);
    }

    public function test_allowance_is_independent_per_astrologer(): void
    {
        Sanctum::actingAs($this->user);

        // Exhaust all 5 free messages with Astrologer A
        UserAstrologerChatAllowance::create([
            'user_id' => $this->user->id,
            'astrologer_id' => $this->astrologerA->id,
            'free_messages_used' => 5,
        ]);

        // Pricing check for Astrologer A shows 0 free remaining
        $this->getJson("/api/chat/astrologers/{$this->astrologerA->id}/pricing")
            ->assertJson([
                'data' => ['free_messages_remaining' => 0],
            ]);

        // Pricing check for Astrologer B shows FULL 5 free messages remaining!
        $this->getJson("/api/chat/astrologers/{$this->astrologerB->id}/pricing")
            ->assertJson([
                'data' => [
                    'free_messages_remaining' => 5,
                    'customer_price' => 0.12,
                ],
            ]);

        // User can send free message to Astrologer B even with 0 wallet balance
        $sessionB = ChatSession::create([
            'admin_id' => $this->adminB->id,
            'astrologer_id' => $this->astrologerB->id,
            'user_id' => $this->user->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $sessionB->id,
            'message' => 'Free message to Astrologer B',
            'client_nonce' => 'b-nonce-1',
        ])->assertStatus(201)
            ->assertJson([
                'billing' => [
                    'billing_type' => 'introductory_free',
                    'free_messages_remaining' => 4,
                ],
            ]);
    }

    public function test_explicit_free_chat_does_not_consume_introductory_allowance(): void
    {
        Sanctum::actingAs($this->user);

        // Set Astrologer A's chat consultation service to explicit free chat
        $service = ProfessionalService::where('admin_id', $this->adminA->id)
            ->where('pricing_model', 'per_message')
            ->first();
        $this->assertNotNull($service);
        $service->update(['is_free_chat' => true]);

        // Pricing endpoint reports is_free_chat = true, free_messages_remaining = 5
        $this->getJson("/api/chat/astrologers/{$this->astrologerA->id}/pricing")
            ->assertJson([
                'data' => [
                    'is_free_chat' => true,
                    'customer_price' => 0.0,
                    'can_send_message' => true,
                    'free_messages_remaining' => 5,
                ],
            ]);

        $session = ChatSession::create([
            'admin_id' => $this->adminA->id,
            'astrologer_id' => $this->astrologerA->id,
            'user_id' => $this->user->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        // Send message under explicit free chat
        $res = $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $session->id,
            'message' => 'Hello during explicit free chat',
            'client_nonce' => 'free-chat-nonce-1',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'billing' => [
                    'billing_type' => 'explicit_free',
                    'customer_price' => 0.0,
                    'free_messages_remaining' => 5,
                ],
            ]);

        // Allowance in DB must NOT be consumed
        $allowance = UserAstrologerChatAllowance::where('user_id', $this->user->id)
            ->where('astrologer_id', $this->astrologerA->id)
            ->first();
        $this->assertTrue($allowance === null || $allowance->free_messages_used === 0);
    }

    public function test_astrologer_reply_never_debits_and_has_astrologer_reply_billing_type(): void
    {
        $session = ChatSession::create([
            'admin_id' => $this->adminA->id,
            'astrologer_id' => $this->astrologerA->id,
            'user_id' => $this->user->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        // Act as Astrologer admin
        $this->actingAs($this->adminA, 'sanctum');

        $res = $this->postJson('/api/astrologer/chat/message/send', [
            'chat_session_id' => $session->id,
            'message' => 'Namaste! I am here to help you.',
            'client_nonce' => 'astrologer-reply-nonce-1',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'message' => [
                    'sender' => 'astrologer',
                    'billing_type' => 'astrologer_reply',
                    'customer_price' => 0.0,
                ],
            ]);

        $chat = Chat::where('client_nonce', 'astrologer-reply-nonce-1')->first();
        $this->assertNotNull($chat);
        $this->assertEquals('astrologer', $chat->sender);
        $this->assertEquals('astrologer_reply', $chat->billing_type);
        $this->assertEquals(0.0, (float) $chat->customer_price);

        // User allowance is untouched
        $this->assertEquals(0, UserAstrologerChatAllowance::count());
    }

    public function test_idempotent_send_message_with_client_nonce_prevents_double_billing(): void
    {
        Sanctum::actingAs($this->user);

        // User has exhausted free messages and has ₹1.00 wallet
        $this->user->update(['wallet_balance' => 1.00]);
        UserAstrologerChatAllowance::create([
            'user_id' => $this->user->id,
            'astrologer_id' => $this->astrologerA->id,
            'free_messages_used' => 5,
        ]);

        $session = ChatSession::create([
            'admin_id' => $this->adminA->id,
            'astrologer_id' => $this->astrologerA->id,
            'user_id' => $this->user->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        // First attempt with client_nonce
        $res1 = $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $session->id,
            'message' => 'Hello idempotency',
            'client_nonce' => 'idempotent-nonce-999',
        ]);
        $res1->assertStatus(201);
        $this->assertEquals(0.88, (float) $this->user->fresh()->wallet);
        $this->assertEquals(1, UserWalletTransaction::count());

        // Duplicate attempt with the EXACT SAME client_nonce
        $res2 = $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $session->id,
            'message' => 'Hello idempotency',
            'client_nonce' => 'idempotent-nonce-999',
        ]);
        $res2->assertStatus(200);

        // Wallet balance must STILL be 0.88 (NOT debited twice to 0.76!)
        $this->assertEquals(0.88, (float) $this->user->fresh()->wallet);
        $this->assertEquals(1, UserWalletTransaction::count());
        $this->assertEquals(1, Chat::where('client_nonce', 'idempotent-nonce-999')->count());
    }

    public function test_legacy_deduct_fee_endpoint_returns_http_410_gone(): void
    {
        Sanctum::actingAs($this->user);

        $session = ChatSession::create([
            'admin_id' => $this->adminA->id,
            'astrologer_id' => $this->astrologerA->id,
            'user_id' => $this->user->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        $res = $this->postJson('/api/chat/deduct-fee', [
            'chat_session_id' => $session->id,
        ]);

        $res->assertStatus(410)
            ->assertJson([
                'success' => false,
                'deprecated' => true,
                'error' => 'per_minute_billing_retired',
            ]);
    }

    public function test_multi_language_astrologers_share_admin_id_chat_availability_and_allowance(): void
    {
        Sanctum::actingAs($this->user);

        // Create Hindi profile sharing adminA
        $astrologerAHindi = Astrologer::create([
            'admin_id' => $this->adminA->id,
            'name' => 'आचार्य रमन',
            'language' => 'hi',
            'astrologer_language' => 'हिन्दी',
            'expertise' => 'वैदिक ज्योतिष',
            'experience' => '12 वर्ष',
            'price' => 0.10,
            'status' => 'online',
            'active' => 1,
        ]);

        // Both English and Hindi profiles must have has_active_chat == true
        $this->assertTrue($this->astrologerA->fresh()->has_active_chat);
        $this->assertTrue($astrologerAHindi->fresh()->has_active_chat);

        // 1. Initiate session using English profile without explicit admin_id
        $initEn = $this->postJson('/api/chat/start-session', [
            'astrologer_id' => $this->astrologerA->id,
        ]);
        $initEn->assertStatus(200)
            ->assertJson([
                'status' => 'new',
                'admin_id' => $this->adminA->id,
                'astrologer_id' => $this->astrologerA->id,
            ]);
        $sessionId = $initEn->json('chat_session_id');

        // Send 1 introductory message in English session
        $sendEn = $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $sessionId,
            'message' => 'Namaste Guruji in English',
            'client_nonce' => 'nonce-en-1',
        ]);
        $sendEn->assertStatus(201);
        $this->assertEquals(4, $sendEn->json('billing.free_messages_remaining'));

        // 2. Initiate session using Hindi profile (same admin)
        $initHi = $this->postJson('/api/chat/start-session', [
            'astrologer_id' => $astrologerAHindi->id,
        ]);
        $initHi->assertStatus(200)
            ->assertJson([
                'status' => 'existing',
                'chat_session_id' => $sessionId,
                'admin_id' => $this->adminA->id,
                'astrologer_id' => $astrologerAHindi->id,
            ]);

        // Check pricing on Hindi profile reflects remaining free allowance (4 remaining, NOT reset to 5)
        $pricingHi = $this->getJson("/api/chat/pricing/{$astrologerAHindi->id}");
        $pricingHi->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'astrologer_id' => $astrologerAHindi->id,
                    'admin_id' => $this->adminA->id,
                    'free_messages_used' => 1,
                    'free_messages_remaining' => 4,
                ],
            ]);
    }

    public function test_missing_admin_id_resolves_reliably_on_session_and_message_send(): void
    {
        Sanctum::actingAs($this->user);

        // 1. startSession called without admin_id resolves from astrologer
        $init = $this->postJson('/api/chat/start-session', [
            'astrologer_id' => $this->astrologerB->id,
        ]);
        $init->assertStatus(200)
            ->assertJson([
                'status' => 'new',
                'admin_id' => $this->adminB->id,
                'astrologer_id' => $this->astrologerB->id,
            ]);

        $session = ChatSession::find($init->json('chat_session_id'));
        $this->assertEquals($this->adminB->id, $session->admin_id);

        // 2. sendMessage succeeds and delivers message with resolved admin_id
        $send = $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $session->id,
            'message' => 'Testing admin resolution',
            'client_nonce' => 'nonce-recovery-1',
        ]);
        $send->assertStatus(201);
        $this->assertEquals($this->adminB->id, $session->fresh()->admin_id);
    }

    public function test_inactive_chat_service_authoritatively_rejects_with_http_422(): void
    {
        Sanctum::actingAs($this->user);

        // Deactivate astrologer A's chat service
        $chatService = ProfessionalService::where('admin_id', $this->adminA->id)
            ->where(function ($q) {
                $q->where('offering_type', 'chat')
                  ->orWhere('pricing_model', 'per_message')
                  ->orWhereJsonContains('delivery_methods', 'chat');
            })
            ->first();

        $this->assertNotNull($chatService);
        $chatService->update(['is_active' => false]);

        // Model attribute has_active_chat must evaluate to false
        $this->assertFalse($this->astrologerA->fresh()->has_active_chat);

        $session = ChatSession::create([
            'admin_id' => $this->adminA->id,
            'astrologer_id' => $this->astrologerA->id,
            'user_id' => $this->user->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        // sendMessage must reject with HTTP 422
        $res = $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $session->id,
            'message' => 'Should fail when inactive',
            'client_nonce' => 'nonce-inactive-test',
        ]);

        $res->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Chat consultation service is currently not available for this astrologer.',
            ]);
    }
}
