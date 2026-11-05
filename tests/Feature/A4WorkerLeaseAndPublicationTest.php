<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AstrologyCalculationJob;
use App\Models\KundliReport;
use App\Models\ReportPurchase;
use App\Models\User;
use App\Services\Astrology\Calculation\CalculationJobManager;
use App\Services\Astrology\Reports\ReportPublicationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class A4WorkerLeaseAndPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected CalculationJobManager $jobManager;
    protected ReportPublicationService $publicationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jobManager = app(CalculationJobManager::class);
        $this->publicationService = app(ReportPublicationService::class);
    }

    protected function createProcessingJob(string $workerId = 'worker_alpha', int $leaseSeconds = 60): AstrologyCalculationJob
    {
        return AstrologyCalculationJob::create([
            'job_id' => 'job_' . Str::random(12),
            'calculation_type' => 'detailed_kundli_report',
            'canonical_key' => 'canon_' . Str::random(16),
            'active_key' => 'active_' . Str::random(16),
            'payload' => ['test' => true],
            'status' => AstrologyCalculationJob::STATUS_PROCESSING,
            'attempts' => 1,
            'max_attempts' => 3,
            'worker_id' => $workerId,
            'started_at' => now(),
            'lease_expires_at' => now()->addSeconds($leaseSeconds),
        ]);
    }

    protected function createReportWithDebit(string $status = 'processing'): KundliReport
    {
        $user = User::factory()->create();
        $debit = ReportPurchase::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'idempotency_key' => 'debit_' . Str::random(12),
            'transaction_type' => 'debit',
            'amount' => 149.00,
            'currency' => 'INR',
            'status' => 'completed',
        ]);

        return KundliReport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'report_purchase_id' => $debit->id,
            'status' => $status,
            'language' => 'en',
        ]);
    }

    /**
     * Test successful lease renewal:
     * When worker owns job and lease is active, renewLease extends lease_expires_at.
     */
    public function test_successful_lease_renewal_extends_expiration(): void
    {
        $workerId = 'worker_active_1';
        $job = $this->createProcessingJob($workerId, 30);

        $initialExpiry = $job->lease_expires_at;

        // Sleep/advance time slightly
        $this->travel(10)->seconds();

        $renewed = $this->jobManager->renewLease($job, $workerId, 120);
        $this->assertTrue($renewed);

        $job->refresh();
        $this->assertTrue($job->lease_expires_at->isAfter($initialExpiry));
        $this->assertEquals(AstrologyCalculationJob::STATUS_PROCESSING, $job->status);
    }

    /**
     * Test lease renewal after expiration is rejected:
     * If lease has already expired (lease_expires_at < now), renewLease returns false.
     */
    public function test_lease_renewal_after_expiration_is_rejected(): void
    {
        $workerId = 'worker_expired_1';
        $job = $this->createProcessingJob($workerId, 10);

        // Travel beyond the lease expiration window
        $this->travel(15)->seconds();

        $renewed = $this->jobManager->renewLease($job, $workerId, 60);
        $this->assertFalse($renewed, "Expired worker must not be allowed to renew an expired lease");
    }

    /**
     * Test worker cannot renew a lease it does not own:
     * A different workerId is rejected.
     */
    public function test_worker_cannot_renew_unowned_lease(): void
    {
        $ownerWorker = 'worker_owner';
        $impostorWorker = 'worker_impostor';

        $job = $this->createProcessingJob($ownerWorker, 60);

        $renewed = $this->jobManager->renewLease($job, $impostorWorker, 60);
        $this->assertFalse($renewed, "Impostor worker must be blocked from renewing someone else's lease");
    }

    /**
     * Test ownership fencing on markCompleted and markFailed:
     * A stale worker cannot mark the job completed or failed.
     */
    public function test_stale_worker_cannot_update_job_status(): void
    {
        $worker1 = 'worker_first';
        $worker2 = 'worker_second';

        $job = $this->createProcessingJob($worker1, 10);

        // Advance past expiration, simulate job reclaimed by worker2
        $this->travel(15)->seconds();
        $job->update([
            'worker_id' => $worker2,
            'lease_expires_at' => now()->addSeconds(60),
            'attempts' => 2,
        ]);

        // Worker1 attempts to mark completed with its workerId
        $completed = $this->jobManager->markCompleted($job, 'cache_key_test', $worker1);
        $this->assertFalse($completed, "Stale worker1 must not be allowed to mark job completed");

        // Job status remains processing with worker2
        $job->refresh();
        $this->assertEquals(AstrologyCalculationJob::STATUS_PROCESSING, $job->status);
        $this->assertEquals($worker2, $job->worker_id);

        // Worker1 attempts to mark failed
        $failed = $this->jobManager->markFailed($job, 'Stale error', null, $worker1);
        $this->assertFalse($failed, "Stale worker1 must not be allowed to mark job failed");
    }

    /**
     * Test attempt-specific temporary file isolation:
     * Two different workers and attempts generate distinct isolated files in storage/app/private/reports/tmp/.
     */
    public function test_attempt_specific_temporary_file_isolation(): void
    {
        $reportUuid = (string) Str::uuid();

        $tmpPath1 = $this->publicationService->getAttemptTempPath($reportUuid, 'worker_A', 1);
        $tmpPath2 = $this->publicationService->getAttemptTempPath($reportUuid, 'worker_B', 2);

        $this->assertNotEquals($tmpPath1, $tmpPath2);
        $this->assertStringContainsString('storage/app/private/reports/tmp', str_replace('\\', '/', $tmpPath1));
        $this->assertStringContainsString('worker_A_1.pdf', $tmpPath1);
        $this->assertStringContainsString('worker_B_2.pdf', $tmpPath2);
    }

    /**
     * Test successful atomic publication:
     * Temporary file is atomically moved to final private destination, report marked completed, hash recorded.
     */
    public function test_successful_atomic_publication(): void
    {
        $workerId = 'worker_valid_publisher';
        $job = $this->createProcessingJob($workerId, 120);
        $report = $this->createReportWithDebit('processing');

        $tmpPath = $this->publicationService->getAttemptTempPath($report->uuid, $workerId, 1);
        File::put($tmpPath, '%PDF-1.4 Mock Content for Valid Test');

        $published = $this->publicationService->publishReport(
            report: $report,
            tempFilePath: $tmpPath,
            workerId: $workerId,
            job: $job,
            durationMs: 1450,
            pageCount: 8
        );

        $this->assertTrue($published);

        // Temp file must no longer exist
        $this->assertFileDoesNotExist($tmpPath);

        // Final report file must exist in private storage
        $paths = $this->publicationService->getFinalStoragePath($report->uuid);
        $this->assertFileExists($paths['absolute']);

        // Database row updated
        $report->refresh();
        $this->assertSame(KundliReport::STATUS_COMPLETED, $report->status);
        $this->assertSame($paths['relative'], $report->file_path);
        $this->assertNotNull($report->file_hash);
        $this->assertSame(8, $report->page_count);
        $this->assertSame(1450, $report->generation_duration_ms);

        // Job marked completed
        $job->refresh();
        $this->assertSame(AstrologyCalculationJob::STATUS_COMPLETED, $job->status);

        // Clean up test file
        @unlink($paths['absolute']);
    }

    /**
     * Test stale worker cannot publish report:
     * If worker lost lease, publishReport cleans up tmp file, refuses publication, and leaves report processing.
     */
    public function test_stale_worker_cannot_publish_report(): void
    {
        $workerStale = 'worker_stale';
        $job = $this->createProcessingJob($workerStale, 10);
        $report = $this->createReportWithDebit('processing');

        $tmpPath = $this->publicationService->getAttemptTempPath($report->uuid, $workerStale, 1);
        File::put($tmpPath, '%PDF-1.4 Stale Worker PDF');

        // Travel past expiration
        $this->travel(15)->seconds();

        $published = $this->publicationService->publishReport(
            report: $report,
            tempFilePath: $tmpPath,
            workerId: $workerStale,
            job: $job,
            durationMs: 2000
        );

        $this->assertFalse($published, "Stale worker publication must be blocked");

        // Temporary file must be cleaned up
        $this->assertFileDoesNotExist($tmpPath);

        // Final file must not be created
        $paths = $this->publicationService->getFinalStoragePath($report->uuid);
        $this->assertFileDoesNotExist($paths['absolute']);

        // Report status must remain processing (not completed)
        $this->assertSame(KundliReport::STATUS_PROCESSING, $report->fresh()->status);
    }

    /**
     * Test temporary file cleanup on failure:
     */
    public function test_temporary_file_cleanup_on_failure(): void
    {
        $workerId = 'worker_clean';
        $tmpPath = $this->publicationService->getAttemptTempPath('uuid_clean', $workerId, 1);

        File::put($tmpPath, 'Temporary PDF Data');
        $this->assertFileExists($tmpPath);

        $this->publicationService->cleanupTempFile($tmpPath);
        $this->assertFileDoesNotExist($tmpPath);
    }

    /**
     * Test actual worker heartbeat integration during calculation:
     * When worker owns job, renewLease is called and extends lease.
     * When worker loses ownership, calculation aborts safely, cleans up temp file, and does not publish.
     */
    public function test_worker_heartbeat_renewal_during_calculation_and_abort_on_lease_loss(): void
    {
        $workerId = 'worker_heartbeat_test';
        $job = $this->createProcessingJob($workerId, 30);
        $report = $this->createReportWithDebit('queued');

        $handler = app(\App\Services\Astrology\Calculation\Handlers\DetailedKundliReportCalculationHandler::class);

        // 1. Successful heartbeat and calculation
        $result = $handler->calculate([
            'report_uuid' => $report->uuid,
            'worker_id' => $workerId,
            'job_id' => $job->job_id,
            'attempt' => 1,
            'language' => 'en',
        ]);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(KundliReport::STATUS_COMPLETED, $report->fresh()->status);

        // 2. Failure case: worker lost lease prior to calculation
        $report2 = $this->createReportWithDebit('queued');
        $job2 = $this->createProcessingJob($workerId, 10);

        // Advance time so lease expires
        $this->travel(15)->seconds();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('lost lease');

        try {
            $handler->calculate([
                'report_uuid' => $report2->uuid,
                'worker_id' => $workerId,
                'job_id' => $job2->job_id,
                'attempt' => 1,
                'language' => 'en',
            ]);
        } finally {
            // Report must NOT be completed
            $this->assertNotEquals(KundliReport::STATUS_COMPLETED, $report2->fresh()->status);
            $this->assertSame(KundliReport::STATUS_FAILED, $report2->fresh()->status);

            // Temp file must be cleaned up
            $tmpPath = $this->publicationService->getAttemptTempPath($report2->uuid, $workerId, 1);
            $this->assertFileDoesNotExist($tmpPath);
        }
    }

    /**
     * Test failed worker launch does not leave job indefinitely marked as processing.
     * When worker launcher fails, the job remains in 'pending' status so it can be retried.
     */
    public function test_failed_worker_launch_does_not_leave_job_processing(): void
    {
        \App\Services\Astrology\Calculation\CalculationWorkerLauncher::setLaunchHook(function () {
            throw new \RuntimeException('Simulated process spawn failure on shared hosting');
        });

        $user = User::factory()->create();
        $jobManager = app(CalculationJobManager::class);

        // Find or create a new job
        $jobInfo = $jobManager->findOrCreateJob('detailed_kundli_report', [
            'report_uuid' => (string) Str::uuid(),
            'language' => 'en',
        ], $user->id);

        $job = $jobInfo['job'];
        $this->assertSame(AstrologyCalculationJob::STATUS_PENDING, $job->status);

        // Attempt launch
        $launcher = app(\App\Services\Astrology\Calculation\CalculationWorkerLauncher::class);
        $launchResult = $launcher->launchWorkerIfNeeded();

        $this->assertFalse($launchResult['launched']);
        $this->assertSame('test_hook_exception', $launchResult['reason']);

        // Job must NOT be in processing status; remains pending
        $job->refresh();
        $this->assertSame(AstrologyCalculationJob::STATUS_PENDING, $job->status);
        $this->assertNull($job->worker_id);
        $this->assertNull($job->lease_expires_at);

        \App\Services\Astrology\Calculation\CalculationWorkerLauncher::setLaunchHook(null);
    }

    /**
     * Test a stale worker cannot overwrite or delete a newer worker's published output.
     */
    public function test_stale_worker_cannot_overwrite_newer_workers_published_output(): void
    {
        $workerNew = 'worker_new';
        $workerStale = 'worker_stale';

        $report = $this->createReportWithDebit('processing');

        // Worker New publishes valid report
        $jobNew = $this->createProcessingJob($workerNew, 120);
        $tmpNew = $this->publicationService->getAttemptTempPath($report->uuid, $workerNew, 1);
        File::put($tmpNew, '%PDF-1.4 Legitimate Content from Worker New');

        $published = $this->publicationService->publishReport(
            report: $report,
            tempFilePath: $tmpNew,
            workerId: $workerNew,
            job: $jobNew,
            durationMs: 1200,
            pageCount: 10
        );
        $this->assertTrue($published);

        $paths = $this->publicationService->getFinalStoragePath($report->uuid);
        $legitimateHash = hash_file('sha256', $paths['absolute']);
        $this->assertSame($legitimateHash, $report->fresh()->file_hash);

        // Now stale worker with expired job attempts to publish malicious/stale file
        $jobStale = $this->createProcessingJob($workerStale, 10);
        $this->travel(15)->seconds(); // Stale lease expires

        $tmpStale = $this->publicationService->getAttemptTempPath($report->uuid, $workerStale, 2);
        File::put($tmpStale, '%PDF-1.4 Stale Content to be Blocked');

        $stalePublishResult = $this->publicationService->publishReport(
            report: $report->fresh(),
            tempFilePath: $tmpStale,
            workerId: $workerStale,
            job: $jobStale,
            durationMs: 3000
        );

        $this->assertFalse($stalePublishResult, "Stale worker publication must be rejected");

        // The legitimate published file must be preserved intact!
        $this->assertFileExists($paths['absolute']);
        $this->assertSame($legitimateHash, hash_file('sha256', $paths['absolute']));
        $this->assertSame($legitimateHash, $report->fresh()->file_hash);

        // Stale tmp file must be cleaned up
        $this->assertFileDoesNotExist($tmpStale);

        @unlink($paths['absolute']);
    }

    /**
     * Test failure during publication does not mark report completed:
     * If temporary file is missing or corrupted, publication fails and report does not complete.
     */
    public function test_failure_during_publication_does_not_mark_report_completed(): void
    {
        $workerId = 'worker_corrupted';
        $job = $this->createProcessingJob($workerId, 120);
        $report = $this->createReportWithDebit('processing');

        // Missing/non-existent temporary file
        $missingPath = storage_path('app/private/reports/tmp/non_existent.pdf');

        $published = $this->publicationService->publishReport(
            report: $report,
            tempFilePath: $missingPath,
            workerId: $workerId,
            job: $job,
            durationMs: 500
        );

        $this->assertFalse($published);

        // Report must NOT be marked completed
        $this->assertSame(KundliReport::STATUS_PROCESSING, $report->fresh()->status);
        $this->assertNull($report->fresh()->file_path);
    }
}
