<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation;

use App\Models\AstrologyCalculationJob;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CalculationJobManager
{
    public function __construct(
        protected CalculationHandlerRegistry $registry
    ) {}

    /**
     * Find an existing calculation result, active job, or create a new job.
     *
     * @param string $type E.g. 'daily_panchang'
     * @param array<string, mixed> $payload
     * @param int|null $userId Optional owning user ID for personal calculations
     * @return array{ready: bool, result?: array, job?: AstrologyCalculationJob, canonical_key: string, is_new?: bool}
     */
    public function findOrCreateJob(string $type, array $payload, ?int $userId = null): array
    {
        $handler = $this->registry->get($type);
        $canonicalKey = $handler->computeCanonicalKey($payload);

        // 1. Fast check: is result already in durable cache?
        $cachedResult = $handler->getCachedResult($canonicalKey, $payload);
        if ($cachedResult !== null) {
            return [
                'ready' => true,
                'result' => $cachedResult,
                'canonical_key' => $canonicalKey,
            ];
        }

        // 2. Database transaction to inspect and atomically claim/create job
        return DB::transaction(function () use ($type, $canonicalKey, $payload, $userId, $handler) {
            // Find existing job for this canonical calculation
            $existingJob = AstrologyCalculationJob::where('calculation_type', $type)
                ->where('canonical_key', $canonicalKey)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existingJob) {
                // If previously completed, re-check cache or mark completed
                if ($existingJob->isCompleted()) {
                    $result = $handler->getCachedResult($canonicalKey, $payload);
                    if ($result !== null) {
                        return [
                            'ready' => true,
                            'result' => $result,
                            'job' => $existingJob,
                            'canonical_key' => $canonicalKey,
                        ];
                    }
                    // Cache expired; re-queue job
                    $existingJob->update([
                        'status' => AstrologyCalculationJob::STATUS_PENDING,
                        'attempts' => 0,
                        'started_at' => null,
                        'completed_at' => null,
                        'lease_expires_at' => null,
                    ]);

                    return [
                        'ready' => false,
                        'job' => $existingJob->fresh(),
                        'canonical_key' => $canonicalKey,
                        'is_new' => false,
                    ];
                }

                // If currently processing: check for stale lease (crashed worker recovery)
                if ($existingJob->isProcessing()) {
                    if ($existingJob->isStale()) {
                        // Worker died or timed out; re-queue if attempts remain
                        if ($existingJob->canRetry()) {
                            $existingJob->update([
                                'status' => AstrologyCalculationJob::STATUS_PENDING,
                                'worker_id' => null,
                                'lease_expires_at' => null,
                            ]);
                        }
                    }

                    return [
                        'ready' => false,
                        'job' => $existingJob->fresh(),
                        'canonical_key' => $canonicalKey,
                        'is_new' => false,
                    ];
                }

                // If pending, reuse it
                if ($existingJob->isPending()) {
                    return [
                        'ready' => false,
                        'job' => $existingJob,
                        'canonical_key' => $canonicalKey,
                        'is_new' => false,
                    ];
                }

                // If failed: check if retryable
                if ($existingJob->isFailed()) {
                    if ($existingJob->canRetry()) {
                        $existingJob->update([
                            'status' => AstrologyCalculationJob::STATUS_PENDING,
                            'lease_expires_at' => null,
                            'worker_id' => null,
                        ]);

                        return [
                            'ready' => false,
                            'job' => $existingJob->fresh(),
                            'canonical_key' => $canonicalKey,
                            'is_new' => false,
                        ];
                    }

                    // Max retries exceeded; return failed job
                    return [
                        'ready' => false,
                        'job' => $existingJob,
                        'canonical_key' => $canonicalKey,
                        'is_new' => false,
                    ];
                }
            }

            // Create new job
            $jobId = 'calc_' . bin2hex(random_bytes(16));
            $newJob = AstrologyCalculationJob::create([
                'job_id' => $jobId,
                'calculation_type' => $type,
                'canonical_key' => $canonicalKey,
                'payload' => $payload,
                'status' => AstrologyCalculationJob::STATUS_PENDING,
                'attempts' => 0,
                'max_attempts' => 3,
                'user_id' => $userId,
            ]);

            return [
                'ready' => false,
                'job' => $newJob,
                'canonical_key' => $canonicalKey,
                'is_new' => true,
            ];
        });
    }

    /**
     * Atomically claim the next eligible calculation job.
     */
    public function claimNextJob(string $workerId, int $leaseSeconds = 60): ?AstrologyCalculationJob
    {
        return DB::transaction(function () use ($workerId, $leaseSeconds) {
            // Find next claimable job
            $job = AstrologyCalculationJob::claimable()
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->first();

            if (!$job) {
                return null;
            }

            $job->update([
                'status' => AstrologyCalculationJob::STATUS_PROCESSING,
                'worker_id' => $workerId,
                'attempts' => $job->attempts + 1,
                'started_at' => now(),
                'lease_expires_at' => now()->addSeconds($leaseSeconds),
            ]);

            return $job->fresh();
        });
    }

    /**
     * Mark a job completed and record the result cache key.
     */
    public function markCompleted(AstrologyCalculationJob $job, string $resultCacheKey): void
    {
        $job->update([
            'status' => AstrologyCalculationJob::STATUS_COMPLETED,
            'completed_at' => now(),
            'result_cache_key' => $resultCacheKey,
            'lease_expires_at' => null,
            'error_message' => null,
            'error_context' => null,
        ]);
    }

    /**
     * Mark a job failed or return to pending if attempts remain.
     */
    public function markFailed(AstrologyCalculationJob $job, string $errorMessage, ?array $context = null): void
    {
        $hasRemainingAttempts = $job->attempts < $job->max_attempts;

        $job->update([
            'status' => $hasRemainingAttempts ? AstrologyCalculationJob::STATUS_PENDING : AstrologyCalculationJob::STATUS_FAILED,
            'error_message' => $errorMessage,
            'error_context' => $context,
            'lease_expires_at' => null,
            'worker_id' => null,
        ]);
    }

    /**
     * Retrieve job by ID with authorization check for personal jobs.
     */
    public function getJobById(string $jobId, ?int $requestingUserId = null): ?AstrologyCalculationJob
    {
        $job = AstrologyCalculationJob::where('job_id', $jobId)->first();
        if (!$job) {
            return null;
        }

        // Security: If job belongs to a specific user, verify requesting user matches
        if ($job->user_id !== null && $job->user_id !== $requestingUserId) {
            return null;
        }

        return $job;
    }
}
