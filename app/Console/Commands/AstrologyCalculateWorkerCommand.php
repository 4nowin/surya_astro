<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Astrology\Calculation\CalculationHandlerRegistry;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Calculation\CalculationWorkerLauncher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class AstrologyCalculateWorkerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'astro:calculate-worker
                            {--job= : Specific calculation job ID to execute and exit}
                            {--max-jobs=10 : Maximum number of jobs to process before exiting}
                            {--max-time=45 : Maximum execution time in seconds}
                            {--retry-failed : Reset failed calculation jobs back to pending and retry them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending astrology calculation jobs sequentially via PHP CLI';

    /**
     * Execute the console command.
     */
    public function handle(
        CalculationJobManager $jobManager,
        CalculationHandlerRegistry $registry,
        CalculationWorkerLauncher $launcher
    ): int {
        $maxJobs = (int) $this->option('max-jobs');
        $maxTime = (int) $this->option('max-time');
        $specificJobId = $this->option('job') ? trim((string) $this->option('job')) : null;

        if ($this->option('retry-failed')) {
            $resetCount = \App\Models\AstrologyCalculationJob::where('status', \App\Models\AstrologyCalculationJob::STATUS_FAILED)
                ->update([
                    'status' => \App\Models\AstrologyCalculationJob::STATUS_PENDING,
                    'attempts' => 0,
                    'lease_expires_at' => null,
                    'worker_id' => null,
                    'error_message' => null,
                    'error_context' => null,
                ]);
            $this->info("Reset {$resetCount} failed calculation job(s) back to pending.");
        }

        $workerId = 'worker_' . bin2hex(random_bytes(6)) . '_' . getmypid();
        $startTime = microtime(true);
        $bootstrapMs = defined('LARAVEL_START') ? round((microtime(true) - LARAVEL_START) * 1000, 2) : 0.0;
        $workerLog = storage_path('logs/astro-worker.log');
        $processedCount = 0;

        try {
            // Specific job mode: execute targeted job and exit immediately (no queue scanning)
            if (!empty($specificJobId)) {
                $this->info("Astrology calculation worker [{$workerId}] executing targeted job: {$specificJobId} (bootstrap: {$bootstrapMs}ms)");
                $tClaim0 = microtime(true);
                $job = $jobManager->claimJobById($specificJobId, $workerId, 30);
                $claimMs = round((microtime(true) - $tClaim0) * 1000, 2);

                if (!$job) {
                    $existing = \App\Models\AstrologyCalculationJob::where('job_id', $specificJobId)->first();
                    if ($existing && $existing->isCompleted()) {
                        $this->info("Job [{$specificJobId}] is already completed.");
                        return self::SUCCESS;
                    }
                    $this->warn("Job [{$specificJobId}] not found or cannot be claimed by worker.");
                    return self::FAILURE;
                }

                if ($job->isCompleted()) {
                    $this->info("Job [{$specificJobId}] already completed.");
                    return self::SUCCESS;
                }

                $jobStartTime = microtime(true);
                try {
                    $handler = $registry->get($job->calculation_type);
                    $jobPayload = array_merge($job->payload, [
                        'worker_id' => $workerId,
                        'job_id' => $job->job_id,
                        'attempt' => $job->attempts,
                    ]);

                    $tCalc0 = microtime(true);
                    $result = $handler->calculate($jobPayload);
                    $calcMs = round((microtime(true) - $tCalc0) * 1000, 2);

                    $tStore0 = microtime(true);
                    $cacheKey = $handler->storeResult($job->canonical_key, $result, $job->payload);
                    $storeMs = round((microtime(true) - $tStore0) * 1000, 2);

                    $tComplete0 = microtime(true);
                    $jobManager->markCompleted($job, $cacheKey, $workerId, $result);
                    $completeMs = round((microtime(true) - $tComplete0) * 1000, 2);

                    $totalJobMs = round((microtime(true) - $jobStartTime) * 1000, 2);
                    $timingMetrics = [
                        'timestamp' => now()->toIso8601String(),
                        'event' => 'targeted_job_completed',
                        'worker_id' => $workerId,
                        'job_id' => $job->job_id,
                        'type' => $job->calculation_type,
                        'bootstrap_ms' => $bootstrapMs,
                        'claim_ms' => $claimMs,
                        'calc_ms' => $calcMs,
                        'store_ms' => $storeMs,
                        'complete_ms' => $completeMs,
                        'total_job_ms' => $totalJobMs,
                    ];
                    @file_put_contents($workerLog, json_encode($timingMetrics, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
                    $this->info("Job [{$job->job_id}] completed successfully in {$totalJobMs}ms (calc: {$calcMs}ms, store: {$storeMs}ms).");
                    Log::info("Job [{$job->job_id}] completed successfully in {$totalJobMs}ms (bootstrap: {$bootstrapMs}ms, claim: {$claimMs}ms, calc: {$calcMs}ms, store: {$storeMs}ms, complete: {$completeMs}ms)");

                    return self::SUCCESS;
                } catch (Throwable $e) {
                    $totalJobMs = round((microtime(true) - $jobStartTime) * 1000, 2);
                    $this->error("Job [{$job->job_id}] failed after {$totalJobMs}ms: " . $e->getMessage());

                    $failMetrics = [
                        'timestamp' => now()->toIso8601String(),
                        'event' => 'targeted_job_failed',
                        'worker_id' => $workerId,
                        'job_id' => $job->job_id,
                        'type' => $job->calculation_type,
                        'bootstrap_ms' => $bootstrapMs,
                        'claim_ms' => $claimMs,
                        'total_job_ms' => $totalJobMs,
                        'error' => $e->getMessage(),
                    ];
                    @file_put_contents($workerLog, json_encode($failMetrics, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

                    Log::error("Astrology calculation job [{$job->job_id}] failed: " . $e->getMessage(), [
                        'job_id' => $job->job_id,
                        'type' => $job->calculation_type,
                        'exception' => get_class($e),
                        'attempts' => $job->attempts,
                    ]);

                    $jobManager->markFailed(
                        $job,
                        $e->getMessage(),
                        [
                            'exception' => get_class($e),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ],
                        $workerId
                    );

                    return self::FAILURE;
                }
            }

            $this->info("Astrology calculation worker [{$workerId}] started. Max jobs: {$maxJobs}, Max time: {$maxTime}s (bootstrap: {$bootstrapMs}ms)");

            while ($processedCount < $maxJobs) {
                // Check if maximum execution time has been exceeded
                if ((microtime(true) - $startTime) >= $maxTime) {
                    $this->info("Worker reached max execution time ({$maxTime}s). Exiting.");
                    break;
                }

                // Renew worker lease
                $launcher->renewWorkerLock(30);

                // Claim next eligible job atomically
                $tClaim0 = microtime(true);
                $job = $jobManager->claimNextJob($workerId, 30);
                $claimMs = round((microtime(true) - $tClaim0) * 1000, 2);

                if (!$job) {
                    $this->line("No eligible pending calculation jobs found. Worker exiting cleanly.");
                    break;
                }

                $this->info("Processing job [{$job->job_id}] type: {$job->calculation_type} attempt: {$job->attempts}/{$job->max_attempts} (claim: {$claimMs}ms)");

                $jobStartTime = microtime(true);

                try {
                    $handler = $registry->get($job->calculation_type);

                    // Pass worker ownership context and attempt to handler payload
                    $jobPayload = array_merge($job->payload, [
                        'worker_id' => $workerId,
                        'job_id' => $job->job_id,
                        'attempt' => $job->attempts,
                    ]);

                    // Perform the calculation
                    $tCalc0 = microtime(true);
                    $result = $handler->calculate($jobPayload);
                    $calcMs = round((microtime(true) - $tCalc0) * 1000, 2);

                    // Store result in durable cache / storage
                    $tStore0 = microtime(true);
                    $cacheKey = $handler->storeResult($job->canonical_key, $result, $job->payload);
                    $storeMs = round((microtime(true) - $tStore0) * 1000, 2);

                    // Mark completed with ownership fencing and persistent MySQL storage
                    $tComplete0 = microtime(true);
                    $jobManager->markCompleted($job, $cacheKey, $workerId, $result);
                    $completeMs = round((microtime(true) - $tComplete0) * 1000, 2);

                    $durationMs = round((microtime(true) - $jobStartTime) * 1000, 2);
                    $timingMetrics = [
                        'timestamp' => now()->toIso8601String(),
                        'event' => 'queue_job_completed',
                        'worker_id' => $workerId,
                        'job_id' => $job->job_id,
                        'type' => $job->calculation_type,
                        'claim_ms' => $claimMs,
                        'calc_ms' => $calcMs,
                        'store_ms' => $storeMs,
                        'complete_ms' => $completeMs,
                        'total_job_ms' => $durationMs,
                    ];
                    @file_put_contents($workerLog, json_encode($timingMetrics, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
                    $this->info("Job [{$job->job_id}] completed successfully in {$durationMs}ms.");
                    Log::info("Queue job [{$job->job_id}] completed in {$durationMs}ms (calc: {$calcMs}ms, store: {$storeMs}ms, complete: {$completeMs}ms)");
                    $processedCount++;
                } catch (Throwable $e) {
                    $durationMs = round((microtime(true) - $jobStartTime) * 1000, 2);
                    $this->error("Job [{$job->job_id}] failed after {$durationMs}ms: " . $e->getMessage());

                    Log::error("Astrology calculation job [{$job->job_id}] failed: " . $e->getMessage(), [
                        'job_id' => $job->job_id,
                        'type' => $job->calculation_type,
                        'exception' => get_class($e),
                        'attempts' => $job->attempts,
                    ]);

                    $jobManager->markFailed(
                        $job,
                        $e->getMessage(),
                        [
                            'exception' => get_class($e),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ],
                        $workerId
                    );

                    $processedCount++;
                }
            }
        } finally {
            if (!empty($specificJobId)) {
                $launcher->releaseJobWorkerLock($specificJobId);
            }
            $launcher->releaseWorkerLock();
        }

        $totalDuration = round(microtime(true) - $startTime, 2);
        $this->info("Worker [{$workerId}] finished. Processed: {$processedCount} job(s) in {$totalDuration}s.");

        return self::SUCCESS;
    }
}
