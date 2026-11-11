<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAstrologyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AstrologyProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/astrology/profiles');
        $response->assertUnauthorized();
    }

    public function test_can_create_verified_profile_with_safeguards_enabled(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name'                  => 'Arjun Sharma',
            'relationship'          => 'self',
            'date_of_birth'         => '1990-05-15',
            'birth_time_precision'  => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_time'            => '14:30:00',
            'birth_city'            => 'New Delhi',
            'birth_state'           => 'Delhi',
            'birth_country'         => 'India',
            'latitude'              => 28.6139391,
            'longitude'             => 77.2090212,
            'timezone'              => 'Asia/Kolkata',
        ];

        $response = $this->postJson('/api/v1/astrology/profiles', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.birth_time_precision', 'verified')
            ->assertJsonPath('data.birth_time', '14:30:00')
            ->assertJsonPath('data.approximate_time_window', null)
            ->assertJsonPath('data.can_calculate_exact_chart', true)
            ->assertJsonPath('data.can_calculate_lagna', true)
            ->assertJsonPath('data.is_default', true); // First profile becomes default

        $this->assertDatabaseHas('user_astrology_profiles', [
            'user_id'              => $user->id,
            'name'                 => 'Arjun Sharma',
            'birth_time_precision' => 'verified',
            'is_default'           => 1,
        ]);
    }

    public function test_can_create_approximate_profile_with_safeguards_applied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name'                  => 'Priya Sharma',
            'relationship'          => 'spouse',
            'date_of_birth'         => '1992-08-20',
            'birth_time_precision'  => UserAstrologyProfile::PRECISION_APPROXIMATE,
            'approximate_time_window' => 'morning',
            'birth_city'            => 'Mumbai',
            'birth_state'           => 'Maharashtra',
            'birth_country'         => 'India',
            'latitude'              => 19.0760900,
            'longitude'             => 72.8774260,
            'timezone'              => 'Asia/Kolkata',
        ];

        $response = $this->postJson('/api/v1/astrology/profiles', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.birth_time_precision', 'approximate')
            ->assertJsonPath('data.approximate_time_window', 'morning')
            ->assertJsonPath('data.birth_time', null)
            ->assertJsonPath('data.can_calculate_exact_chart', false)
            ->assertJsonPath('data.can_calculate_lagna', false);
    }

    public function test_can_create_unknown_precision_profile_never_invents_time(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name'                  => 'Elder Relative',
            'relationship'          => 'parent',
            'date_of_birth'         => '1960-01-01',
            'birth_time_precision'  => UserAstrologyProfile::PRECISION_UNKNOWN,
            'birth_city'            => 'Varanasi',
            'birth_state'           => 'Uttar Pradesh',
            'birth_country'         => 'India',
            'latitude'              => 25.3176452,
            'longitude'             => 82.9739144,
            'timezone'              => 'Asia/Kolkata',
        ];

        $response = $this->postJson('/api/v1/astrology/profiles', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.birth_time_precision', 'unknown')
            ->assertJsonPath('data.birth_time', null)
            ->assertJsonPath('data.approximate_time_window', null)
            ->assertJsonPath('data.can_calculate_exact_chart', false)
            ->assertJsonPath('data.can_calculate_lagna', false);
    }

    public function test_validation_rejects_missing_time_for_verified_precision(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name'                  => 'Invalid Profile',
            'date_of_birth'         => '1990-01-01',
            'birth_time_precision'  => UserAstrologyProfile::PRECISION_VERIFIED,
            // birth_time missing
            'birth_city'            => 'Pune',
            'birth_country'         => 'India',
            'latitude'              => 18.5204303,
            'longitude'             => 73.8567437,
            'timezone'              => 'Asia/Kolkata',
        ];

        $response = $this->postJson('/api/v1/astrology/profiles', $payload);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_time']);
    }

    public function test_validation_rejects_invalid_coordinates_and_timezone(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name'                  => 'Bad Coords Profile',
            'date_of_birth'         => '1990-01-01',
            'birth_time_precision'  => UserAstrologyProfile::PRECISION_UNKNOWN,
            'birth_city'            => 'Nowhere',
            'birth_country'         => 'Fantasy',
            'latitude'              => 150.0, // Invalid: exceeds 90
            'longitude'             => -250.0, // Invalid: exceeds -180
            'timezone'              => 'Invalid/Timezone',
        ];

        $response = $this->postJson('/api/v1/astrology/profiles', $payload);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude', 'timezone']);
    }

    public function test_validation_rejects_future_birth_date(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name'                  => 'Time Traveler',
            'date_of_birth'         => date('Y-m-d', strtotime('+1 year')),
            'birth_time_precision'  => UserAstrologyProfile::PRECISION_UNKNOWN,
            'birth_city'            => 'Bengaluru',
            'birth_country'         => 'India',
            'latitude'              => 12.9715987,
            'longitude'             => 77.5945627,
            'timezone'              => 'Asia/Kolkata',
        ];

        $response = $this->postJson('/api/v1/astrology/profiles', $payload);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_user_ownership_isolation_strictly_enforced(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $profileA = UserAstrologyProfile::create([
            'user_id'              => $userA->id,
            'name'                 => 'User A Profile',
            'date_of_birth'        => '1985-06-10',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_UNKNOWN,
            'birth_city'           => 'Delhi',
            'birth_country'        => 'India',
            'latitude'             => 28.6139,
            'longitude'            => 77.2090,
            'timezone'             => 'Asia/Kolkata',
            'is_default'           => true,
        ]);

        // User B tries to view User A's profile
        Sanctum::actingAs($userB);
        $response = $this->getJson("/api/v1/astrology/profiles/{$profileA->id}");
        $response->assertNotFound();

        // User B tries to update User A's profile
        $response = $this->putJson("/api/v1/astrology/profiles/{$profileA->id}", [
            'name' => 'Hacked Profile Name',
        ]);
        $response->assertForbidden();

        // User B tries to delete User A's profile
        $response = $this->deleteJson("/api/v1/astrology/profiles/{$profileA->id}");
        $response->assertNotFound();

        // Verify profile A remains unaltered
        $this->assertDatabaseHas('user_astrology_profiles', [
            'id'   => $profileA->id,
            'name' => 'User A Profile',
        ]);
    }

    public function test_can_update_and_delete_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $profile = UserAstrologyProfile::create([
            'user_id'              => $user->id,
            'name'                 => 'Initial Name',
            'relationship'         => 'self',
            'date_of_birth'        => '1995-03-25',
            'birth_time_precision' => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_time'           => '10:15:00',
            'birth_city'           => 'Jaipur',
            'birth_country'        => 'India',
            'latitude'             => 26.9124,
            'longitude'            => 75.7872,
            'timezone'             => 'Asia/Kolkata',
            'is_default'           => true,
        ]);

        // Update name and city
        $response = $this->putJson("/api/v1/astrology/profiles/{$profile->id}", [
            'name'       => 'Updated Name',
            'birth_city' => 'Udaipur',
            'latitude'   => 24.5854,
            'longitude'  => 73.7125,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.birth_city', 'Udaipur');

        // Delete profile
        $deleteResponse = $this->deleteJson("/api/v1/astrology/profiles/{$profile->id}");
        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('user_astrology_profiles', [
            'id' => $profile->id,
        ]);
    }

    public function test_save_primary_endpoint_creates_or_updates_idempotently(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name'                  => 'Primary Initial',
            'relationship'          => 'self',
            'date_of_birth'         => '1991-04-12',
            'birth_time_precision'  => UserAstrologyProfile::PRECISION_VERIFIED,
            'birth_time'            => '08:30:00',
            'birth_city'            => 'Chandigarh',
            'birth_country'         => 'India',
            'latitude'              => 30.7333,
            'longitude'             => 76.7794,
            'timezone'              => 'Asia/Kolkata',
        ];

        // 1. Initial creation via POST /profiles/primary
        $response = $this->postJson('/api/v1/astrology/profiles/primary', $payload);
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Primary Initial')
            ->assertJsonPath('data.is_default', true);

        $this->assertEquals(1, $user->astrologyProfiles()->count());

        // 2. Subsequent update via PUT /profiles/primary updates without duplicate
        $payload['name'] = 'Primary Updated';
        $updateResponse = $this->putJson('/api/v1/astrology/profiles/primary', $payload);
        $updateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Primary Updated');

        $this->assertEquals(1, $user->astrologyProfiles()->count());
    }

    public function test_updating_non_existent_profile_id_returns_404_not_403(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/astrology/profiles/99999', [
            'name' => 'Ghost Profile',
        ]);

        $response->assertNotFound();
    }

    public function test_creating_primary_profile_when_one_exists_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name'                  => 'Initial First',
            'relationship'          => 'self',
            'date_of_birth'         => '1993-02-15',
            'birth_time_precision'  => UserAstrologyProfile::PRECISION_UNKNOWN,
            'birth_city'            => 'Bhopal',
            'birth_country'         => 'India',
            'latitude'              => 23.2599,
            'longitude'             => 77.4126,
            'timezone'              => 'Asia/Kolkata',
        ];

        $res1 = $this->postJson('/api/v1/astrology/profiles', $payload);
        $res1->assertCreated();
        $this->assertEquals(1, $user->astrologyProfiles()->count());

        // Post again with is_default = true & relationship = self
        $payload['name'] = 'Initial Updated Without Duplicate';
        $res2 = $this->postJson('/api/v1/astrology/profiles', $payload);
        $res2->assertCreated();

        // Must still be only 1 profile for this user
        $this->assertEquals(1, $user->astrologyProfiles()->count());
        $this->assertEquals('Initial Updated Without Duplicate', $user->astrologyProfiles()->first()->name);
    }
}
