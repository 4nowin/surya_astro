<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAstrologyProfileRequest;
use App\Http\Requests\UpdateAstrologyProfileRequest;
use App\Models\UserAstrologyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AstrologyProfileController extends Controller
{
    /**
     * Display a listing of astrology profiles for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $profiles = $request->user()
            ->astrologyProfiles()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Astrology profiles retrieved successfully',
            'data'    => $profiles,
        ]);
    }

    /**
     * Store a newly created astrology profile for the authenticated user.
     */
    public function store(StoreAstrologyProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $profile = DB::transaction(function () use ($user, $validated) {
            $isFirstProfile = !$user->astrologyProfiles()->exists();
            $shouldBeDefault = $isFirstProfile || (!empty($validated['is_default']) && (bool) $validated['is_default']);

            if ($shouldBeDefault) {
                $user->astrologyProfiles()->update(['is_default' => false]);
                $validated['is_default'] = true;
            } else {
                $validated['is_default'] = false;
            }

            if (empty($validated['name'])) {
                $validated['name'] = $user->name ?: 'Self';
            }

            if (empty($validated['relationship'])) {
                $validated['relationship'] = 'self';
            }

            return $user->astrologyProfiles()->create($validated);
        });

        return response()->json([
            'success' => true,
            'message' => 'Astrology profile created successfully',
            'data'    => $profile->fresh(),
        ], 201);
    }

    /**
     * Display the specified astrology profile.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $profile = $request->user()->astrologyProfiles()->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Astrology profile retrieved successfully',
            'data'    => $profile,
        ]);
    }

    /**
     * Update the specified astrology profile.
     */
    public function update(UpdateAstrologyProfileRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $profile = $user->astrologyProfiles()->findOrFail($id);
        $validated = $request->validated();

        $profile = DB::transaction(function () use ($user, $profile, $validated) {
            if (isset($validated['is_default']) && (bool) $validated['is_default']) {
                $user->astrologyProfiles()->where('id', '!=', $profile->id)->update(['is_default' => false]);
            }

            $profile->update($validated);
            return $profile;
        });

        return response()->json([
            'success' => true,
            'message' => 'Astrology profile updated successfully',
            'data'    => $profile->fresh(),
        ]);
    }

    /**
     * Remove the specified astrology profile from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $profile = $user->astrologyProfiles()->findOrFail($id);

        DB::transaction(function () use ($user, $profile) {
            $wasDefault = (bool) $profile->is_default;
            $profile->delete();

            if ($wasDefault) {
                $nextDefault = $user->astrologyProfiles()->first();
                if ($nextDefault) {
                    $nextDefault->update(['is_default' => true]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Astrology profile deleted successfully',
        ]);
    }
}
