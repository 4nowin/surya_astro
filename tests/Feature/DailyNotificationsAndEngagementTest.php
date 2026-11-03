<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserAstrologyProfile;
use App\Models\UserDevice;
use App\Models\UserNotificationPreference;
use App\Services\FirebaseNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class DailyNotificationsAndEngagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_device_registration_endpoint_registers_and_updates_devices_idempotently(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['language' => 'en']);

        // Register Device 1
        $response1 = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/devices/register', [
                'fcm_token' => 'fcm_token_device_1',
                'device_id' => 'uuid_device_1',
                'platform' => 'android',
                'device_model' => 'Pixel 7',
                'timezone' => 'Asia/Kolkata',
            ]);

        $response1->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'fcm_token' => 'fcm_token_device_1',
            'is_active' => true,
        ]);

        // Register Device 2 for same user (Multi-device support)
        $response2 = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/devices/register', [
                'fcm_token' => 'fcm_token_device_2',
                'device_id' => 'uuid_device_2',
                'platform' => 'android',
                'device_model' => 'Samsung S23',
                'timezone' => 'Asia/Kolkata',
            ]);

        $response2->assertOk();

        // Both devices must be present and active
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'fcm_token' => 'fcm_token_device_2',
            'is_active' => true,
        ]);
        $this->assertEquals(2, UserDevice::where('user_id', $user->id)->where('is_active', true)->count());

        // Preferences table must have detected timezone
        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $user->id,
            'timezone' => 'Asia/Kolkata',
        ]);
    }

    public function test_device_removal_deactivates_only_the_specified_device(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $d1 = UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'token_alpha',
            'device_id' => 'dev_1',
            'is_active' => true,
        ]);

        $d2 = UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'token_beta',
            'device_id' => 'dev_2',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/devices/remove', [
                'fcm_token' => 'token_alpha',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertFalse((bool) $d1->fresh()->is_active);
        $this->assertTrue((bool) $d2->fresh()->is_active);
    }

    public function test_get_and_update_notification_preferences(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Initial fetch returns approved defaults
        $getRes = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/preferences');

        $getRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.master_enabled', true)
            ->assertJsonPath('data.panchang_enabled', true)
            ->assertJsonPath('data.horoscope_enabled', true)
            ->assertJsonPath('data.personalized_enabled', true)
            ->assertJsonPath('data.reminders_enabled', false)
            ->assertJsonPath('data.preferred_delivery_hour', 7);

        // Update preferences
        $putRes = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'preferred_delivery_hour' => 8,
                'panchang_enabled' => false,
                'timezone' => 'Europe/London',
            ]);

        $putRes->assertOk()
            ->assertJsonPath('data.preferred_delivery_hour', 8)
            ->assertJsonPath('data.panchang_enabled', false)
            ->assertJsonPath('data.timezone', 'Europe/London');

        $this->assertDatabaseHas('user_notification_preferences', [
            'user_id' => $user->id,
            'preferred_delivery_hour' => 8,
            'panchang_enabled' => false,
            'timezone' => 'Europe/London',
        ]);
    }

    public function test_dispatcher_dispatches_with_idempotency_and_cleans_unregistered_tokens(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['language' => 'en']);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'valid_mock_token',
            'is_active' => true,
        ]);

        $invalidDevice = UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'unregistered_mock_token',
            'is_active' => true,
        ]);

        UserNotificationPreference::create([
            'user_id' => $user->id,
            'timezone' => 'Asia/Kolkata',
            'master_enabled' => true,
            'panchang_enabled' => true,
            'horoscope_enabled' => false,
            'personalized_enabled' => false,
            'preferred_delivery_hour' => 7,
        ]);

        // Mock FirebaseNotificationService
        $mockFcm = Mockery::mock(FirebaseNotificationService::class);
        $this->app->instance(FirebaseNotificationService::class, $mockFcm);

        // Expect call for valid device -> success
        $mockFcm->shouldReceive('sendToToken')
            ->with('valid_mock_token', Mockery::any(), Mockery::any(), Mockery::any())
            ->once()
            ->andReturn([
                'success' => true,
                'message_id' => 'projects/test/messages/msg_123',
                'error' => null,
                'unregistered' => false,
            ]);

        // Expect call for invalid device -> fails with unregistered
        $mockFcm->shouldReceive('sendToToken')
            ->with('unregistered_mock_token', Mockery::any(), Mockery::any(), Mockery::any())
            ->once()
            ->andReturn([
                'success' => false,
                'message_id' => null,
                'error' => 'Requested entity was not found',
                'unregistered' => true,
            ]);

        // Run single daily batch dispatch
        $exitCode = Artisan::call('notifications:dispatch', [
            '--date' => '2026-11-03',
            '--user' => $user->id,
        ]);

        $this->assertEquals(0, $exitCode);

        // Verify deliveries table has recorded both attempts
        $this->assertDatabaseHas('notification_deliveries', [
            'user_device_id' => $device->id,
            'notification_type' => 'daily_briefing',
            'status' => 'sent',
            'fcm_message_id' => 'projects/test/messages/msg_123',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'user_device_id' => $invalidDevice->id,
            'notification_type' => 'daily_briefing',
            'status' => 'failed',
        ]);

        // Invalid device must have been automatically deactivated
        $this->assertFalse((bool) $invalidDevice->fresh()->is_active);

        // SECOND EXECUTION ON SAME DATE (Idempotency verification)
        // Should NOT call sendToToken again because record is already 'sent'
        $exitCode2 = Artisan::call('notifications:dispatch', [
            '--date' => '2026-11-03',
            '--user' => $user->id,
        ]);

        $this->assertEquals(0, $exitCode2);
        // Only 1 sent record exists
        $this->assertEquals(1, NotificationDelivery::where('user_device_id', $device->id)->where('status', 'sent')->count());
    }

    public function test_consolidated_morning_briefing_combines_enabled_categories_into_single_push(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['language' => 'hi']);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'fcm_consolidated_device',
            'is_active' => true,
        ]);

        UserNotificationPreference::create([
            'user_id' => $user->id,
            'timezone' => 'Asia/Kolkata',
            'master_enabled' => true,
            'panchang_enabled' => true,
            'horoscope_enabled' => true,
            'personalized_enabled' => false,
            'reminders_enabled' => false,
            'preferred_delivery_hour' => 7,
        ]);

        $mockFcm = Mockery::mock(FirebaseNotificationService::class);
        $this->app->instance(FirebaseNotificationService::class, $mockFcm);

        // Expect sendToToken called EXACTLY ONCE with consolidated Panchang & Horoscope briefing
        $mockFcm->shouldReceive('sendToToken')
            ->once()
            ->with(
                'fcm_consolidated_device',
                Mockery::pattern('/पंचांग व राशिफल/'),
                Mockery::any(),
                Mockery::on(function ($data) {
                    return isset($data['type']) && $data['type'] === 'daily_briefing';
                })
            )
            ->andReturn([
                'success' => true,
                'message_id' => 'projects/test/messages/msg_consolidated_1',
                'error' => null,
                'unregistered' => false,
            ]);

        $exitCode = Artisan::call('notifications:dispatch', [
            '--date' => '2026-11-03',
            '--user' => $user->id,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertEquals(1, NotificationDelivery::where('user_id', $user->id)->count());
    }

    public function test_personalized_insights_notification_is_suppressed_when_user_has_no_birth_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['language' => 'en']);

        UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'fcm_user_no_profile',
            'is_active' => true,
        ]);

        UserNotificationPreference::create([
            'user_id' => $user->id,
            'timezone' => 'Asia/Kolkata',
            'master_enabled' => true,
            'panchang_enabled' => false,
            'horoscope_enabled' => false,
            'personalized_enabled' => true, // Enabled, but user has NO profile!
            'preferred_delivery_hour' => 7,
        ]);

        $mockFcm = Mockery::mock(FirebaseNotificationService::class);
        $this->app->instance(FirebaseNotificationService::class, $mockFcm);

        // sendToToken must NEVER be called
        $mockFcm->shouldNotReceive('sendToToken');

        $exitCode = Artisan::call('notifications:dispatch', [
            '--date' => '2026-11-03',
            '--user' => $user->id,
        ]);

        $this->assertEquals(0, $exitCode);

        // No delivery was sent
        $this->assertEquals(0, NotificationDelivery::where('user_id', $user->id)->count());
    }

    public function test_legacy_broadcast_routes_are_protected_against_unauthenticated_requests(): void
    {
        $response = $this->postJson('/api/en/horoscope-notification', [
            'title' => 'Spam Title',
            'description' => 'Spam Body',
        ]);

        // Must be rejected with 401 Unauthorized
        $response->assertUnauthorized();
    }
}
