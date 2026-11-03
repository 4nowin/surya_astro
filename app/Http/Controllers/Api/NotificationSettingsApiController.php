<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use App\Models\UserNotificationPreference;
use App\Models\UserAstrologyProfile;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationSettingsApiController extends Controller
{
    /**
     * Register or update a user device for push notifications.
     * POST /api/v1/notifications/devices/register
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string|max:500',
            'device_id' => 'nullable|string|max:100',
            'platform' => 'nullable|string|max:20',
            'device_model' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:64',
        ]);

        $user = $request->user();

        // 1. Update or create UserDevice record
        $device = UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'fcm_token' => $validated['fcm_token'],
            ],
            [
                'device_id' => $validated['device_id'] ?? null,
                'platform' => $validated['platform'] ?? 'android',
                'device_model' => $validated['device_model'] ?? null,
                'is_active' => true,
                'last_active_at' => now(),
            ]
        );

        // Also update legacy single column on users for fallback
        $user->fcm_token = $validated['fcm_token'];
        $user->save();

        // 2. Sync detected IANA timezone if valid
        if (!empty($validated['timezone'])) {
            $tz = $validated['timezone'];
            if (in_array($tz, DateTimeZone::listIdentifiers(), true)) {
                $prefs = UserNotificationPreference::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'timezone' => $tz,
                        'master_enabled' => true,
                        'panchang_enabled' => true,
                        'horoscope_enabled' => true,
                        'personalized_enabled' => true,
                        'reminders_enabled' => false,
                        'preferred_delivery_hour' => 7,
                    ]
                );
                if ($prefs->timezone !== $tz) {
                    $prefs->timezone = $tz;
                    $prefs->save();
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Device registered successfully',
            'data' => [
                'device_id' => $device->id,
                'is_active' => $device->is_active,
            ],
        ]);
    }

    /**
     * Deactivate a user device upon logout.
     * POST /api/v1/notifications/devices/remove
     */
    public function removeDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = $request->user();

        $device = UserDevice::where('user_id', $user->id)
            ->where('fcm_token', $validated['fcm_token'])
            ->first();

        if ($device) {
            $device->update(['is_active' => false]);
        }

        if ($user->fcm_token === $validated['fcm_token']) {
            $user->fcm_token = null;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Device deactivated successfully',
        ]);
    }

    /**
     * Retrieve the user's notification preferences and eligibility status.
     * GET /api/v1/notifications/preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var UserNotificationPreference $prefs */
        $prefs = UserNotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'timezone' => 'Asia/Kolkata',
                'master_enabled' => true,
                'panchang_enabled' => true,
                'horoscope_enabled' => true,
                'personalized_enabled' => true,
                'reminders_enabled' => false,
                'preferred_delivery_hour' => 7,
            ]
        );

        // Check M4B eligibility for personalized insights
        $eligibility = $this->evaluatePersonalizedInsightsEligibility($user);

        return response()->json([
            'success' => true,
            'data' => [
                'timezone' => $prefs->timezone,
                'master_enabled' => (bool) $prefs->master_enabled,
                'panchang_enabled' => (bool) $prefs->panchang_enabled,
                'horoscope_enabled' => (bool) $prefs->horoscope_enabled,
                'personalized_enabled' => (bool) $prefs->personalized_enabled,
                'reminders_enabled' => (bool) $prefs->reminders_enabled,
                'preferred_delivery_hour' => (int) $prefs->preferred_delivery_hour,
                'personalized_eligibility' => $eligibility,
            ],
        ]);
    }

    /**
     * Update user's notification preferences.
     * PUT /api/v1/notifications/preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timezone' => 'nullable|string|max:64',
            'master_enabled' => 'nullable|boolean',
            'panchang_enabled' => 'nullable|boolean',
            'horoscope_enabled' => 'nullable|boolean',
            'personalized_enabled' => 'nullable|boolean',
            'reminders_enabled' => 'nullable|boolean',
            'preferred_delivery_hour' => 'nullable|integer',
        ]);

        $user = $request->user();

        /** @var UserNotificationPreference $prefs */
        $prefs = UserNotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'timezone' => 'Asia/Kolkata',
                'master_enabled' => true,
                'panchang_enabled' => true,
                'horoscope_enabled' => true,
                'personalized_enabled' => true,
                'reminders_enabled' => false,
                'preferred_delivery_hour' => 7,
            ]
        );

        if (!empty($validated['timezone'])) {
            if (in_array($validated['timezone'], DateTimeZone::listIdentifiers(), true)) {
                $prefs->timezone = $validated['timezone'];
            }
        }

        if (array_key_exists('master_enabled', $validated)) {
            $prefs->master_enabled = (bool) $validated['master_enabled'];
        }
        if (array_key_exists('panchang_enabled', $validated)) {
            $prefs->panchang_enabled = (bool) $validated['panchang_enabled'];
        }
        if (array_key_exists('horoscope_enabled', $validated)) {
            $prefs->horoscope_enabled = (bool) $validated['horoscope_enabled'];
        }
        if (array_key_exists('personalized_enabled', $validated)) {
            $prefs->personalized_enabled = (bool) $validated['personalized_enabled'];
        }
        if (array_key_exists('reminders_enabled', $validated)) {
            $prefs->reminders_enabled = (bool) $validated['reminders_enabled'];
        }
        if (array_key_exists('preferred_delivery_hour', $validated)) {
            $prefs->preferred_delivery_hour = (int) $validated['preferred_delivery_hour'];
        }

        $prefs->save();

        $eligibility = $this->evaluatePersonalizedInsightsEligibility($user);

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated successfully',
            'data' => [
                'timezone' => $prefs->timezone,
                'master_enabled' => (bool) $prefs->master_enabled,
                'panchang_enabled' => (bool) $prefs->panchang_enabled,
                'horoscope_enabled' => (bool) $prefs->horoscope_enabled,
                'personalized_enabled' => (bool) $prefs->personalized_enabled,
                'reminders_enabled' => (bool) $prefs->reminders_enabled,
                'preferred_delivery_hour' => (int) $prefs->preferred_delivery_hour,
                'personalized_eligibility' => $eligibility,
            ],
        ]);
    }

    /**
     * Evaluate whether user has an eligible M4B profile for personalized insights.
     * Reuses M4B safeguards: supports verified, approximate and unknown birth profiles
     * as long as natal Moon sign is established or profile is valid.
     */
    protected function evaluatePersonalizedInsightsEligibility($user): array
    {
        /** @var UserAstrologyProfile|null $profile */
        $profile = $user->primaryAstrologyProfile ?? $user->astrologyProfiles()->first();

        if (!$profile) {
            return [
                'is_eligible' => false,
                'status' => 'no_profile',
                'reason' => 'Create a birth profile to receive personal transit insights.',
            ];
        }

        return [
            'is_eligible' => true,
            'profile_id' => $profile->id,
            'profile_name' => $profile->name,
            'birth_time_precision' => (string) $profile->birth_time_precision,
            'status' => 'eligible',
            'reason' => 'Personalized transit insights will be generated for ' . $profile->name . '.',
        ];
    }
}
