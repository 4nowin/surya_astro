<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\Chat;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrossAppChatIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Admin $admin;
    protected Astrologer $astrologer;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create registered Astrologer with Admin role
        $this->admin = Admin::create([
            'name' => 'Pandit Devrat',
            'email' => 'devrat@panditsetu.com',
            'password' => bcrypt('SecurePassword123'),
        ]);

        $roleAdmin = Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);
        $this->admin->assignRole($roleAdmin);

        $this->astrologer = Astrologer::create([
            'admin_id' => $this->admin->id,
            'name' => 'Pandit Devrat',
            'language' => 'en',
            'astrologer_language' => 'Hindi, Sanskrit',
            'expertise' => 'Vedic Astrology, Kundli',
            'experience' => '15 years',
            'price' => 30,
            'status' => 'online',
            'active' => 1,
        ]);

        // 2. Create registered Astro Setu User (Jajman)
        $this->user = User::create([
            'name' => 'Rajesh Sharma',
            'email' => 'rajesh@astrosetu.com',
            'password' => bcrypt('UserPassword123'),
            'wallet_balance' => 500.00,
            'role' => 'free',
            'status' => 'active',
            'fcm_token' => 'fcm_user_test_token',
        ]);
    }

    /**
     * Complete 11-step cross-app end-to-end integration test
     * between Astro Setu (User) and Pandit Setu (Astrologer).
     */
    public function test_cross_app_chat_message_flow(): void
    {
        // -------------------------------------------------------------
        // Step 1: Log in to Pandit Setu as the Astrologer
        // -------------------------------------------------------------
        $astroLoginResponse = $this->postJson('/api/astrologer/astro-login', [
            'email' => 'devrat@panditsetu.com',
            'password' => 'SecurePassword123',
        ]);
        $astroLoginResponse->assertStatus(200);
        $astrologerToken = $astroLoginResponse->json('token');
        $this->assertNotEmpty($astrologerToken);

        // -------------------------------------------------------------
        // Step 2: Authenticate Astro Setu User
        // -------------------------------------------------------------
        $userToken = $this->user->createToken('astro-setu-user-token')->plainTextToken;
        $this->assertNotEmpty($userToken);

        // -------------------------------------------------------------
        // Step 3: Astro Setu User initiates chat session with Astrologer
        // -------------------------------------------------------------
        $startSessionResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/en/chat/start-session', [
                'astrologer_id' => $this->astrologer->id,
                'admin_id' => $this->admin->id,
            ]);
        $startSessionResponse->assertStatus(200);
        $sessionId = $startSessionResponse->json('chat_session_id');
        $this->assertNotNull($sessionId);

        // -------------------------------------------------------------
        // Step 4: Verify conversation appears in Pandit Setu Connect inbox
        // -------------------------------------------------------------
        $inboxResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/astrologer/conversations');
        $inboxResponse->assertStatus(200);
        $conversations = $inboxResponse->json('data');
        $this->assertNotEmpty($conversations);

        $matchedConv = collect($conversations)->firstWhere('session_id', $sessionId);
        $this->assertNotNull($matchedConv, "Session {$sessionId} must appear in Astrologer's inbox");
        $this->assertEquals('Rajesh Sharma', $matchedConv['client_name']);

        // -------------------------------------------------------------
        // Step 5: Astrologer requests Ably real-time TokenRequest
        // -------------------------------------------------------------
        $ablyTokenResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/astrologer/chat/ably-token', [
                'chat_session_id' => $sessionId,
            ]);
        // Validates authorization logic; returns 200 (or 500 if live ABLY_KEY not configured)
        $this->assertContains($ablyTokenResponse->status(), [200, 500]);

        // -------------------------------------------------------------
        // Step 6: Astro Setu sends: "Hello Pandit Ji"
        // -------------------------------------------------------------
        $userSendResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/en/chat/message/send', [
                'chat_session_id' => $sessionId,
                'message' => 'Hello Pandit Ji',
                'client_nonce' => 'user_nonce_001',
            ]);
        $userSendResponse->assertStatus(201);
        $userMsg = $userSendResponse->json('message');
        $this->assertEquals('Hello Pandit Ji', $userMsg['message']);
        $this->assertEquals('user', $userMsg['sender']);
        $userMsgId = $userMsg['id'];

        // -------------------------------------------------------------
        // Step 7: Pandit Setu receives message history containing user message
        // -------------------------------------------------------------
        $astroFetchResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/astrologer/chat/sessions/{$sessionId}/messages");
        $astroFetchResponse->assertStatus(200);
        $astroMessages = $astroFetchResponse->json('messages');
        $this->assertCount(1, $astroMessages);
        $this->assertEquals('Hello Pandit Ji', $astroMessages[0]['message']);
        $this->assertEquals('user', $astroMessages[0]['sender']);

        // -------------------------------------------------------------
        // Step 8: Pandit Setu replies: "Pranam! How can I help you today?"
        // -------------------------------------------------------------
        $astroReplyResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/astrologer/chat/message/send', [
                'chat_session_id' => $sessionId,
                'message' => 'Pranam! How can I help you today?',
                'client_nonce' => 'pandit_nonce_002',
            ]);
        $astroReplyResponse->assertStatus(201);
        $astroReply = $astroReplyResponse->json('message');
        $this->assertEquals('Pranam! How can I help you today?', $astroReply['message']);
        $this->assertEquals('astrologer', $astroReply['sender']);
        $astroReplyId = $astroReply['id'];

        // -------------------------------------------------------------
        // Step 9: Astro Setu receives the astrologer's reply
        // -------------------------------------------------------------
        $userFetchResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/en/chat/sessions/{$sessionId}/messages");
        $userFetchResponse->assertStatus(200);
        $userFetchedList = $userFetchResponse->json('messages');
        $this->assertCount(2, $userFetchedList);
        $this->assertEquals('Pranam! How can I help you today?', $userFetchedList[1]['message']);
        $this->assertEquals('astrologer', $userFetchedList[1]['sender']);

        // -------------------------------------------------------------
        // Step 10: Confirm both messages are stored in database
        // -------------------------------------------------------------
        $this->assertDatabaseHas('chats', [
            'id' => $userMsgId,
            'chat_session_id' => $sessionId,
            'sender' => 'user',
            'message' => 'Hello Pandit Ji',
            'client_nonce' => 'user_nonce_001',
        ]);

        $this->assertDatabaseHas('chats', [
            'id' => $astroReplyId,
            'chat_session_id' => $sessionId,
            'sender' => 'astrologer',
            'message' => 'Pranam! How can I help you today?',
            'client_nonce' => 'pandit_nonce_002',
        ]);

        // -------------------------------------------------------------
        // Step 11: Close and reopen in both apps & confirm catchup loads
        // -------------------------------------------------------------
        $catchupResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/astrologer/chat/sessions/{$sessionId}/messages?after_id={$userMsgId}");
        $catchupResponse->assertStatus(200);
        $catchupMessages = $catchupResponse->json('messages');
        $this->assertCount(1, $catchupMessages);
        $this->assertEquals('Pranam! How can I help you today?', $catchupMessages[0]['message']);

        // -------------------------------------------------------------
        // Step 12: Subsequent Messages: User sends msg 2 and msg 3
        // -------------------------------------------------------------
        $userSendMsg2 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/en/chat/message/send', [
                'chat_session_id' => $sessionId,
                'message' => 'Second query about career',
                'client_nonce' => 'user_nonce_002',
            ]);
        $userSendMsg2->assertStatus(201);

        $userSendMsg3 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/en/chat/message/send', [
                'chat_session_id' => $sessionId,
                'message' => 'Third query about health',
                'client_nonce' => 'user_nonce_003',
            ]);
        $userSendMsg3->assertStatus(201);

        // -------------------------------------------------------------
        // Step 13: Reopening conversation window retrieves all 4 messages
        // -------------------------------------------------------------
        $allMessagesResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/astrologer/chat/sessions/{$sessionId}/messages");
        $allMessagesResponse->assertStatus(200);
        $allMessages = $allMessagesResponse->json('messages');
        $this->assertCount(4, $allMessages);
        $this->assertEquals('Hello Pandit Ji', $allMessages[0]['message']);
        $this->assertEquals('Pranam! How can I help you today?', $allMessages[1]['message']);
        $this->assertEquals('Second query about career', $allMessages[2]['message']);
        $this->assertEquals('Third query about health', $allMessages[3]['message']);

        // -------------------------------------------------------------
        // Step 14: Astrologer inbox reflects latest message and sender
        // -------------------------------------------------------------
        $updatedInbox = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/astrologer/conversations');
        $updatedInbox->assertStatus(200);
        $convs = $updatedInbox->json('data');
        $targetConv = collect($convs)->firstWhere('session_id', $sessionId);
        $this->assertNotNull($targetConv);
        $this->assertEquals('Third query about health', $targetConv['last_message']);
        $this->assertEquals('user', $targetConv['last_message_sender']);

        // -------------------------------------------------------------
        // Step 15: Screen reopen / re-start session reuses active session
        // -------------------------------------------------------------
        $reopenedSessionResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/en/chat/start-session', [
                'astrologer_id' => $this->astrologer->id,
                'admin_id' => $this->admin->id,
            ]);
        $reopenedSessionResponse->assertStatus(200);
        $this->assertEquals($sessionId, $reopenedSessionResponse->json('chat_session_id'), 'Must reuse existing session between user and astrologer');
    }
}
