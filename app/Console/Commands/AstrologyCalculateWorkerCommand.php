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
        $processedCount = 0;

        $this->info("Astrology calculation worker [{$workerId}] started. Max jobs: {$maxJobs}, Max time: {$maxTime}s");

        try {
            while ($processedCount < $maxJobs) {
                // Check if maximum execution time has been exceeded
                if ((microtime(true) - $startTime) >= $maxTime) {
                    $this->info("Worker reached max execution time ({$maxTime}s). Exiting.");
                    break;
                }

                // Renew worker lease
                $launcher->renewWorkerLock(60);

                // Claim next eligible job atomically
                $job = $jobManager->claimNextJob($workerId, 60);

                if (!$job) {
                    $this->line("No eligible pending calculation jobs found. Worker exiting cleanly.");
                    break;
                }

                $this->info("Processing job [{$job->job_id}] type: {$job->calculation_type} attempt: {$job->attempts}/{$job->max_attempts}");

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
                    $result = $handler->calculate($jobPayload);

                    // Store result in durable cache / storage
                    $cacheKey = $handler->storeResult($job->canonical_key, $result, $job->payload);

                    // Mark completed with ownership fencing
                    $jobManager->markCompleted($job, $cacheKey, $workerId);

                    $durationMs = round((microtime(true) - $jobStartTime) * 1000, 2);
                    $this->info("Job [{$job->job_id}] completed successfully in {$durationMs}ms.");
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
            $launcher->releaseWorkerLock();
        }

        $totalDuration = round(microtime(true) - $startTime, 2);
        $this->info("Worker [{$workerId}] finished. Processed: {$processedCount} job(s) in {$totalDuration}s.");

        return self::SUCCESS;
    }
}
