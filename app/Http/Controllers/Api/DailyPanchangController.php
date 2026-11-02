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
        protected CalculationWorkerLauncher $workerLauncher
    ) {}

    /**
     * Compute or retrieve the authoritative daily Panchang for a location and date.
     *
     * GET /api/v1/panchang/daily?date=YYYY-MM-DD&lat={latitude}&lon={longitude}&tz={timezone}
     */
    public function daily(DailyPanchangRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $payload = [
            'date' => (string) $validated['date'],
            'lat' => (float) $validated['lat'],
            'lon' => (float) $validated['lon'],
            'tz' => (string) $validated['tz'],
            'elevation' => isset($validated['elevation']) ? (float) $validated['elevation'] : 0.0,
        ];

        try {
            $lookup = $this->jobManager->findOrCreateJob(
                type: DailyPanchangCalculationHandler::TYPE,
                payload: $payload,
                userId: null // Panchang is public data
            );

            // Case A: Result is already available in cache
            if ($lookup['ready'] && !empty($lookup['result'])) {
                $data = $lookup['result'];
                return response()->json([
                    'success' => true,
                    'message' => 'Daily Panchang retrieved successfully',
                    'data' => $data,
                ], 200, [
                    'X-Panchang-Cached' => 'HIT',
                    'X-Panchang-Cache-Key' => "panchang:daily:{$lookup['canonical_key']}",
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

            // Case B & C: Job is pending or processing. Trigger background worker launch if not already running.
            $launchResult = $this->workerLauncher->launchWorkerIfNeeded();
            if (!empty($launchResult['error'])) {
                Log::warning("Background calculation worker launch notice for job [{$job->job_id}]: {$launchResult['error']}", [
                    'reason' => $launchResult['reason'],
                ]);
            }

            $statusUrl = url("/api/v1/calculations/{$job->job_id}");

            return response()->json([
                'success' => true,
                'status' => $job->status,
                'message' => 'Panchang calculation in progress. Poll status URL for result.',
                'data' => [
                    'job_id' => $job->job_id,
                    'calculation_type' => $job->calculation_type,
                    'status' => $job->status,
                    'status_url' => $statusUrl,
                    'poll_interval_ms' => 2000,
                    'worker_launched' => $launchResult['launched'],
                ],
            ], 202, [
                'X-Panchang-Status' => $job->status,
                'X-Panchang-Job-Id' => $job->job_id,
                'Location' => $statusUrl,
                'Retry-After' => '2',
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
