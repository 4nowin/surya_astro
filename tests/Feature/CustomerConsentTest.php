<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Astrologer;
use App\Models\ChatSession;
use App\Models\CustomerAstrologerConsent;
use App\Models\Jajman;
use App\Models\JajmanBirthDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerConsentTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected Admin $practitioner;
    protected Astrologer $astrologer;

    protected function setUp(): void
    {
        parent::setUp();

        $astroRole = Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        $this->customer = User::create([
            'name' => 'Aarav Sharma',
            'email' => 'aarav@example.com',
            'phone' => '+919876543210',
            'password' => bcrypt('password123'),
        ]);

        $this->practitioner = Admin::create([
            'name' => 'Pandit Dev Sharma',
            'email' => 'dev@astrosetu.com',
            'password' => bcrypt('password123'),
        ]);
        $this->practitioner->assignRole($astroRole);

        $this->astrologer = Astrologer::create([
            'admin_id' => $this->practitioner->id,
            'name' => 'Pandit Dev Sharma',
            'language' => 'en',
            'astrologer_language' => 'Hindi, English',
            'expertise' => 'Vedic Astrology',
            'experience' => '10 years',
            'price' => 20,
            'active' => 1,
            'status' => 'online',
        ]);
    }

    public function test_customer_can_query_initial_consent_status_and_defaults_to_prompt(): void
    {
        $token = $this->customer->createToken('token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/customer/consent/{$this->practitioner->id}?session_id=101");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'consent' => [
                    'admin_id' => $this->practitioner->id,
                    'status' => 'pending',
                    'has_consented' => false,
                    'should_prompt' => true,
                ],
            ]);
    }

    public function test_granting_consent_persists_across_sessions_and_suppresses_prompt(): void
    {
        $token = $this->customer->createToken('token')->plainTextToken;

        // 1. Grant consent
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/customer/consent', [
                'admin_id' => $this->practitioner->id,
                'action' => 'grant',
                'chat_session_id' => 101,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'consent' => [
                    'status' => 'granted',
                    'has_consented' => true,
                    'should_prompt' => false,
                ],
            ]);

        $this->assertDatabaseHas('customer_astrologer_consents', [
            'user_id' => $this->customer->id,
            'admin_id' => $this->practitioner->id,
            'status' => 'granted',
        ]);

        // 2. Query in a future session: should NOT prompt
        $futureSessionRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/customer/consent/{$this->practitioner->id}?session_id=202");

        $futureSessionRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'consent' => [
                    'status' => 'granted',
                    'has_consented' => true,
                    'should_prompt' => false,
                ],
            ]);
    }

    public function test_declining_consent_dismisses_for_current_session_but_reprompts_in_future_session(): void
    {
        $token = $this->customer->createToken('token')->plainTextToken;

        // 1. Decline in session 101
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/customer/consent', [
                'admin_id' => $this->practitioner->id,
                'action' => 'decline_session',
                'chat_session_id' => 101,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'consent' => [
                    'status' => 'declined_session',
                    'has_consented' => false,
                    'should_prompt' => false,
                ],
            ]);

        // 2. Same session 101: should NOT prompt
        $sameSessionRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/customer/consent/{$this->practitioner->id}?session_id=101");

        $sameSessionRes->assertStatus(200)
            ->assertJson([
                'consent' => [
                    'status' => 'declined_session',
                    'should_prompt' => false,
                ],
            ]);

        // 3. Different future session 202: MUST prompt again!
        $differentSessionRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/customer/consent/{$this->practitioner->id}?session_id=202");

        $differentSessionRes->assertStatus(200)
            ->assertJson([
                'consent' => [
                    'status' => 'declined_session',
                    'should_prompt' => true,
                ],
            ]);
    }

    public function test_withdrawing_consent_updates_status_and_reprompts(): void
    {
        $token = $this->customer->createToken('token')->plainTextToken;

        // Pre-create granted consent
        CustomerAstrologerConsent::create([
            'user_id' => $this->customer->id,
            'admin_id' => $this->practitioner->id,
            'status' => 'granted',
            'consented_at' => now(),
        ]);

        // Withdraw consent
        $withdrawRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/customer/consent/withdraw', [
                'admin_id' => $this->practitioner->id,
            ]);

        $withdrawRes->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('customer_astrologer_consents', [
            'user_id' => $this->customer->id,
            'admin_id' => $this->practitioner->id,
            'status' => 'withdrawn',
        ]);

        // Next check prompts again
        $checkRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/customer/consent/{$this->practitioner->id}?session_id=303");

        $checkRes->assertStatus(200)
            ->assertJson([
                'consent' => [
                    'status' => 'withdrawn',
                    'has_consented' => false,
                    'should_prompt' => true,
                ],
            ]);
    }

    public function test_server_side_enforcement_withholds_unconsented_details_in_conversations_and_jajman_crm(): void
    {
        $astroToken = $this->practitioner->createToken('astro-token')->plainTextToken;

        // 1. Establish chat session between customer and practitioner
        $session = ChatSession::create([
            'user_id' => $this->customer->id,
            'admin_id' => $this->practitioner->id,
            'astrologer_id' => $this->astrologer->id,
            'started_at' => now(),
        ]);

        // 2. Link a Jajman record to customer
        $jajman = Jajman::create([
            'name' => 'Aarav Sharma',
            'phone' => '+919876543210',
            'email' => 'aarav@example.com',
            'city' => 'Delhi',
            'date_of_birth' => '1995-05-15',
            'user_id' => $this->customer->id,
        ]);
        $jajman->professionals()->attach($this->practitioner->id, ['status' => 'active', 'source' => 'chat']);

        $birth = JajmanBirthDetail::create([
            'jajman_id' => $jajman->id,
            'date_of_birth' => '1995-05-15',
            'birth_time' => '14:30:00',
            'birth_place' => 'New Delhi',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'is_primary' => true,
        ]);

        // --- CASE A: Consent is NOT granted (status: pending/withdrawn) ---
        // Verify Conversations API withholds phone & email
        $convRes = $this->withHeader('Authorization', 'Bearer ' . $astroToken)
            ->getJson('/api/astrologer/conversations');

        $convRes->assertStatus(200);
        $convItem = collect($convRes->json('data'))->firstWhere('session_id', $session->id);
        $this->assertNotNull($convItem);
        $this->assertEquals('Aarav Sharma', $convItem['client_name']);
        $this->assertNull($convItem['client_phone'], 'Unconsented phone must be withheld');
        $this->assertNull($convItem['client_email'], 'Unconsented email must be withheld');
        $this->assertFalse($convItem['consent_granted']);

        // Verify Jajman show endpoint withholds sensitive contact & birth details
        $jajmanRes = $this->withHeader('Authorization', 'Bearer ' . $astroToken)
            ->getJson("/api/astrologer/jajmans/{$jajman->id}");

        $jajmanRes->assertStatus(200);
        $jajmanData = $jajmanRes->json('data.jajman');
        $this->assertEquals('Aarav Sharma', $jajmanData['name']);
        $this->assertNull($jajmanData['phone'], 'Unconsented phone must be withheld');
        $this->assertNull($jajmanData['email'], 'Unconsented email must be withheld');
        $this->assertNull($jajmanData['date_of_birth'], 'Unconsented DOB must be withheld');
        $this->assertNull($jajmanData['primary_birth_detail'], 'Primary birth detail must be withheld');
        $this->assertEmpty($jajmanData['birth_details'], 'Birth details list must be withheld');
        $this->assertFalse($jajmanData['is_consent_granted']);

        // Verify Jajman Kundli endpoint returns 403 when consent not granted
        $kundliRes = $this->withHeader('Authorization', 'Bearer ' . $astroToken)
            ->getJson("/api/astrologer/jajmans/{$jajman->id}/kundli");
        $kundliRes->assertStatus(403)
            ->assertJson(['status' => 'consent_required']);

        // --- CASE B: Customer GRANTS consent ---
        CustomerAstrologerConsent::create([
            'user_id' => $this->customer->id,
            'admin_id' => $this->practitioner->id,
            'status' => 'granted',
            'consented_at' => now(),
        ]);

        // Conversations API now returns phone and email
        $convGrantedRes = $this->withHeader('Authorization', 'Bearer ' . $astroToken)
            ->getJson('/api/astrologer/conversations');
        $convGrantedItem = collect($convGrantedRes->json('data'))->firstWhere('session_id', $session->id);
        $this->assertEquals('+919876543210', $convGrantedItem['client_phone']);
        $this->assertEquals('aarav@example.com', $convGrantedItem['client_email']);
        $this->assertTrue($convGrantedItem['consent_granted']);

        // Jajman show endpoint now returns full details
        $jajmanGrantedRes = $this->withHeader('Authorization', 'Bearer ' . $astroToken)
            ->getJson("/api/astrologer/jajmans/{$jajman->id}");
        $jajmanGrantedData = $jajmanGrantedRes->json('data.jajman');
        $this->assertEquals('+919876543210', $jajmanGrantedData['phone']);
        $this->assertEquals('aarav@example.com', $jajmanGrantedData['email']);
        $this->assertNotNull($jajmanGrantedData['primary_birth_detail']);
        $this->assertTrue($jajmanGrantedData['is_consent_granted']);
    }

    public function test_manual_offline_jajman_created_by_practitioner_is_never_withheld(): void
    {
        $astroToken = $this->practitioner->createToken('astro-token')->plainTextToken;

        // Manual Jajman created by astrologer directly (user_id is NULL)
        $manualJajman = Jajman::create([
            'name' => 'Offline Walk-in Client',
            'phone' => '+919111222333',
            'email' => 'walkin@example.com',
            'city' => 'Varanasi',
            'date_of_birth' => '1988-12-01',
            'user_id' => null, // Offline, not linked to consumer app account
        ]);
        $manualJajman->professionals()->attach($this->practitioner->id, ['status' => 'active', 'source' => 'manual']);

        JajmanBirthDetail::create([
            'jajman_id' => $manualJajman->id,
            'date_of_birth' => '1988-12-01',
            'birth_time' => '10:00:00',
            'birth_place' => 'Varanasi',
            'latitude' => 25.3176,
            'longitude' => 82.9739,
            'is_primary' => true,
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $astroToken)
            ->getJson("/api/astrologer/jajmans/{$manualJajman->id}");

        $res->assertStatus(200);
        $data = $res->json('data.jajman');

        $this->assertEquals('+919111222333', $data['phone'], 'Manual offline Jajman phone must never be withheld');
        $this->assertEquals('walkin@example.com', $data['email']);
        $this->assertNotNull($data['primary_birth_detail'], 'Manual birth details must never be withheld');
        $this->assertTrue($data['is_consent_granted']);
        $this->assertEquals('manual', $data['consent_status']);
    }
}
