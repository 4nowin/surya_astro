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

        if ($jajman->user_id !== null) {
            $hasConsent = \App\Models\CustomerAstrologerConsent::where('user_id', $jajman->user_id)
                ->where('admin_id', $admin->id)
                ->where('status', \App\Models\CustomerAstrologerConsent::STATUS_GRANTED)
                ->exists();

            if (!$hasConsent) {
                return response()->json([
                    'success' => false,
                    'status' => 'consent_required',
                    'message' => 'Client has not granted personal data sharing consent for astrological chart calculation.',
                    'data' => null,
                ], 403);
            }
        }

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

        if ($jajman->user_id !== null) {
            $hasConsent = \App\Models\CustomerAstrologerConsent::where('user_id', $jajman->user_id)
                ->where('admin_id', $admin->id)
                ->where('status', \App\Models\CustomerAstrologerConsent::STATUS_GRANTED)
                ->exists();

            if (!$hasConsent) {
                return response()->json([
                    'success' => false,
                    'status' => 'consent_required',
                    'message' => 'Client has not granted personal data sharing consent for astrological chart calculation.',
                    'data' => null,
                ], 403);
            }
        }

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
     * Resolve Kundli response: checks cache, or queues targeted background CLI worker.
     * Web runtime (LiteSpeed / Apache / FPM) NEVER attempts direct FFI calculation.
     */
    protected function resolveKundliResponse(Request $request, array $payload, bool $forceRefresh = false): JsonResponse
    {
        $normalized = KundliCalculationHandler::normalizePayload($payload);
        $canonicalKey = $this->kundliHandler->computeCanonicalKey($normalized);

        // 1. Check cache (unless force-refresh requested)
        if (!$forceRefresh) {
            $cached = $this->kundliHandler->getCachedResult($canonicalKey, $normalized);
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

        // 2. Queue calculation job and launch targeted CLI worker (where FFI is isolated)
        try {
            $lookup = $this->jobManager->findOrCreateJob(
                type: KundliCalculationHandler::TYPE,
                payload: $normalized,
                userId: null,
                forceRefresh: $forceRefresh
            );

            if ($lookup['ready'] && !empty($lookup['result'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kundli retrieved successfully.',
                    'data' => $lookup['result'],
                ], 200, [
                    'X-Astrology-Cached' => 'HIT',
                    'X-Astrology-Cache-Key' => "astrology:kundli:{$canonicalKey}",
                ]);
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

            // Launch targeted CLI worker process specifically for this job
            $launchResult = $this->workerLauncher->launchWorkerForJob($job);
            if (!empty($launchResult['error'])) {
                Log::warning("Background calculation worker launch notice for job [{$job->job_id}]: {$launchResult['error']}", [
                    'reason' => $launchResult['reason'],
                ]);
            }

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
                    'poll_interval_ms' => 1500,
                    'worker_launched' => $launchResult['launched'],
                ],
            ], 202, [
                'X-Astrology-Status' => $job->status,
                'X-Astrology-Job-Id' => $job->job_id,
                'Location' => $statusUrl,
                'Retry-After' => '1',
            ]);
        } catch (Throwable $e) {
            Log::error("Kundli calculation dispatch failed: {$e->getMessage()}", [
                'payload' => $normalized,
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
     * D-9 Navamsha chart, and Saturn transits via the calculation handler.
     */
    public function buildCompleteKundli(array $payload): array
    {
        return $this->kundliHandler->calculate($payload);
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
        return KundliCalculationHandler::buildNavamshaHouses($d9AscSignId, $d9Planets);
    }

    /**
     * GET /api/astrologer/astrology/panchang/daily
     * Authoritative daily Panchang for date, lat, lon, and timezone.
     */
    public function dailyPanchang(Request $request): JsonResponse
    {
        $tReqStart = microtime(true);
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'lat' => 'nullable|numeric|between:-90,90',
            'lon' => 'nullable|numeric|between:-180,180',
            'tz' => 'nullable|string|max:100',
            'elevation' => 'nullable|numeric|between:-500,9000',
        ]);

        $payload = DailyPanchangCalculationHandler::normalizePayload($validated);
        $canonicalKey = $this->panchangHandler->computeCanonicalKey($payload);

        // 1. Fast indexed cache lookup: check fast cache & persistent MySQL table directly
        $tLookup0 = microtime(true);
        $cached = $this->panchangHandler->getCachedResult($canonicalKey, $payload);
        $lookupMs = round((microtime(true) - $tLookup0) * 1000, 2);

        if ($cached !== null) {
            $totalMs = round((microtime(true) - $tReqStart) * 1000, 2);
            return response()->json([
                'success' => true,
                'message' => 'Daily Panchang retrieved successfully.',
                'data' => $cached,
            ], 200, [
                'X-Panchang-Cached' => 'HIT',
                'X-Panchang-Cache-Key' => "panchang:daily:{$canonicalKey}",
                'X-Panchang-Lookup-Ms' => (string) $lookupMs,
                'X-Panchang-Total-Ms' => (string) $totalMs,
            ]);
        }

        // 2. Queue fallback (Standard Web runtime: Hostinger LiteSpeed / FPM / Apache)
        try {
            $lookup = $this->jobManager->findOrCreateJob(
                type: DailyPanchangCalculationHandler::TYPE,
                payload: $payload,
                userId: null
            );

            if ($lookup['ready'] && !empty($lookup['result'])) {
                $totalMs = round((microtime(true) - $tReqStart) * 1000, 2);
                return response()->json([
                    'success' => true,
                    'message' => 'Daily Panchang retrieved successfully.',
                    'data' => $lookup['result'],
                ], 200, [
                    'X-Panchang-Cached' => 'HIT',
                    'X-Panchang-Cache-Key' => "panchang:daily:{$canonicalKey}",
                    'X-Panchang-Total-Ms' => (string) $totalMs,
                ]);
            }

            /** @var AstrologyCalculationJob $job */
            $job = $lookup['job'];

            $tLaunch0 = microtime(true);
            $launchResult = $this->workerLauncher->launchWorkerForJob($job);
            $launchMs = round((microtime(true) - $tLaunch0) * 1000, 2);

            $statusUrl = url("/api/v1/calculations/{$job->job_id}");
            $totalMs = round((microtime(true) - $tReqStart) * 1000, 2);

            return response()->json([
                'success' => true,
                'status' => $job->status,
                'message' => 'Panchang calculation in progress.',
                'data' => [
                    'job_id' => $job->job_id,
                    'calculation_type' => $job->calculation_type,
                    'status' => $job->status,
                    'status_url' => $statusUrl,
                    'poll_interval_ms' => 1500,
                    'worker_launched' => $launchResult['launched'],
                ],
            ], 202, [
                'X-Panchang-Cached' => 'MISS',
                'X-Panchang-Status' => $job->status,
                'X-Panchang-Job-Id' => $job->job_id,
                'X-Panchang-Total-Ms' => (string) $totalMs,
                'Location' => $statusUrl,
                'Retry-After' => '1',
            ]);
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
