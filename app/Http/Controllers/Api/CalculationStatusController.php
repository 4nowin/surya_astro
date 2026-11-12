<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AstrologyCalculationJob;
use App\Services\Astrology\Calculation\CalculationHandlerRegistry;
use App\Services\Astrology\Calculation\CalculationJobManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalculationStatusController extends Controller
{
    public function __construct(
        protected CalculationJobManager $jobManager,
        protected CalculationHandlerRegistry $registry
    ) {}

    /**
     * Inspect the execution status of an astrology calculation job.
     *
     * GET /api/v1/calculations/{jobId}
     */
    public function show(Request $request, string $jobId): JsonResponse
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{10,64}$/', $jobId)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid calculation job identifier format.',
            ], 400);
        }

        $user = $request->user('sanctum') ?? $request->user();
        $userId = $user?->id;
        $job = $this->jobManager->getJobById($jobId, $userId);

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Calculation job not found.',
            ], 404);
        }

        $isReady = $job->isCompleted();
        $result = null;

        if ($isReady && $this->registry->has($job->calculation_type)) {
            $handler = $this->registry->get($job->calculation_type);
            $result = $handler->getCachedResult($job->canonical_key, $job->payload);

            // Fallback: check direct cache key recorded on the job record
            if ($result === null && !empty($job->result_cache_key)) {
                $cached = \Illuminate\Support\Facades\Cache::get($job->result_cache_key);
                if (is_array($cached)) {
                    $result = $cached;
                }
            }
        }

        // Return 200 for all valid job lookups so clients cleanly parse status
        // (Previously STATUS_FAILED returned HTTP 500, which crashed polling clients)
        $statusCode = 200;

        return response()->json([
            'success' => !$job->isFailed(),
            'message' => match ($job->status) {
                AstrologyCalculationJob::STATUS_COMPLETED => 'Calculation completed successfully.',
                AstrologyCalculationJob::STATUS_PROCESSING => 'Calculation is currently processing.',
                AstrologyCalculationJob::STATUS_PENDING => 'Calculation job is queued and pending execution.',
                AstrologyCalculationJob::STATUS_FAILED => 'Calculation failed: ' . ($job->error_message ?? 'Unknown error'),
                default => 'Unknown status',
            },
            'data' => [
                'job_id' => $job->job_id,
                'calculation_type' => $job->calculation_type,
                'status' => $job->status,
                'ready' => $isReady && $result !== null,
                'created_at' => $job->created_at?->toIso8601String(),
                'started_at' => $job->started_at?->toIso8601String(),
                'completed_at' => $job->completed_at?->toIso8601String(),
                'error_message' => $job->isFailed() ? $job->error_message : null,
                'result' => $result,
            ],
        ], $statusCode, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate, private',
        ]);
    }
}
