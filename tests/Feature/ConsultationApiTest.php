<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\ChatSession;
use App\Models\Chat;
use App\Models\Consultation;
use App\Models\Jajman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConsultationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultations_and_conversations_api_lifecycle_and_isolation()
    {
        // 1. Ensure Astrologer role exists for admin guard
        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        // 2. Create Practitioner A & Astrologer profile
        $adminA = Admin::create([
            'name' => 'Pandit Shastri',
            'email' => 'shastri_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $adminA->assignRole('Astrologer');

        $astrologerA = Astrologer::create([
            'admin_id' => $adminA->id,
            'name' => 'Pandit Shastri',
            'language' => 'en',
            'astrologer_language' => 'Hindi, English',
            'expertise' => 'Vedic Astrology',
            'experience' => '10 years',
            'price' => 25,
            'status' => 'online',
            'active' => 1,
        ]);

        // 3. Create Practitioner B
        $adminB = Admin::create([
            'name' => 'Acharya Tiwari',
            'email' => 'tiwari_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $adminB->assignRole('Astrologer');

        // 4. Create Astro Setu client user & chat session with Astrologer A
        $userClient = User::create([
            'name' => 'Sunil Gupta',
            'email' => 'sunil_' . uniqid() . '@example.com',
            'mobile' => '9876543210',
            'password' => bcrypt('secret123'),
        ]);

        $session = ChatSession::create([
            'admin_id' => $adminA->id,
            'astrologer_id' => $astrologerA->id,
            'user_id' => $userClient->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        Chat::create([
            'chat_session_id' => $session->id,
            'user_id' => $userClient->id,
            'admin_id' => $adminA->id,
            'astrologer_id' => $astrologerA->id,
            'sender' => 'user',
            'message' => 'Namaste Guruji, I have a query regarding my career.',
            'sent_at' => now(),
        ]);

        // 5. Practitioner A creates a Jajman record matching the client user
        $jajmanA = Jajman::create([
            'name' => 'Sunil Gupta',
            'phone' => '9876543210',
            'user_id' => $userClient->id,
            'city' => 'Delhi',
        ]);
        $jajmanA->professionals()->attach($adminA->id, [
            'status' => 'active',
            'source' => 'manual',
        ]);

        // 6. Practitioner A fetches conversations -> Should list session and match Jajman CRM
        $convResponse = $this->actingAs($adminA, 'sanctum')->getJson('/api/astrologer/conversations');
        $convResponse->assertStatus(200);
        $convData = $convResponse->json('data');
        $this->assertCount(1, $convData);
        $this->assertEquals($session->id, $convData[0]['session_id']);
        $this->assertEquals('Sunil Gupta', $convData[0]['client_name']);
        $this->assertEquals($jajmanA->id, $convData[0]['jajman_id']);

        // 7. Practitioner B fetches conversations -> Should be empty (isolation)
        $convResponseB = $this->actingAs($adminB, 'sanctum')->getJson('/api/astrologer/conversations');
        $convResponseB->assertStatus(200);
        $this->assertCount(0, $convResponseB->json('data'));

        // 8. Practitioner A logs an in-person / puja consultation
        $consultationResponse = $this->actingAs($adminA, 'sanctum')->postJson('/api/astrologer/consultations', [
            'client_name' => 'Sunil Gupta',
            'jajman_id' => $jajmanA->id,
            'chat_session_id' => $session->id,
            'type' => 'ritual_puja',
            'status' => 'completed',
            'consultation_date' => now()->toDateString(),
            'duration_minutes' => 60,
            'fee_charged' => 2100.00,
            'summary' => 'Navagraha Shanti Puja conducted successfully.',
        ]);

        $consultationResponse->assertStatus(201);
        $consultationId = $consultationResponse->json('data.id');
        $this->assertEquals('ritual_puja', $consultationResponse->json('data.type'));
        $this->assertEquals(2100.00, $consultationResponse->json('data.fee_charged'));

        // 9. Practitioner A can list consultations
        $listResponseA = $this->actingAs($adminA, 'sanctum')->getJson('/api/astrologer/consultations');
        $listResponseA->assertStatus(200);
        $this->assertCount(1, $listResponseA->json('data'));

        // 10. Practitioner B attempts to list consultations -> Should NOT see Practitioner A's consultation
        $listResponseB = $this->actingAs($adminB, 'sanctum')->getJson('/api/astrologer/consultations');
        $listResponseB->assertStatus(200);
        $this->assertCount(0, $listResponseB->json('data'));

        // 11. Practitioner B attempts to record consultation linking Practitioner A's Jajman -> MUST 404/fail
        $failResponseB = $this->actingAs($adminB, 'sanctum')->postJson('/api/astrologer/consultations', [
            'client_name' => 'Unauthorized Client',
            'jajman_id' => $jajmanA->id,
            'type' => 'in_person',
            'status' => 'completed',
        ]);
        $failResponseB->assertStatus(404);
    }

    public function test_conversations_api_resolves_client_avatar_url_with_precedence()
    {
        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        $admin = Admin::create([
            'name' => 'Acharya Avatar Tester',
            'email' => 'avatar_tester_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $admin->assignRole('Astrologer');

        $astrologer = Astrologer::create([
            'admin_id' => $admin->id,
            'name' => 'Acharya Avatar Tester',
            'language' => 'en',
            'astrologer_language' => 'Hindi, English',
            'expertise' => 'Vedic Astrology',
            'experience' => '5 years',
            'price' => 20,
            'status' => 'online',
            'active' => 1,
        ]);

        // Case 1: User has OAuth profile image (https://...)
        $oauthUser = User::create([
            'name' => 'OAuth User',
            'email' => 'oauth_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
            'profile_image' => 'https://lh3.googleusercontent.com/a/oauth_user_photo.jpg',
        ]);
        $session1 = ChatSession::create([
            'admin_id' => $admin->id,
            'astrologer_id' => $astrologer->id,
            'user_id' => $oauthUser->id,
            'status' => 'ACTIVE',
        ]);

        // Case 2: User has local storage profile image
        $localUser = User::create([
            'name' => 'Local Image User',
            'email' => 'local_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
            'profile_image' => 'profile_images/user_avatar_456.jpg',
        ]);
        $session2 = ChatSession::create([
            'admin_id' => $admin->id,
            'astrologer_id' => $astrologer->id,
            'user_id' => $localUser->id,
            'status' => 'ACTIVE',
        ]);

        // Case 3: User has NO profile image, but linked Jajman has profile_photo
        $jajmanUser = User::create([
            'name' => 'Jajman Photo User',
            'email' => 'jajman_user_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
            'profile_image' => null,
        ]);
        $session3 = ChatSession::create([
            'admin_id' => $admin->id,
            'astrologer_id' => $astrologer->id,
            'user_id' => $jajmanUser->id,
            'status' => 'ACTIVE',
        ]);
        $jajman = Jajman::create([
            'name' => 'Jajman Photo User',
            'phone' => '9123456789',
            'user_id' => $jajmanUser->id,
            'profile_photo' => 'jajmans/photos/client_photo.jpg',
        ]);
        $jajman->professionals()->attach($admin->id, ['status' => 'active', 'source' => 'manual']);

        // Case 4: Neither user nor jajman has an image
        $noImageUser = User::create([
            'name' => 'No Image User',
            'email' => 'no_image_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
            'profile_image' => null,
        ]);
        $session4 = ChatSession::create([
            'admin_id' => $admin->id,
            'astrologer_id' => $astrologer->id,
            'user_id' => $noImageUser->id,
            'status' => 'ACTIVE',
        ]);

        // Fetch conversations
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/astrologer/conversations');
        $response->assertStatus(200);

        $data = collect($response->json('data'))->keyBy('session_id');

        // Check Case 1: OAuth preserved
        $this->assertEquals(
            'https://lh3.googleusercontent.com/a/oauth_user_photo.jpg',
            $data[$session1->id]['client_avatar_url']
        );

        // Check Case 2: Local storage resolved to public URL
        $this->assertStringContainsString(
            'storage/profile_images/user_avatar_456.jpg',
            $data[$session2->id]['client_avatar_url']
        );

        // Check Case 3: Fallback to Jajman photo
        $this->assertStringContainsString(
            'storage/jajmans/photos/client_photo.jpg',
            $data[$session3->id]['client_avatar_url']
        );

        // Check Case 4: Null when neither exists
        $this->assertNull($data[$session4->id]['client_avatar_url']);
    }
}
