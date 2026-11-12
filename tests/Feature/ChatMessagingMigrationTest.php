<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\Chat;
use App\Models\ChatBroadcastOutbox;
use App\Models\ChatSession;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use App\Services\Chat\AblyAuthService;
use App\Services\Chat\ChatBroadcaster;
use App\Services\Chat\ChatNotificationService;
use App\Services\FirebaseNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ChatMessagingMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Admin $admin;
    protected Astrologer $astrologer;
    protected ChatSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        $this->user = User::create([
            'name' => 'Arjun Verma',
            'email' => 'arjun@example.com',
            'password' => bcrypt('password123'),
            'wallet_balance' => 500.00,
            'role' => 'free',
            'status' => 'active',
            'language' => 'en',
            'fcm_token' => 'fcm_user_token_abc123',
        ]);

        // Create astrologer admin
        $this->admin = Admin::create([
            'name' => 'Pandit Shastri',
            'email' => 'shastri@example.com',
            'password' => bcrypt('password123'),
            'fcm_token' => 'fcm_astrologer_token_xyz789',
        ]);

        Role::findOrCreate('Astrologer', 'admin');
        $this->admin->assignRole('Astrologer');

        // Create astrologer profile
        $this->astrologer = Astrologer::create([
            'admin_id' => $this->admin->id,
            'name' => 'Pandit Shastri',
            'language' => 'en',
            'astrologer_language' => 'Hindi, English',
            'expertise' => 'Vedic Astrology',
            'experience' => '10 years',
            'price' => 25,
            'status' => 'online',
            'active' => 1,
        ]);

        // Create active chat session
        $this->session = ChatSession::create([
            'user_id' => $this->user->id,
            'admin_id' => $this->admin->id,
            'astrologer_id' => $this->astrologer->id,
            'started_at' => now(),
        ]);
    }

    public function test_user_can_obtain_scoped_ably_token_for_active_session(): void
    {
        // Provide mock Ably key for testing
        config(['broadcasting.connections.ably.key' => 'testAppId.testKeyId:testSecret1234567890']);

        Sanctum::actingAs($this->user);
        $response = $this->postJson('/api/en/chat/ably-token', [
                'chat_session_id' => $this->session->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'channel_name' => 'chat:session:' . $this->session->id,
                    'client_id' => 'user_' . $this->user->id,
                    'participant_type' => 'user',
                    'session_id' => $this->session->id,
                ],
            ]);

        $tokenRequest = $response->json('data.token_request');
        $this->assertNotEmpty($tokenRequest['mac']);
        $this->assertNotEmpty($tokenRequest['nonce']);
        $this->assertEquals('testAppId.testKeyId', $tokenRequest['keyName']);
        $this->assertStringContainsString('chat:session:' . $this->session->id, (string) $tokenRequest['capability']);
        $this->assertStringContainsString('subscribe', (string) $tokenRequest['capability']);
    }

    public function test_astrologer_admin_can_obtain_scoped_ably_token(): void
    {
        config(['broadcasting.connections.ably.key' => 'testAppId.testKeyId:testSecret1234567890']);

        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->postJson('/api/astrologer/chat/ably-token', [
                'chat_session_id' => $this->session->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'channel_name' => 'chat:session:' . $this->session->id,
                    'client_id' => 'astrologer_' . $this->admin->id,
                    'participant_type' => 'astrologer',
                ],
            ]);
    }

    public function test_unauthorized_user_cannot_access_other_users_session(): void
    {
        $otherUser = User::create([
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => bcrypt('password123'),
        ]);

        Sanctum::actingAs($otherUser);
        $response = $this->postJson('/api/en/chat/ably-token', [
                'chat_session_id' => $this->session->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_user_can_send_message_persisted_to_mysql(): void
    {
        $mockFCM = Mockery::mock(FirebaseNotificationService::class);
        $mockFCM->shouldReceive('sendToToken')
            ->once()
            ->andReturn(['success' => true, 'message_id' => 'projects/test/messages/123']);
        $this->app->instance(FirebaseNotificationService::class, $mockFCM);

        $nonce = (string) Str::uuid();

        Sanctum::actingAs($this->user);
        $response = $this->postJson('/api/en/chat/message/send', [
                'chat_session_id' => $this->session->id,
                'message' => 'Hello Panditji, please look at my 7th house.',
                'client_nonce' => $nonce,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'created',
                'message' => [
                    'chat_session_id' => $this->session->id,
                    'sender' => 'user',
                    'sender_name' => 'Arjun Verma',
                    'message' => 'Hello Panditji, please look at my 7th house.',
                    'client_nonce' => $nonce,
                ],
            ]);

        $this->assertDatabaseHas('chats', [
            'chat_session_id' => $this->session->id,
            'user_id' => $this->user->id,
            'sender' => 'user',
            'message' => 'Hello Panditji, please look at my 7th house.',
            'client_nonce' => $nonce,
        ]);
    }

    public function test_astrologer_can_send_message_persisted_to_mysql(): void
    {
        $mockFCM = Mockery::mock(FirebaseNotificationService::class);
        $mockFCM->shouldReceive('sendToToken')
            ->once()
            ->andReturn(['success' => true, 'message_id' => 'projects/test/messages/456']);
        $this->app->instance(FirebaseNotificationService::class, $mockFCM);

        $nonce = (string) Str::uuid();

        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->postJson('/api/astrologer/chat/message/send', [
                'chat_session_id' => $this->session->id,
                'message' => 'Namaste Arjun, Jupiter is favorably placed.',
                'client_nonce' => $nonce,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'created',
                'message' => [
                    'chat_session_id' => $this->session->id,
                    'sender' => 'astrologer',
                    'sender_name' => 'Pandit Shastri',
                    'message' => 'Namaste Arjun, Jupiter is favorably placed.',
                    'client_nonce' => $nonce,
                ],
            ]);

        $this->assertDatabaseHas('chats', [
            'chat_session_id' => $this->session->id,
            'sender' => 'astrologer',
            'message' => 'Namaste Arjun, Jupiter is favorably placed.',
        ]);
    }

    public function test_message_idempotency_via_client_nonce_prevents_duplicates(): void
    {
        $mockFCM = Mockery::mock(FirebaseNotificationService::class);
        $mockFCM->shouldReceive('sendToToken')->once()->andReturn(['success' => true]);
        $this->app->instance(FirebaseNotificationService::class, $mockFCM);

        $nonce = (string) Str::uuid();

        // 1st request -> Created (201)
        Sanctum::actingAs($this->user);
        $first = $this->postJson('/api/en/chat/message/send', [
                'chat_session_id' => $this->session->id,
                'message' => 'Idempotent message check',
                'client_nonce' => $nonce,
            ]);
        $first->assertStatus(201);
        $originalId = $first->json('message.id');

        // 2nd retry request with identical client_nonce -> OK (200), same ID, no duplicate row
        $second = $this->postJson('/api/en/chat/message/send', [
                'chat_session_id' => $this->session->id,
                'message' => 'Idempotent message check',
                'client_nonce' => $nonce,
            ]);
        $second->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'duplicate_returned',
                'message' => [
                    'id' => $originalId,
                    'client_nonce' => $nonce,
                ],
            ]);

        $this->assertEquals(1, Chat::where('client_nonce', $nonce)->count());
    }

    public function test_get_messages_returns_chronological_list_with_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Chat::create([
                'chat_session_id' => $this->session->id,
                'user_id' => $this->user->id,
                'admin_id' => $this->admin->id,
                'astrologer_id' => $this->astrologer->id,
                'message' => "Message {$i}",
                'sender' => $i % 2 === 0 ? 'astrologer' : 'user',
                'sent_at' => now()->subMinutes(6 - $i),
            ]);
        }

        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/en/chat/sessions/{$this->session->id}/messages?limit=3");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 3,
                'has_more' => true,
            ]);

        $messages = $response->json('messages');
        $this->assertCount(3, $messages);
        // Latest 3 messages in ascending order: Message 3, Message 4, Message 5
        $this->assertEquals('Message 3', $messages[0]['message']);
        $this->assertEquals('Message 5', $messages[2]['message']);

        // Test before_id pagination
        $firstId = $messages[0]['id'];
        $olderResponse = $this->getJson("/api/en/chat/sessions/{$this->session->id}/messages?limit=3&before_id={$firstId}");

        $olderResponse->assertStatus(200);
        $olderMessages = $olderResponse->json('messages');
        $this->assertCount(2, $olderMessages);
        $this->assertEquals('Message 1', $olderMessages[0]['message']);
        $this->assertEquals('Message 2', $olderMessages[1]['message']);
    }

    public function test_after_id_catchup_retrieves_only_new_messages(): void
    {
        $c1 = Chat::create([
            'chat_session_id' => $this->session->id,
            'user_id' => $this->user->id,
            'admin_id' => $this->admin->id,
            'astrologer_id' => $this->astrologer->id,
            'message' => 'First',
            'sender' => 'user',
            'sent_at' => now()->subMinutes(2),
        ]);

        $c2 = Chat::create([
            'chat_session_id' => $this->session->id,
            'user_id' => $this->user->id,
            'admin_id' => $this->admin->id,
            'astrologer_id' => $this->astrologer->id,
            'message' => 'Second',
            'sender' => 'astrologer',
            'sent_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/en/chat/sessions/{$this->session->id}/messages?after_id={$c1->id}");

        $response->assertStatus(200);
        $messages = $response->json('messages');
        $this->assertCount(1, $messages);
        $this->assertEquals('Second', $messages[0]['message']);
    }

    public function test_cannot_send_message_to_ended_session(): void
    {
        $this->session->update(['ended_at' => now()]);

        Sanctum::actingAs($this->user);
        $response = $this->postJson('/api/en/chat/message/send', [
                'chat_session_id' => $this->session->id,
                'message' => 'Trying to message closed session',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This chat session has already ended.',
            ]);
    }

    public function test_user_can_end_session_and_sets_ended_at(): void
    {
        $this->assertNull($this->session->ended_at);

        Sanctum::actingAs($this->user);
        $response = $this->postJson('/api/en/chat/session/end', [
                'chat_session_id' => $this->session->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'ended',
            ]);

        $this->assertNotNull($this->session->fresh()->ended_at);
    }

    public function test_outbox_retry_command_runs_safely(): void
    {
        $chat = Chat::create([
            'chat_session_id' => $this->session->id,
            'user_id' => $this->user->id,
            'admin_id' => $this->admin->id,
            'astrologer_id' => $this->astrologer->id,
            'message' => 'Outbox test',
            'sender' => 'user',
            'sent_at' => now(),
        ]);

        ChatBroadcastOutbox::create([
            'chat_id' => $chat->id,
            'chat_session_id' => $this->session->id,
            'payload' => ['id' => $chat->id, 'message' => 'Outbox test'],
            'attempts' => 0,
            'status' => 'pending',
        ]);

        $exitCode = $this->artisan('chat:retry-broadcasts', ['--limit' => 10]);
        $exitCode->assertExitCode(0);
    }
}
