<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyPanchangRequest;
use App\Models\AstrologyCalculationJob;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use App\Services\Astrology\Calculation\Handlers\DailyPanchangCalculationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DailyPanchangController extends Controller
{
    public function __construct(
        protected CalculationJobManager $jobManager,
        protected CalculationWorkerLauncher $workerLauncher,
        protected DailyPanchangCalculationHandler $panchangHandler
    ) {}

    /**
     * Compute or retrieve the authoritative daily Panchang for a location and date.
     *
     * GET /api/v1/panchang/daily?date=YYYY-MM-DD&lat={latitude}&lon={longitude}&tz={timezone}
     */
    public function daily(DailyPanchangRequest $request): JsonResponse
    {
        $tReqStart = microtime(true);
        $validated = $request->validated();
        $payload = DailyPanchangCalculationHandler::normalizePayload($validated);

        try {
            $canonicalKey = $this->panchangHandler->computeCanonicalKey($payload);

            // Fast indexed cache lookup: check fast cache & persistent MySQL table directly
            $tLookup0 = microtime(true);
            $cached = $this->panchangHandler->getCachedResult($canonicalKey, $payload);
            $lookupMs = round((microtime(true) - $tLookup0) * 1000, 2);

            if ($cached !== null) {
                $totalMs = round((microtime(true) - $tReqStart) * 1000, 2);
                return response()->json([
                    'success' => true,
                    'message' => 'Daily Panchang retrieved successfully',
                    'data' => $cached,
                ], 200, [
                    'X-Panchang-Cached' => 'HIT',
                    'X-Panchang-Cache-Key' => "panchang:daily:{$canonicalKey}",
                    'X-Panchang-Lookup-Ms' => (string) $lookupMs,
                    'X-Panchang-Total-Ms' => (string) $totalMs,
                ]);
            }

            // Cache miss: find or atomically create targeted calculation job
            $lookup = $this->jobManager->findOrCreateJob(
                type: DailyPanchangCalculationHandler::TYPE,
                payload: $payload,
                userId: null // Panchang is public data
            );

            // Result might have been completed concurrently during job lookup
            if ($lookup['ready'] && !empty($lookup['result'])) {
                $totalMs = round((microtime(true) - $tReqStart) * 1000, 2);
                return response()->json([
                    'success' => true,
                    'message' => 'Daily Panchang retrieved successfully',
                    'data' => $lookup['result'],
                ], 200, [
                    'X-Panchang-Cached' => 'HIT',
                    'X-Panchang-Cache-Key' => "panchang:daily:{$canonicalKey}",
                    'X-Panchang-Total-Ms' => (string) $totalMs,
                ]);
            }

            /** @var AstrologyCalculationJob $job */
            $job = $lookup['job'];

            // Case D: Job previously failed and exhausted retries
            if ($job->isFailed() && !$job->canRetry()) {
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Panchang calculation failed: ' . ($job->error_message ?? 'Calculation error'),
                    'data' => [
                        'job_id' => $job->job_id,
                        'status' => 'failed',
                        'attempts' => $job->attempts,
                    ],
                ], 500);
            }

            // Trigger targeted automatic short-lived CLI worker process for this calculation job
            $tLaunch0 = microtime(true);
            $launchResult = $this->workerLauncher->launchWorkerForJob($job);
            $launchMs = round((microtime(true) - $tLaunch0) * 1000, 2);

            if (!empty($launchResult['error'])) {
                Log::warning("Background calculation worker launch notice for job [{$job->job_id}]: {$launchResult['error']}", [
                    'reason' => $launchResult['reason'],
                ]);
            }

            $statusUrl = url("/api/v1/calculations/{$job->job_id}");
            $totalMs = round((microtime(true) - $tReqStart) * 1000, 2);

            return response()->json([
                'success' => true,
                'status' => $job->status,
                'message' => 'Panchang calculation in progress. Poll status URL for result.',
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
        } catch (\Throwable $e) {
            Log::error('DailyPanchang request handling failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Daily Panchang request failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
