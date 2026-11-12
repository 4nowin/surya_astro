<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation;

use App\Models\AstrologyCalculationJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalculationWorkerLauncher
{
    public const WORKER_LOCK_KEY = 'astro_calc_worker_lease';
    public const DEFAULT_LEASE_SECONDS = 60;

    /**
     * Optional callable hook for mocking process launches in tests.
     *
     * @var (callable(string, string): bool)|null
     */
    protected static $launchHook = null;

    /**
     * Set a custom hook to intercept worker launches (used by automated tests).
     *
     * @param (callable(string, string): bool)|null $hook
     */
    public static function setLaunchHook(?callable $hook): void
    {
        self::$launchHook = $hook;
    }

    /**
     * Resolve the verified PHP CLI executable binary path.
     * Priority:
     * 1. ASTRO_CLI_BINARY in .env
     * 2. PHP_CLI_PATH in .env
     * 3. Hostinger Linux known paths
     * 4. System PHP CLI
     */
    public function resolvePhpCliBinary(): string
    {
        $explicit = env('ASTRO_CLI_BINARY') ?: env('PHP_CLI_PATH') ?: config('app.php_cli_path');
        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        $isWindows = PHP_OS_FAMILY === 'Windows';

        if (!$isWindows) {
            $currentBinary = defined('PHP_BINARY') ? (string) PHP_BINARY : '';
            // Only accept PHP_BINARY if it is verified CLI and not a web SAPI (lsphp, fpm, cgi)
            $safeCurrentBinary = ($currentBinary !== ''
                && PHP_SAPI === 'cli'
                && !str_contains($currentBinary, 'fpm')
                && !str_contains($currentBinary, 'cgi')
                && !str_contains($currentBinary, 'lsws')
                && !str_contains($currentBinary, 'lsphp'))
                ? $currentBinary
                : null;

            $linuxCandidates = array_filter([
                $safeCurrentBinary,
                '/usr/bin/php',
                '/usr/local/bin/php',
                '/usr/bin/php8.2',
                '/usr/bin/php8.3',
                '/usr/bin/php8.4',
                '/opt/alt/php82/usr/bin/php',
                '/opt/alt/php83/usr/bin/php',
                '/opt/alt/php84/usr/bin/php',
                '/usr/bin/php-cli',
            ]);

            foreach ($linuxCandidates as $candidate) {
                if (@file_exists($candidate) && @is_executable($candidate)) {
                    return $candidate;
                }
            }

            return 'php';
        }

        // Windows local development
        $wampPhp = 'C:\\wamp64\\bin\\php\\php' . PHP_VERSION . '\\php.exe';
        if (file_exists($wampPhp)) {
            return $wampPhp;
        }

        foreach (glob('C:\\wamp64\\bin\\php\\php*\\php.exe') ?: [] as $wPath) {
            if (file_exists($wPath)) {
                return $wPath;
            }
        }

        return 'php.exe';
    }

    /**
     * Check whether an active worker lease exists and is still valid.
     */
    public function isWorkerRunning(): bool
    {
        return Cache::has(self::WORKER_LOCK_KEY);
    }

    /**
     * Acquire or inspect the single-worker concurrency lock.
     * Prevents launching duplicate concurrent workers on shared hosting.
     */
    public function acquireWorkerLock(int $seconds = self::DEFAULT_LEASE_SECONDS): bool
    {
        return Cache::add(self::WORKER_LOCK_KEY, [
            'locked_at' => now()->toIso8601String(),
            'expires_at' => now()->addSeconds($seconds)->toIso8601String(),
        ], now()->addSeconds($seconds));
    }

    /**
     * Renew the single-worker concurrency lock lease.
     * Overwrites/extends the active lease so long-running worker loops do not expire prematurely.
     */
    public function renewWorkerLock(int $seconds = self::DEFAULT_LEASE_SECONDS): void
    {
        Cache::put(self::WORKER_LOCK_KEY, [
            'locked_at' => now()->toIso8601String(),
            'expires_at' => now()->addSeconds($seconds)->toIso8601String(),
        ], now()->addSeconds($seconds));
    }

    /**
     * Release the single-worker concurrency lock.
     */
    public function releaseWorkerLock(): void
    {
        Cache::forget(self::WORKER_LOCK_KEY);
    }

    /**
     * Automatically launch short-lived PHP CLI process targeted at a specific calculation job.
     *
     * @param AstrologyCalculationJob|string $job Job instance or job_id string
     * @param bool $syncFallback Whether to run synchronously if detached spawn is unverified or blocked
     * @return array{launched: bool, reason: string, error: ?string}
     */
    public function launchWorkerForJob(AstrologyCalculationJob|string $job, bool $syncFallback = false): array
    {
        $jobId = is_string($job) ? $job : $job->job_id;

        // Check if a worker is already running within its valid lease (unless sync fallback requested)
        if (!$syncFallback && $this->isWorkerRunning()) {
            return [
                'launched' => false,
                'reason' => 'worker_already_active',
                'error' => null,
            ];
        }

        // Acquire worker lock before spawning
        if (!$syncFallback && !$this->acquireWorkerLock(self::DEFAULT_LEASE_SECONDS)) {
            return [
                'launched' => false,
                'reason' => 'worker_lock_contention',
                'error' => null,
            ];
        }

        $phpBinary = $this->resolvePhpCliBinary();
        $artisanPath = base_path('artisan');

        if (!file_exists($artisanPath)) {
            if (!$syncFallback) {
                $this->releaseWorkerLock();
            }
            return [
                'launched' => false,
                'reason' => 'artisan_not_found',
                'error' => "Artisan entrypoint not found at: {$artisanPath}",
            ];
        }

        // Test hook interception for automated tests
        if (self::$launchHook !== null) {
            try {
                $hookResult = (self::$launchHook)($phpBinary, $artisanPath);
                return [
                    'launched' => $hookResult,
                    'reason' => $hookResult ? 'test_hook_launched' : 'test_hook_refused',
                    'error' => null,
                ];
            } catch (Throwable $e) {
                if (!$syncFallback) {
                    $this->releaseWorkerLock();
                }
                return [
                    'launched' => false,
                    'reason' => 'test_hook_exception',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $isWindows = PHP_OS_FAMILY === 'Windows';
        $workerLog = storage_path('logs/astro-worker.log');
        $basePath = base_path();

        try {
            if ($isWindows) {
                if ($syncFallback) {
                    $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($artisanPath) . ' astro:calculate-worker --job=' . escapeshellarg($jobId) . ' >> ' . escapeshellarg($workerLog) . ' 2>&1';
                    @exec($cmd);
                } else {
                    $cmd = 'start /B "" ' . escapeshellarg($phpBinary) . ' ' . escapeshellarg($artisanPath) . ' astro:calculate-worker --job=' . escapeshellarg($jobId) . ' >> ' . escapeshellarg($workerLog) . ' 2>&1';
                    $handle = @popen($cmd, 'r');
                    if (is_resource($handle)) {
                        @pclose($handle);
                    }
                }
            } else {
                $disabledRaw = (string) ini_get('disable_functions');
                $disabledFunctions = array_filter(array_map('trim', array_map('strtolower', explode(',', $disabledRaw))));

                if ($syncFallback) {
                    $cmd = 'cd ' . escapeshellarg($basePath) . ' && ' . escapeshellcmd($phpBinary) . ' ' . escapeshellarg($artisanPath) . ' astro:calculate-worker --job=' . escapeshellarg($jobId) . ' >> ' . escapeshellarg($workerLog) . ' 2>&1';
                    if (function_exists('exec') && !in_array('exec', $disabledFunctions, true)) {
                        @exec($cmd);
                    } elseif (function_exists('shell_exec') && !in_array('shell_exec', $disabledFunctions, true)) {
                        @shell_exec($cmd);
                    }
                } else {
                    $cmd = 'cd ' . escapeshellarg($basePath) . ' && nohup ' . escapeshellcmd($phpBinary) . ' ' . escapeshellarg($artisanPath) . ' astro:calculate-worker --job=' . escapeshellarg($jobId) . ' >> ' . escapeshellarg($workerLog) . ' 2>&1 &';

                    if (function_exists('exec') && !in_array('exec', $disabledFunctions, true)) {
                        @exec($cmd);
                    } elseif (function_exists('proc_open') && !in_array('proc_open', $disabledFunctions, true)) {
                        $descriptors = [
                            0 => ['pipe', 'r'],
                            1 => ['file', $workerLog, 'a'],
                            2 => ['file', $workerLog, 'a'],
                        ];
                        $pipes = [];
                        $process = @proc_open($cmd, $descriptors, $pipes, $basePath);
                        if (is_resource($process)) {
                            @proc_close($process);
                        }
                    } elseif (function_exists('shell_exec') && !in_array('shell_exec', $disabledFunctions, true)) {
                        @shell_exec($cmd);
                    } elseif (function_exists('popen') && !in_array('popen', $disabledFunctions, true)) {
                        $handle = @popen($cmd, 'r');
                        if (is_resource($handle)) {
                            @pclose($handle);
                        }
                    } else {
                        return [
                            'launched' => false,
                            'reason' => 'process_spawning_disabled_by_host',
                            'error' => 'Process execution functions disabled in php.ini.',
                        ];
                    }
                }
            }

            return [
                'launched' => true,
                'reason' => $syncFallback ? 'worker_executed_sync_cli' : 'worker_spawned_job_cli',
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error("Failed to launch CLI calculation worker for job [{$jobId}]: {$e->getMessage()}", [
                'exception' => get_class($e),
                'php_binary' => $phpBinary,
            ]);

            return [
                'launched' => false,
                'reason' => 'spawn_failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Launch the background worker process if no worker is currently active.
     *
     * @return array{launched: bool, reason: string, error: ?string}
     */
    public function launchWorkerIfNeeded(): array
    {
        // Check if an active worker is already running within its valid lease
        if ($this->isWorkerRunning()) {
            return [
                'launched' => false,
                'reason' => 'worker_already_active',
                'error' => null,
            ];
        }

        // Acquire worker lock before spawning to avoid race conditions
        $acquired = $this->acquireWorkerLock(self::DEFAULT_LEASE_SECONDS);
        if (!$acquired) {
            return [
                'launched' => false,
                'reason' => 'worker_lock_contention',
                'error' => null,
            ];
        }

        $phpBinary = $this->resolvePhpCliBinary();
        $artisanPath = base_path('artisan');

        if (!file_exists($artisanPath)) {
            $this->releaseWorkerLock();
            return [
                'launched' => false,
                'reason' => 'artisan_not_found',
                'error' => "Artisan entrypoint not found at: {$artisanPath}",
            ];
        }

        // If a test hook is installed, dispatch to test hook
        if (self::$launchHook !== null) {
            try {
                $hookResult = (self::$launchHook)($phpBinary, $artisanPath);
                return [
                    'launched' => $hookResult,
                    'reason' => $hookResult ? 'test_hook_launched' : 'test_hook_refused',
                    'error' => null,
                ];
            } catch (Throwable $e) {
                $this->releaseWorkerLock();
                return [
                    'launched' => false,
                    'reason' => 'test_hook_exception',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Production launch
        $isWindows = PHP_OS_FAMILY === 'Windows';

        try {
            if ($isWindows) {
                // Windows detached background spawn
                $cmd = 'start /B "" ' . escapeshellarg($phpBinary) . ' ' . escapeshellarg($artisanPath) . ' astro:calculate-worker';
                $handle = @popen($cmd, 'r');
                if (is_resource($handle)) {
                    pclose($handle);
                }
            } else {
                // Linux detached background spawn with diagnostic logging to storage/logs/astro-worker.log
                $basePath = base_path();
                $workerLog = storage_path('logs/astro-worker.log');
                $cmd = 'cd ' . escapeshellarg($basePath) . ' && nohup ' . escapeshellcmd($phpBinary) . ' ' . escapeshellarg($artisanPath) . ' astro:calculate-worker >> ' . escapeshellarg($workerLog) . ' 2>&1 &';

                $disabledRaw = (string) ini_get('disable_functions');
                $disabledFunctions = array_filter(array_map('trim', array_map('strtolower', explode(',', $disabledRaw))));

                if (function_exists('exec') && !in_array('exec', $disabledFunctions, true)) {
                    @exec($cmd);
                } elseif (function_exists('proc_open') && !in_array('proc_open', $disabledFunctions, true)) {
                    $descriptors = [
                        0 => ['pipe', 'r'],
                        1 => ['file', $workerLog, 'a'],
                        2 => ['file', $workerLog, 'a'],
                    ];
                    $pipes = [];
                    $process = @proc_open($cmd, $descriptors, $pipes, $basePath);
                    if (is_resource($process)) {
                        @proc_close($process);
                    }
                } elseif (function_exists('shell_exec') && !in_array('shell_exec', $disabledFunctions, true)) {
                    @shell_exec($cmd);
                } elseif (function_exists('popen') && !in_array('popen', $disabledFunctions, true)) {
                    $handle = @popen($cmd, 'r');
                    if (is_resource($handle)) {
                        @pclose($handle);
                    }
                } else {
                    Log::info('Background process execution disabled in php.ini. Calculation queued for scheduled cron worker.');
                    $this->releaseWorkerLock();
                    return [
                        'launched' => false,
                        'reason' => 'process_spawning_disabled_by_host',
                        'error' => 'Process execution functions disabled in php.ini. Calculation queued for cron worker.',
                    ];
                }
            }

            return [
                'launched' => true,
                'reason' => 'worker_spawned_detached',
                'error' => null,
            ];
        } catch (Throwable $e) {
            $this->releaseWorkerLock();
            Log::error('Failed to launch detached calculation worker: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'php_binary' => $phpBinary,
            ]);

            return [
                'launched' => false,
                'reason' => 'spawn_failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
