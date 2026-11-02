<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyPanchangRequest;
use App\Services\Astrology\DailyPanchangService;
use Illuminate\Http\JsonResponse;

class DailyPanchangController extends Controller
{
    public function __construct(
        protected DailyPanchangService $panchangService
    ) {}

    /**
     * Compute or retrieve the authoritative daily Panchang for a location and date.
     *
     * GET /api/v1/panchang/daily?date=YYYY-MM-DD&lat={latitude}&lon={longitude}&tz={timezone}
     */
    public function daily(DailyPanchangRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data = $this->panchangService->getDailyPanchang(
            date: (string) $validated['date'],
            latitude: (float) $validated['lat'],
            longitude: (float) $validated['lon'],
            timezone: (string) $validated['tz'],
            elevation: isset($validated['elevation']) ? (float) $validated['elevation'] : 0.0
        );

        return response()->json([
            'success' => true,
            'message' => 'Daily Panchang retrieved successfully',
            'data' => $data,
        ], 200, [
            'X-Panchang-Cached' => $data['metadata']['cached'] ? 'HIT' : 'MISS',
            'X-Panchang-Cache-Key' => $data['metadata']['cache_key'] ?? '',
        ]);
    }
}
