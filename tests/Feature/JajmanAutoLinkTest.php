<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\ChatSession;
use App\Models\CustomerAstrologerConsent;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\ProfessionalService;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JajmanAutoLinkTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected Admin $practitioner;
    protected Astrologer $astrologer;
    protected ProfessionalService $chatService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Astrologer']);

        $this->customer = User::create([
            'name' => 'Rohan Varma',
            'email' => 'rohan.varma@example.com',
            'phone' => '+91 98765 43210',
            'password' => bcrypt('password123'),
            'wallet_balance' => 100.00,
        ]);

        UserAstrologyProfile::create([
            'user_id' => $this->customer->id,
            'name' => 'Rohan Varma',
            'is_default' => true,
            'date_of_birth' => '1995-08-20',
            'birth_time' => '14:30:00',
            'birth_time_precision' => 'verified',
            'birth_city' => 'Varanasi',
            'birth_state' => 'Uttar Pradesh',
            'birth_country' => 'India',
            'latitude' => 25.3176,
            'longitude' => 82.9739,
            'timezone' => 'Asia/Kolkata',
        ]);

        $this->practitioner = Admin::create([
            'name' => 'Pandit Dev Sharma',
            'email' => 'dev@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $this->practitioner->assignRole('Astrologer');

        $this->astrologer = Astrologer::create([
            'admin_id' => $this->practitioner->id,
            'name' => 'Pandit Dev Sharma',
            'language' => 'en',
            'astrologer_language' => 'Hindi, English',
            'expertise' => 'Vedic Astrology',
            'experience' => '12 years',
        ]);

        $this->chatService = ProfessionalService::create([
            'admin_id' => $this->practitioner->id,
            'title' => 'Vedic Consultation',
            'name' => 'Vedic Consultation',
            'slug' => 'vedic-consultation-' . uniqid(),
            'category' => 'consultation',
            'offering_type' => 'chat',
            'pricing_model' => 'per_message',
            'price' => 10.00,
            'currency' => 'INR',
            'is_active' => true,
            'is_public' => true,
            'is_free_chat' => false,
            'max_message_characters' => 1000,
        ]);
    }

    public function test_jajman_is_not_created_on_passive_screen_open_but_created_on_message_send(): void
    {
        $this->actingAs($this->customer, 'sanctum');

        // 1. Passive screen open: user starts session
        $startRes = $this->postJson('/api/chat/start-session', [
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
        ]);
        $startRes->assertStatus(200);
        $sessionId = $startRes->json('chat_session_id');

        // Assert Jajman is NOT created on passive screen open
        $this->assertEquals(
            0,
            Jajman::whereHas('professionals', fn($q) => $q->where('admins.id', $this->practitioner->id))->count()
        );

        // 2. Active chat initiation: user sends first message
        $sendRes = $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $sessionId,
            'message' => 'Pranam Panditji, please check my planetary positions.',
            'client_nonce' => 'nonce_test_001',
        ]);
        $sendRes->assertStatus(201);

        // Assert Jajman is now auto-created and linked
        $jajman = Jajman::whereHas('professionals', fn($q) => $q->where('admins.id', $this->practitioner->id))
            ->where('user_id', $this->customer->id)
            ->first();

        $this->assertNotNull($jajman);
        $this->assertEquals('Rohan Varma', $jajman->name);
        $this->assertEquals($this->customer->id, $jajman->user_id);

        // Check pivot values
        $pivot = DB::table('professional_jajmans')
            ->where('admin_id', $this->practitioner->id)
            ->where('jajman_id', $jajman->id)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertEquals('chat', $pivot->source);
        $this->assertEquals(0, $pivot->has_possible_duplicates);

        // Check birth details were synced
        $birthDetail = $jajman->primaryBirthDetail;
        $this->assertNotNull($birthDetail);
        $dob = is_string($birthDetail->date_of_birth) ? $birthDetail->date_of_birth : $birthDetail->date_of_birth->format('Y-m-d');
        $this->assertEquals('1995-08-20', $dob);
        $this->assertStringContainsString('Varanasi', $birthDetail->birth_place);
    }

    public function test_auto_linking_is_idempotent_on_subsequent_messages(): void
    {
        $this->actingAs($this->customer, 'sanctum');

        $startRes = $this->postJson('/api/chat/start-session', [
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
        ]);
        $sessionId = $startRes->json('chat_session_id');

        // Send 1st message
        $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $sessionId,
            'message' => 'First message',
            'client_nonce' => 'nonce_idemp_1',
        ])->assertStatus(201);

        // Send 2nd message
        $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $sessionId,
            'message' => 'Second message',
            'client_nonce' => 'nonce_idemp_2',
        ])->assertStatus(201);

        // Assert strictly 1 Jajman and 1 pivot link exist for this practitioner
        $jajmansCount = Jajman::whereHas('professionals', fn($q) => $q->where('admins.id', $this->practitioner->id))->count();
        $this->assertEquals(1, $jajmansCount);

        $pivotsCount = DB::table('professional_jajmans')
            ->where('admin_id', $this->practitioner->id)
            ->count();
        $this->assertEquals(1, $pivotsCount);
    }

    public function test_deduplicates_by_unique_normalized_phone_match_against_preexisting_manual_jajman(): void
    {
        // Astrologer manually created an offline client record earlier
        $manualJajman = Jajman::create([
            'name' => 'Rohan Varma (Offline)',
            'phone' => '09876543210', // Different format of +91 98765 43210
            'email' => 'old_rohan@example.com',
            'user_id' => null, // Offline entry
        ]);

        $manualJajman->professionals()->attach($this->practitioner->id, [
            'status' => 'active',
            'source' => 'manual',
            'has_possible_duplicates' => false,
        ]);

        $this->actingAs($this->customer, 'sanctum');

        $startRes = $this->postJson('/api/chat/start-session', [
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
        ]);
        $sessionId = $startRes->json('chat_session_id');

        $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $sessionId,
            'message' => 'Hello from app!',
            'client_nonce' => 'nonce_phone_dedup',
        ])->assertStatus(201);

        // Assert NO new Jajman was created
        $this->assertEquals(1, Jajman::count());

        // Assert the pre-existing manual Jajman is now associated with the user
        $manualJajman->refresh();
        $this->assertEquals($this->customer->id, $manualJajman->user_id);

        // Assert pivot updated to source = 'linked'
        $pivot = DB::table('professional_jajmans')
            ->where('admin_id', $this->practitioner->id)
            ->where('jajman_id', $manualJajman->id)
            ->first();

        $this->assertEquals('linked', $pivot->source);
        $this->assertEquals(0, $pivot->has_possible_duplicates);
    }

    public function test_deduplicates_by_unique_normalized_email_match_against_preexisting_manual_jajman(): void
    {
        // Astrologer manually created offline client with email
        $manualJajman = Jajman::create([
            'name' => 'Rohan V.',
            'phone' => null,
            'email' => '  Rohan.Varma@Example.COM  ',
            'user_id' => null,
        ]);

        $manualJajman->professionals()->attach($this->practitioner->id, [
            'status' => 'active',
            'source' => 'manual',
        ]);

        $this->actingAs($this->customer, 'sanctum');

        $startRes = $this->postJson('/api/chat/start-session', [
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
        ]);
        $sessionId = $startRes->json('chat_session_id');

        $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $sessionId,
            'message' => 'Hello via email match!',
            'client_nonce' => 'nonce_email_dedup',
        ])->assertStatus(201);

        $this->assertEquals(1, Jajman::count());
        $manualJajman->refresh();
        $this->assertEquals($this->customer->id, $manualJajman->user_id);
    }

    public function test_handles_ambiguous_matches_safely_without_blind_merge_and_flags_possible_duplicates(): void
    {
        // Astrologer has TWO pre-existing manual Jajmans sharing the same phone (e.g. father & son)
        $jajman1 = Jajman::create([
            'name' => 'Rohan Senior',
            'phone' => '9876543210',
            'user_id' => null,
        ]);
        $jajman1->professionals()->attach($this->practitioner->id, ['status' => 'active', 'source' => 'manual']);

        $jajman2 = Jajman::create([
            'name' => 'Rohan Junior',
            'phone' => '+919876543210',
            'user_id' => null,
        ]);
        $jajman2->professionals()->attach($this->practitioner->id, ['status' => 'active', 'source' => 'manual']);

        $this->actingAs($this->customer, 'sanctum');

        $startRes = $this->postJson('/api/chat/start-session', [
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
        ]);
        $sessionId = $startRes->json('chat_session_id');

        $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $sessionId,
            'message' => 'Hello from ambiguous phone!',
            'client_nonce' => 'nonce_ambig_1',
        ])->assertStatus(201);

        // System must NOT blindly merge. Total Jajmans should now be 3 (2 manual + 1 new for user).
        $this->assertEquals(3, Jajman::count());

        $newJajman = Jajman::where('user_id', $this->customer->id)->first();
        $this->assertNotNull($newJajman);

        // Check that new Jajman has possible duplicates flagged
        $newPivot = DB::table('professional_jajmans')
            ->where('admin_id', $this->practitioner->id)
            ->where('jajman_id', $newJajman->id)
            ->first();

        $this->assertEquals(1, $newPivot->has_possible_duplicates);
        $candidates = json_decode((string) $newPivot->duplicate_candidates, true);
        $this->assertContains($jajman1->id, $candidates);
        $this->assertContains($jajman2->id, $candidates);

        // Verify API response for practitioner includes duplicate flags
        $this->actingAs($this->practitioner, 'sanctum');
        $listRes = $this->getJson('/api/astrologer/jajmans');
        $listRes->assertStatus(200);

        $jajmanInList = collect($listRes->json('data'))->firstWhere('id', $newJajman->id);
        $this->assertTrue($jajmanInList['has_possible_duplicates']);
        $this->assertContains($jajman1->id, $jajmanInList['possible_duplicate_ids']);
        $this->assertContains($jajman2->id, $jajmanInList['possible_duplicate_ids']);
    }

    public function test_respects_task_b2_consent_rules_when_auto_created_jajman_is_queried(): void
    {
        $this->actingAs($this->customer, 'sanctum');

        $startRes = $this->postJson('/api/chat/start-session', [
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
        ]);
        $sessionId = $startRes->json('chat_session_id');

        $this->postJson('/api/chat/message/send', [
            'chat_session_id' => $sessionId,
            'message' => 'Chat without explicit consent yet',
            'client_nonce' => 'nonce_consent_check',
        ])->assertStatus(201);

        $jajman = Jajman::where('user_id', $this->customer->id)->first();
        $this->assertNotNull($jajman);

        // 1. Practitioner queries Jajman BEFORE consent is granted
        $this->actingAs($this->practitioner, 'sanctum');
        $showRes1 = $this->getJson("/api/astrologer/jajmans/{$jajman->id}");
        $showRes1->assertStatus(200);

        $jData1 = $showRes1->json('data.jajman');
        $this->assertFalse($jData1['is_consent_granted']);
        $this->assertNull($jData1['phone']);
        $this->assertNull($jData1['email']);
        $this->assertNull($jData1['date_of_birth']);
        $this->assertEmpty($jData1['birth_details']);
        $this->assertNull($jData1['primary_birth_detail']);

        // 2. Customer grants consent
        $this->actingAs($this->customer, 'sanctum');
        $this->postJson('/api/customer/consent', [
            'admin_id' => $this->practitioner->id,
            'action' => 'grant',
        ])->assertStatus(200);

        // 3. Practitioner queries Jajman AFTER consent is granted
        $this->actingAs($this->practitioner, 'sanctum');
        $showRes2 = $this->getJson("/api/astrologer/jajmans/{$jajman->id}");
        $showRes2->assertStatus(200);

        $jData2 = $showRes2->json('data.jajman');
        $this->assertTrue($jData2['is_consent_granted']);
        $this->assertEquals('+91 98765 43210', $jData2['phone']);
        $this->assertEquals('rohan.varma@example.com', $jData2['email']);
        $this->assertEquals('1995-08-20', $jData2['date_of_birth']);
        $this->assertNotNull($jData2['primary_birth_detail']);
    }
}
