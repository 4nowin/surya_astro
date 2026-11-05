<?php

declare(strict_types=1);

namespace App\Services\Astrology\Reports;

use App\Models\AstrologyCalculationJob;
use App\Models\KundliReport;
use App\Services\Astrology\Calculation\CalculationJobManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReportPublicationService
{
    public const RETENTION_DAYS_DISK = 90;
    public const ELIGIBILITY_DAYS_REGENERATION = 365;

    public function __construct(
        protected CalculationJobManager $jobManager
    ) {}

    /**
     * Resolve the isolated, attempt-specific temporary path for worker PDF generation.
     * Guaranteed to never collide between concurrent workers or retry attempts.
     */
    public function getAttemptTempPath(string $reportUuid, string $workerId, int $attempt): string
    {
        $dir = storage_path('app/private/reports/tmp');
        if (!is_dir($dir)) {
            File::makeDirectory($dir, 0700, true, true);
        }

        $safeWorker = preg_replace('/[^a-zA-Z0-9_-]/', '_', $workerId);
        return "{$dir}/{$reportUuid}_{$safeWorker}_{$attempt}.pdf";
    }

    /**
     * Resolve the canonical private storage path for completed reports.
     * Outside web root: storage/app/private/reports/{year}/{month}/{uuid}.pdf
     *
     * @return array{relative: string, absolute: string}
     */
    public function getFinalStoragePath(string $reportUuid, ?CarbonImmutable $date = null): array
    {
        $dt = $date ?? CarbonImmutable::now('UTC');
        $year = $dt->format('Y');
        $month = $dt->format('m');

        $relative = "reports/{$year}/{$month}/{$reportUuid}.pdf";
        $absolute = storage_path("app/private/{$relative}");

        return [
            'relative' => $relative,
            'absolute' => $absolute,
        ];
    }

    /**
     * Atomically publish the generated PDF to its final private destination
     * guarded by worker ownership fencing and database row locking.
     *
     * Invariants enforced:
     * 1. Pre-publication ownership check: Worker must actively own the job.
     * 2. PDF validity: File exists, is readable, and has size > 0.
     * 3. Atomic rename: Uses filesystem rename() to target path.
     * 4. Post-publication transactional verification:
     *    Locks report row with lockForUpdate(). Verifies worker still owns job.
     *    If ownership was lost (e.g. lease expired during rename), rolls back and removes file!
     * 5. File permissions set to 0600.
     *
     * @param KundliReport $report
     * @param string $tempFilePath
     * @param string $workerId
     * @param AstrologyCalculationJob $job
     * @param int $durationMs
     * @param int|null $pageCount
     * @return bool True if published successfully; false if ownership lost or publication aborted
     */
    public function publishReport(
        KundliReport $report,
        string $tempFilePath,
        string $workerId,
        AstrologyCalculationJob $job,
        int $durationMs,
        ?int $pageCount = null
    ): bool {
        // Step 1: Pre-publication ownership fence
        if (!$this->jobManager->verifyOwnership($job, $workerId)) {
            Log::warning("Report publication aborted: worker [{$workerId}] lost ownership of job [{$job->job_id}].");
            $this->cleanupTempFile($tempFilePath);
            return false;
        }

        // Step 2: Validate generated temporary PDF
        if (!file_exists($tempFilePath) || filesize($tempFilePath) === 0) {
            Log::error("Report publication failed: temporary PDF missing or empty at [{$tempFilePath}].");
            $this->cleanupTempFile($tempFilePath);
            return false;
        }

        $fileSize = (int) filesize($tempFilePath);
        $fileHash = (string) hash_file('sha256', $tempFilePath);

        // Step 3: Prepare target private directory
        $paths = $this->getFinalStoragePath($report->uuid);
        $finalAbsolute = $paths['absolute'];
        $finalDir = dirname($finalAbsolute);

        if (!is_dir($finalDir)) {
            File::makeDirectory($finalDir, 0700, true, true);
        }

        // Step 4: Atomic file move
        try {
            if (!@rename($tempFilePath, $finalAbsolute)) {
                // Fallback to copy and unlink if cross-device
                if (!@copy($tempFilePath, $finalAbsolute)) {
                    throw new \RuntimeException("Failed to move temporary report file to: {$finalAbsolute}");
                }
                @unlink($tempFilePath);
            }

            if (PHP_OS_FAMILY !== 'Windows') {
                @chmod($finalAbsolute, 0600);
            }
        } catch (Throwable $e) {
            Log::error("Filesystem publication error for report [{$report->uuid}]: " . $e->getMessage());
            $this->cleanupTempFile($tempFilePath);
            $this->cleanupTempFile($finalAbsolute);
            return false;
        }

        // Step 5: Transactional post-publication verification under database lock
        try {
            return DB::transaction(function () use (
                $report,
                $job,
                $workerId,
                $paths,
                $finalAbsolute,
                $fileHash,
                $fileSize,
                $durationMs,
                $pageCount
            ) {
                // Lock report row
                $lockedReport = KundliReport::where('id', $report->id)
                    ->whereIn('status', [KundliReport::STATUS_QUEUED, KundliReport::STATUS_PROCESSING])
                    ->lockForUpdate()
                    ->first();

                if (!$lockedReport) {
                    // Report is no longer in valid processing state (cancelled or already modified)
                    Log::warning("Report [{$report->uuid}] is no longer processing under lock. Aborting.");
                    $this->cleanupTempFile($finalAbsolute);
                    return false;
                }

                // Verify worker still owns the calculation job under lock
                if (!$this->jobManager->verifyOwnership($job, $workerId)) {
                    Log::warning("Worker [{$workerId}] lost job lease before database commit for report [{$report->uuid}]. Aborting.");
                    $this->cleanupTempFile($finalAbsolute);
                    return false;
                }

                // Update report delivery state
                $lockedReport->update([
                    'status' => KundliReport::STATUS_COMPLETED,
                    'file_path' => $paths['relative'],
                    'file_hash' => $fileHash,
                    'file_size_bytes' => $fileSize,
                    'page_count' => $pageCount,
                    'generation_duration_ms' => $durationMs,
                    'error_message' => null,
                    'pdf_retained_until' => now()->addDays(self::RETENTION_DAYS_DISK),
                    'regeneration_eligible_until' => now()->addDays(self::ELIGIBILITY_DAYS_REGENERATION),
                ]);

                // Mark calculation job completed with ownership fencing
                $this->jobManager->markCompleted($job, "kundli_report:{$report->uuid}", $workerId);

                return true;
            });
        } catch (Throwable $e) {
            Log::error("Database commit error during report publication [{$report->uuid}]: " . $e->getMessage());
            $this->cleanupTempFile($finalAbsolute);
            return false;
        }
    }

    /**
     * Safely delete an attempt-specific temporary file.
     */
    public function cleanupTempFile(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
