<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AstrologyCalculationJob;
use App\Models\Jajman;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\DailyPanchangCalculationHandler;
use App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler;
use App\Services\Astrology\DailyPanchangService;
use App\Services\Astrology\KundliService;
use App\Services\Astrology\Stability\AstrologyIntervalStabilityService;
use App\Services\Astrology\Transits\SaturnTransitService;
use App\Services\Astrology\Yogas\ManglikDoshaService;
use App\Services\Astrology\Yogas\YogaDoshaOrchestrator;
use App\Services\Geo\GeocodingServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AstrologyApiController extends Controller
{
    public function __construct(
        protected GeocodingServiceInterface $geocodingService,
        protected CalculationJobManager $jobManager,
        protected CalculationWorkerLauncher $workerLauncher,
        protected KundliCalculationHandler $kundliHandler,
        protected DailyPanchangCalculationHandler $panchangHandler
    ) {}

    /**
     * Resolve Kundli service lazily to prevent eager FFI initialization in web runtime.
     */
    protected function getKundliService(): KundliService
    {
        return app(KundliService::class);
    }

    /**
     * Resolve Daily Panchang service lazily to prevent eager FFI initialization in web runtime.
     */
    protected function getDailyPanchangService(): DailyPanchangService
    {
        return app(DailyPanchangService::class);
    }

    /**
     * Resolve Manglik service lazily to prevent eager FFI initialization in web runtime.
     */
    protected function getManglikService(): ManglikDoshaService
    {
        return app(ManglikDoshaService::class);
    }

    /**
     * Resolve Yoga orchestrator lazily to prevent eager FFI initialization in web runtime.
     */
    protected function getYogaOrchestrator(): YogaDoshaOrchestrator
    {
        return app(YogaDoshaOrchestrator::class);
    }

    /**
     * Resolve Stability service lazily to prevent eager FFI initialization in web runtime.
     */
    protected function getStabilityService(): AstrologyIntervalStabilityService
    {
        return app(AstrologyIntervalStabilityService::class);
    }

    /**
     * Resolve Saturn Transit service lazily to prevent eager FFI initialization in web runtime.
     */
    protected function getSaturnTransitService(): SaturnTransitService
    {
        return app(SaturnTransitService::class);
    }

    /**
     * Authorize that the authenticated professional has ownership of the Jajman.
     */
    protected function authorizeJajman(Request $request, int|string $jajmanId): Jajman
    {
        $admin = $request->user();

        $jajman = Jajman::whereHas('professionals', function ($q) use ($admin) {
            $q->where('admin_id', $admin->id);
        })->findOrFail($jajmanId);

        return $jajman;
    }

    /**
     * Check whether direct FFI execution is viable in the current PHP environment.
     * Web SAPIs (fpm-fcgi, cgi-fcgi, apache2handler, etc.) must NEVER execute direct FFI.
     */
    protected function canExecuteDirectFfi(): bool
    {
        if (PHP_SAPI !== 'cli') {
            return false;
        }

        if (!extension_loaded('ffi')) {
            return false;
        }

        $rawFfiEnable = strtolower(trim((string) ini_get('ffi.enable')));
        return $rawFfiEnable === '1' || $rawFfiEnable === 'true';
    }

    /**
     * GET /api/astrologer/jajmans/{id}/kundli
     * Retrieve or generate complete Kundli for a Jajman's primary birth details.
     */
    public function jajmanKundli(Request $request, int|string $id): JsonResponse
    {
        $admin = $request->user();
        $jajman = $this->authorizeJajman($request, $id);

        $birthDetail = $jajman->primaryBirthDetail
            ?? $jajman->birthDetails()->latest()->first();

        if (!$birthDetail) {
            return response()->json([
                'success' => false,
                'status' => 'no_birth_details',
                'message' => 'No birth details recorded for this Jajman. Please record birth date and location first.',
                'data' => null,
            ], 404);
        }

        $dob = $birthDetail->date_of_birth instanceof \DateTimeInterface
            ? $birthDetail->date_of_birth->format('Y-m-d')
            : (string) $birthDetail->date_of_birth;

        $payload = [
            'jajman_id' => $jajman->id,
            'birth_detail_id' => $birthDetail->id,
            'user_id' => $admin->id,
            'name' => $jajman->name,
            'date_of_birth' => $dob,
            'birth_time' => $birthDetail->birth_time,
            'birth_time_precision' => $birthDetail->time_unknown ? 'unknown' : 'verified',
            'latitude' => (float) ($birthDetail->latitude ?? 28.6139),
            'longitude' => (float) ($birthDetail->longitude ?? 77.2090),
            'timezone' => $birthDetail->timezone ?: 'Asia/Kolkata',
            'birth_city' => $birthDetail->birth_place ?: 'Unknown',
        ];

        return $this->resolveKundliResponse($request, $payload);
    }

    /**
     * POST /api/astrologer/jajmans/{id}/kundli/calculate
     * Force recalculation of Kundli for a Jajman (e.g. after birth details are updated).
     */
    public function recalculateJajmanKundli(Request $request, int|string $id): JsonResponse
    {
        $admin = $request->user();
        $jajman = $this->authorizeJajman($request, $id);

        $birthDetail = $jajman->primaryBirthDetail
            ?? $jajman->birthDetails()->latest()->first();

        if (!$birthDetail) {
            return response()->json([
                'success' => false,
                'status' => 'no_birth_details',
                'message' => 'No birth details recorded for this Jajman. Please record birth date and location first.',
                'data' => null,
            ], 404);
        }

        $dob = $birthDetail->date_of_birth instanceof \DateTimeInterface
            ? $birthDetail->date_of_birth->format('Y-m-d')
            : (string) $birthDetail->date_of_birth;

        $payload = [
            'jajman_id' => $jajman->id,
            'birth_detail_id' => $birthDetail->id,
            'user_id' => $admin->id,
            'name' => $jajman->name,
            'date_of_birth' => $dob,
            'birth_time' => $birthDetail->birth_time,
            'birth_time_precision' => $birthDetail->time_unknown ? 'unknown' : 'verified',
            'latitude' => (float) ($birthDetail->latitude ?? 28.6139),
            'longitude' => (float) ($birthDetail->longitude ?? 77.2090),
            'timezone' => $birthDetail->timezone ?: 'Asia/Kolkata',
            'birth_city' => $birthDetail->birth_place ?: 'Unknown',
        ];

        return $this->resolveKundliResponse($request, $payload, forceRefresh: true);
    }

    /**
     * POST /api/astrologer/astrology/kundli/calculate
     * On-the-fly ephemeral Kundli calculation for arbitrary birth parameters.
     */
    public function calculateKundli(Request $request): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'date_of_birth' => 'required|date_format:Y-m-d',
            'birth_time' => 'nullable|string',
            'birth_time_precision' => 'nullable|string|in:verified,approximate,unknown',
            'approximate_time_window' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'timezone' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:191',
            'birth_city' => 'nullable|string|max:191',
        ]);

        $payload = [
            'user_id' => $admin->id,
            'name' => $validated['name'] ?? 'Native',
            'date_of_birth' => $validated['date_of_birth'],
            'birth_time' => $validated['birth_time'] ?? null,
            'birth_time_precision' => $validated['birth_time_precision'] ?? (!empty($validated['birth_time']) ? 'verified' : 'unknown'),
            'approximate_time_window' => $validated['approximate_time_window'] ?? null,
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
            'timezone' => $validated['timezone'] ?? 'Asia/Kolkata',
            'birth_city' => $validated['birth_city'] ?? 'Unknown',
        ];

        $forceRefresh = (bool) $request->boolean('force_refresh', false);

        return $this->resolveKundliResponse($request, $payload, forceRefresh: $forceRefresh);
    }

    /**
     * Resolve Kundli response: checks cache, executes direct FFI if permitted,
     * or queues for background worker if Hostinger web PHP restricts FFI.
     */
    protected function resolveKundliResponse(Request $request, array $payload, bool $forceRefresh = false): JsonResponse
    {
        $canonicalKey = $this->kundliHandler->computeCanonicalKey($payload);

        // 1. Check cache (unless force-refresh requested)
        if (!$forceRefresh) {
            $cached = $this->kundliHandler->getCachedResult($canonicalKey, $payload);
            if ($cached !== null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kundli retrieved successfully.',
                    'data' => $cached,
                ], 200, [
                    'X-Astrology-Cached' => 'HIT',
                    'X-Astrology-Cache-Key' => "astrology:kundli:{$canonicalKey}",
                ]);
            }
        }

        // 2. Direct calculation if FFI is available in current PHP execution context
        if ($this->canExecuteDirectFfi()) {
            try {
                $completeKundli = $this->buildCompleteKundli($payload);
                $this->kundliHandler->storeResult($canonicalKey, $completeKundli, $payload);

                return response()->json([
                    'success' => true,
                    'message' => 'Kundli calculated successfully.',
                    'data' => $completeKundli,
                ], 200, [
                    'X-Astrology-Cached' => 'MISS',
                    'X-Astrology-Cache-Key' => "astrology:kundli:{$canonicalKey}",
                ]);
            } catch (Throwable $e) {
                Log::warning("Direct Kundli calculation failed or threw FFI notice: {$e->getMessage()}. Falling back to background worker queue.");
            }
        }

        // 3. Robust Background Queue fallback (Hostinger Web FPM/Apache compatibility)
        try {
            $userId = $request->user()?->id;
            $lookup = $this->jobManager->findOrCreateJob(
                type: KundliCalculationHandler::TYPE,
                payload: $payload,
                userId: $userId
            );

            if ($lookup['ready'] && !empty($lookup['result'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kundli retrieved successfully.',
                    'data' => $lookup['result'],
                ], 200);
            }

            /** @var AstrologyCalculationJob $job */
            $job = $lookup['job'];

            if ($job->isFailed() && !$job->canRetry()) {
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Kundli calculation failed: ' . ($job->error_message ?? 'Calculation error'),
                    'data' => [
                        'job_id' => $job->job_id,
                        'status' => 'failed',
                    ],
                ], 500);
            }

            // Launch detached CLI worker process where FFI is fully enabled
            $launchResult = $this->workerLauncher->launchWorkerIfNeeded();
            $statusUrl = url("/api/v1/calculations/{$job->job_id}");

            return response()->json([
                'success' => true,
                'status' => $job->status,
                'message' => 'Kundli calculation in progress. Poll status URL for result.',
                'data' => [
                    'job_id' => $job->job_id,
                    'calculation_type' => $job->calculation_type,
                    'status' => $job->status,
                    'status_url' => $statusUrl,
                    'poll_interval_ms' => 2000,
                    'worker_launched' => $launchResult['launched'],
                ],
            ], 202, [
                'X-Astrology-Status' => $job->status,
                'X-Astrology-Job-Id' => $job->job_id,
                'Location' => $statusUrl,
                'Retry-After' => '2',
            ]);
        } catch (Throwable $e) {
            Log::error("Kundli calculation dispatch failed: {$e->getMessage()}", [
                'payload' => $payload,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to dispatch Kundli calculation. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compute full comprehensive Kundli bundling Core, Manglik Dosha, Yogas,
     * D-9 Navamsha chart, and Saturn transits.
     */
    public function buildCompleteKundli(array $payload): array
    {
        $birthDate = (string) $payload['date_of_birth'];
        $birthTime = $payload['birth_time'] ?? null;
        $precision = (string) ($payload['birth_time_precision'] ?? 'verified');
        $approximateWindow = $payload['approximate_time_window'] ?? null;
        $latitude = (float) $payload['latitude'];
        $longitude = (float) $payload['longitude'];
        $timezone = (string) ($payload['timezone'] ?? 'Asia/Kolkata');
        $name = (string) ($payload['name'] ?? 'Native');
        $birthCity = (string) ($payload['birth_city'] ?? 'Unknown');

        // 1. Core Kundli
        $kundli = $this->getKundliService()->computeKundli(
            birthDate: $birthDate,
            birthTime: $birthTime,
            precision: $precision,
            approximateWindow: $approximateWindow,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone,
            profileMetadata: [
                'name' => $name,
                'birth_city' => $birthCity,
            ]
        );

        // 2. Manglik Dosha
        $manglik = $this->getManglikService()->evaluate(
            planets: $kundli['planets'],
            lagnaRashiId: $kundli['ascendant']['id'] ?? $kundli['ascendant']
        );

        // 3. Classical Yogas & Aspects
        $yogas = $this->getYogaOrchestrator()->evaluate(
            planets: $kundli['planets'],
            ascendantData: $kundli['ascendant'],
            stabilityData: ['is_lagna_invariant' => true],
            userAgeYears: 30
        );

        // 4. D-9 Navamsha Stability & Houses
        $stability = $this->getStabilityService()->analyze(
            birthDate: $birthDate,
            birthTime: $birthTime,
            precision: $precision,
            approximateWindow: $approximateWindow,
            latitude: $latitude,
            longitude: $longitude,
            timezone: $timezone
        );

        $d9Asc = $stability['d9_ascendant'] ?? null;
        $d9Planets = $stability['planetary_navamshas'] ?? [];
        $d9Houses = null;
        if ($d9Asc && !empty($d9Asc['id'])) {
            $d9Houses = $this->buildNavamshaHouses((int) $d9Asc['id'], $d9Planets);
        }

        // 5. Saturn Transit Status
        $moonSignId = $kundli['planets']['moon']['rashi']['id'] ?? 1;
        $saturnTransit = $this->getSaturnTransitService()->evaluateCurrentPhaseAt($moonSignId, now($timezone)->toDateString());

        // Attach enriched astrology modules
        $kundli['manglik_dosha'] = $manglik;
        $kundli['yogas'] = $yogas['yogas'] ?? [];
        $kundli['aspects'] = $yogas['aspects'] ?? [];
        $kundli['navamsha'] = [
            'status' => $d9Asc['status'] ?? 'available',
            'ascendant' => $d9Asc,
            'planets' => $d9Planets,
            'houses' => $d9Houses,
        ];
        $kundli['saturn_transit'] = $saturnTransit;

        return $kundli;
    }

    /**
     * Map D-9 Navamsha planets into equal whole-sign houses for North Indian chart rendering.
     *
     * @param int $d9AscSignId 1-based zodiac sign ID (1=Aries..12=Pisces)
     * @param array<string, array<string, mixed>> $d9Planets
     * @return array<int, array<string, mixed>> Keyed 1..12
     */
    protected function buildNavamshaHouses(int $d9AscSignId, array $d9Planets): array
    {
        $zodiacSigns = [
            1 => ['name' => 'Aries', 'vedic_name' => 'Mesha', 'lord' => 'Mars'],
            2 => ['name' => 'Taurus', 'vedic_name' => 'Vrishabha', 'lord' => 'Venus'],
            3 => ['name' => 'Gemini', 'vedic_name' => 'Mithuna', 'lord' => 'Mercury'],
            4 => ['name' => 'Cancer', 'vedic_name' => 'Karka', 'lord' => 'Moon'],
            5 => ['name' => 'Leo', 'vedic_name' => 'Simha', 'lord' => 'Sun'],
            6 => ['name' => 'Virgo', 'vedic_name' => 'Kanya', 'lord' => 'Mercury'],
            7 => ['name' => 'Libra', 'vedic_name' => 'Tula', 'lord' => 'Venus'],
            8 => ['name' => 'Scorpio', 'vedic_name' => 'Vrischika', 'lord' => 'Mars'],
            9 => ['name' => 'Sagittarius', 'vedic_name' => 'Dhanu', 'lord' => 'Jupiter'],
            10 => ['name' => 'Capricorn', 'vedic_name' => 'Makara', 'lord' => 'Saturn'],
            11 => ['name' => 'Aquarius', 'vedic_name' => 'Kumbha', 'lord' => 'Saturn'],
            12 => ['name' => 'Pisces', 'vedic_name' => 'Meena', 'lord' => 'Jupiter'],
        ];

        // Initialize 12 houses
        $houses = [];
        for ($h = 1; $h <= 12; $h++) {
            $signId = (($d9AscSignId - 1 + ($h - 1)) % 12) + 1;
            $signMeta = $zodiacSigns[$signId];
            $houses[$h] = [
                'house_number' => $h,
                'sign_id' => $signId,
                'rashi' => [
                    'id' => $signId,
                    'name' => $signMeta['name'],
                    'vedic_name' => $signMeta['vedic_name'],
                    'lord' => $signMeta['lord'],
                ],
                'planets' => [],
            ];
        }

        // Distribute planets into houses
        foreach ($d9Planets as $key => $p) {
            $pSignId = (int) ($p['d9_navamsha']['id'] ?? ($p['invariant_d9_sign_id'] ?? 1));
            $houseNum = (($pSignId - $d9AscSignId + 12) % 12) + 1;

            if (isset($houses[$houseNum])) {
                $houses[$houseNum]['planets'][] = [
                    'key' => $key,
                    'name' => $p['name'] ?? ucfirst($key),
                    'name_hi' => $p['name_hi'] ?? '',
                    'sign_id' => $pSignId,
                    'is_vargottama' => !empty($p['is_vargottama']),
                    'is_retrograde' => false,
                    'is_combust' => false,
                ];
            }
        }

        return $houses;
    }

    /**
     * GET /api/astrologer/astrology/panchang/daily
     * Authoritative daily Panchang for date, lat, lon, and timezone.
     */
    public function dailyPanchang(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'lat' => 'nullable|numeric|between:-90,90',
            'lon' => 'nullable|numeric|between:-180,180',
            'tz' => 'nullable|string|max:100',
        ]);

        $date = $validated['date'] ?? now('Asia/Kolkata')->toDateString();
        $lat = isset($validated['lat']) ? (float) $validated['lat'] : 28.6139; // New Delhi
        $lon = isset($validated['lon']) ? (float) $validated['lon'] : 77.2090;
        $tz = $validated['tz'] ?? 'Asia/Kolkata';

        $payload = [
            'date' => $date,
            'lat' => $lat,
            'lon' => $lon,
            'tz' => $tz,
            'elevation' => 0.0,
        ];

        $canonicalKey = $this->panchangHandler->computeCanonicalKey($payload);

        // 1. Check cache
        $cached = $this->panchangHandler->getCachedResult($canonicalKey, $payload);
        if ($cached !== null) {
            return response()->json([
                'success' => true,
                'message' => 'Daily Panchang retrieved successfully.',
                'data' => $cached,
            ], 200, [
                'X-Panchang-Cached' => 'HIT',
                'X-Panchang-Cache-Key' => "panchang:daily:{$canonicalKey}",
            ]);
        }

        // 2. Direct calculation if FFI is available
        if ($this->canExecuteDirectFfi()) {
            try {
                $result = $this->getDailyPanchangService()->getDailyPanchang($date, $lat, $lon, $tz);
                $this->panchangHandler->storeResult($canonicalKey, $result, $payload);

                return response()->json([
                    'success' => true,
                    'message' => 'Daily Panchang calculated successfully.',
                    'data' => $result,
                ], 200, [
                    'X-Panchang-Cached' => 'MISS',
                    'X-Panchang-Cache-Key' => "panchang:daily:{$canonicalKey}",
                ]);
            } catch (Throwable $e) {
                Log::warning("Direct Panchang calculation failed: {$e->getMessage()}. Falling back to background worker.");
            }
        }

        // 3. Queue fallback
        try {
            $lookup = $this->jobManager->findOrCreateJob(
                type: DailyPanchangCalculationHandler::TYPE,
                payload: $payload,
                userId: null
            );

            if ($lookup['ready'] && !empty($lookup['result'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Daily Panchang retrieved successfully.',
                    'data' => $lookup['result'],
                ], 200);
            }

            /** @var AstrologyCalculationJob $job */
            $job = $lookup['job'];
            $launchResult = $this->workerLauncher->launchWorkerIfNeeded();
            $statusUrl = url("/api/v1/calculations/{$job->job_id}");

            return response()->json([
                'success' => true,
                'status' => $job->status,
                'message' => 'Panchang calculation in progress.',
                'data' => [
                    'job_id' => $job->job_id,
                    'calculation_type' => $job->calculation_type,
                    'status' => $job->status,
                    'status_url' => $statusUrl,
                    'poll_interval_ms' => 2000,
                    'worker_launched' => $launchResult['launched'],
                ],
            ], 202);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to dispatch Panchang calculation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/astrologer/astrology/cities
     * Search verified cities with coordinates and timezone for birth details and Panchang.
     */
    public function cities(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $limit = min((int) $request->query('limit', 20), 50);

        $results = $this->geocodingService->searchCities($query, $limit > 0 ? $limit : 20);

        return response()->json([
            'success' => true,
            'message' => 'Cities retrieved successfully.',
            'data' => $results,
        ]);
    }
}
