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
        $activeKey = "{$type}:{$canonicalKey}";

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
        try {
            return DB::transaction(function () use ($type, $canonicalKey, $activeKey, $payload, $userId, $handler) {
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
                            'active_key' => $activeKey,
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
                                    'active_key' => $activeKey,
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

                    // If failed: reset to pending on new request so transient worker failures don't permanently brick calculations
                    if ($existingJob->isFailed()) {
                        $existingJob->update([
                            'status' => AstrologyCalculationJob::STATUS_PENDING,
                            'active_key' => $activeKey,
                            'attempts' => 0,
                            'lease_expires_at' => null,
                            'worker_id' => null,
                            'started_at' => null,
                            'completed_at' => null,
                            'error_message' => null,
                            'error_context' => null,
                        ]);

                        return [
                            'ready' => false,
                            'job' => $existingJob->fresh(),
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
                    'active_key' => $activeKey,
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
        } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
            // Concurrent insert race condition caught by database unique index
            $concurrentJob = AstrologyCalculationJob::where('calculation_type', $type)
                ->where('canonical_key', $canonicalKey)
                ->latest('id')
                ->first();

            if ($concurrentJob) {
                return [
                    'ready' => false,
                    'job' => $concurrentJob,
                    'canonical_key' => $canonicalKey,
                    'is_new' => false,
                ];
            }

            throw $e;
        }
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
     * Atomically renew the lease for an active calculation job if owned by $workerId and not expired.
     *
     * @param AstrologyCalculationJob $job
     * @param string $workerId
     * @param int $additionalSeconds
     * @return bool True if lease was successfully renewed; false if ownership was lost or lease expired
     */
    public function renewLease(AstrologyCalculationJob $job, string $workerId, int $additionalSeconds = 60): bool
    {
        $affected = AstrologyCalculationJob::where('id', $job->id)
            ->where('status', AstrologyCalculationJob::STATUS_PROCESSING)
            ->where('worker_id', $workerId)
            ->where('lease_expires_at', '>', now())
            ->update([
                'lease_expires_at' => now()->addSeconds($additionalSeconds),
            ]);

        return $affected > 0;
    }

    /**
     * Verify whether a worker currently owns a valid, unexpired lease on the job.
     */
    public function verifyOwnership(AstrologyCalculationJob $job, string $workerId): bool
    {
        return AstrologyCalculationJob::where('id', $job->id)
            ->where('status', AstrologyCalculationJob::STATUS_PROCESSING)
            ->where('worker_id', $workerId)
            ->where('lease_expires_at', '>', now())
            ->exists();
    }

    /**
     * Mark a job completed and record the result cache key, with optional ownership fencing.
     */
    public function markCompleted(AstrologyCalculationJob $job, string $resultCacheKey, ?string $workerId = null): bool
    {
        $query = AstrologyCalculationJob::where('id', $job->id);

        if ($workerId !== null) {
            $query->where('status', AstrologyCalculationJob::STATUS_PROCESSING)
                ->where('worker_id', $workerId)
                ->where('lease_expires_at', '>', now());
        }

        $affected = $query->update([
            'status' => AstrologyCalculationJob::STATUS_COMPLETED,
            'active_key' => null,
            'completed_at' => now(),
            'result_cache_key' => $resultCacheKey,
            'lease_expires_at' => null,
            'error_message' => null,
            'error_context' => null,
        ]);

        return $affected > 0;
    }

    /**
     * Mark a job failed or return to pending if attempts remain, with optional ownership fencing.
     */
    public function markFailed(AstrologyCalculationJob $job, string $errorMessage, ?array $context = null, ?string $workerId = null): bool
    {
        $hasRemainingAttempts = $job->attempts < $job->max_attempts;

        $query = AstrologyCalculationJob::where('id', $job->id);

        if ($workerId !== null) {
            $query->where('status', AstrologyCalculationJob::STATUS_PROCESSING)
                ->where('worker_id', $workerId);
        }

        $affected = $query->update([
            'status' => $hasRemainingAttempts ? AstrologyCalculationJob::STATUS_PENDING : AstrologyCalculationJob::STATUS_FAILED,
            'active_key' => $hasRemainingAttempts ? "{$job->calculation_type}:{$job->canonical_key}" : null,
            'error_message' => $errorMessage,
            'error_context' => $context,
            'lease_expires_at' => null,
            'worker_id' => null,
        ]);

        return $affected > 0;
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
