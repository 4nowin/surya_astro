<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use Illuminate\Support\Facades\File;
use Throwable;

class CliExecutionCompatibilityService
{
    /**
     * Dedicated storage directory for CLI test output files.
     */
    public static function getStorageDirectory(): string
    {
        return storage_path('app/diagnostics/cli_tests');
    }

    /**
     * Absolute path to the CLI test worker script.
     */
    public static function getWorkerScriptPath(): string
    {
        return base_path('scripts/diagnostics/cli_compat_worker.php');
    }

    /**
     * Check process functions and disable_functions ini setting.
     */
    public function checkProcessFunctions(): array
    {
        $disabledRaw = (string) ini_get('disable_functions');
        $disabledList = array_values(array_filter(array_map('trim', explode(',', strtolower($disabledRaw)))));

        return [
            'proc_open' => [
                'available' => function_exists('proc_open') && !in_array('proc_open', $disabledList, true),
                'disabled_in_ini' => in_array('proc_open', $disabledList, true),
            ],
            'exec' => [
                'available' => function_exists('exec') && !in_array('exec', $disabledList, true),
                'disabled_in_ini' => in_array('exec', $disabledList, true),
            ],
            'shell_exec' => [
                'available' => function_exists('shell_exec') && !in_array('shell_exec', $disabledList, true),
                'disabled_in_ini' => in_array('shell_exec', $disabledList, true),
            ],
            'disabled_functions_list' => $disabledList,
        ];
    }

    /**
     * Discover or configure the PHP CLI executable for the current environment.
     * Prioritizes Hostinger Linux paths when running on Linux.
     */
    public function discoverPhpCliBinary(): array
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $candidates = [];

        // 1. Explicit env configuration
        $envPath = env('PHP_CLI_PATH') ?: (config('app.php_cli_path') ?? null);
        if ($envPath) {
            $candidates['configured_env'] = (string) $envPath;
        }

        // 2. PHP_BINARY if valid CLI (exclude web servers / FastCGI)
        if (defined('PHP_BINARY') && PHP_BINARY) {
            $bin = PHP_BINARY;
            $base = strtolower(basename($bin));
            if (!str_contains($base, 'lsphp') && !str_contains($base, 'php-fpm') && !str_contains($base, 'httpd') && !str_contains($base, 'apache')) {
                $candidates['php_binary_const'] = $bin;
            }
        }

        // 3. Platform-specific known candidate paths
        if (!$isWindows) {
            $linuxCandidates = [
                '/usr/bin/php',
                '/usr/local/bin/php',
                '/opt/alt/php83/usr/bin/php',
                '/opt/alt/php82/usr/bin/php',
                '/opt/alt/php81/usr/bin/php',
                '/usr/bin/php8.3',
                '/usr/bin/php8.2',
                '/usr/bin/php-cli',
            ];
            foreach ($linuxCandidates as $path) {
                $candidates[$path] = $path;
            }
        } else {
            // Check WAMP and local Windows standard PHP CLI locations
            $wampPhp = 'C:\\wamp64\\bin\\php\\php' . PHP_VERSION . '\\php.exe';
            if (file_exists($wampPhp)) {
                $candidates['wamp_php'] = $wampPhp;
            }
            // Check glob for any wamp php
            foreach (glob('C:\\wamp64\\bin\\php\\php*\\php.exe') ?: [] as $wPath) {
                $candidates[basename(dirname($wPath))] = $wPath;
            }
            $candidates['system_path_php'] = 'php.exe';
        }

        $discovered = null;
        $checked = [];

        foreach ($candidates as $label => $path) {
            $exists = false;
            $executable = false;

            if ($isWindows) {
                $exists = file_exists($path) || file_exists($path . '.exe');
                $executable = $exists;
            } else {
                $exists = file_exists($path);
                $executable = $exists && is_executable($path);
            }

            $checked[] = [
                'candidate' => $path,
                'source' => $label,
                'exists' => $exists,
                'executable' => $executable,
            ];

            if ($executable && $discovered === null) {
                $discovered = $path;
            }
        }

        if ($discovered === null) {
            $discovered = $isWindows ? 'php.exe' : 'php';
        }

        return [
            'selected_binary' => $discovered,
            'is_configured' => !empty($envPath),
            'candidates_checked' => $checked,
        ];
    }

    /**
     * Test A: Synchronous process execution.
     *
     * @param string $phpBinary Discovered CLI executable
     * @param string $testId Unique alphanumeric test identifier
     * @return array
     */
    public function runSyncTest(string $phpBinary, string $testId): array
    {
        $workerScript = self::getWorkerScriptPath();
        if (!file_exists($workerScript)) {
            return [
                'started' => false,
                'error' => "Worker script not found at: {$workerScript}",
            ];
        }

        $funcs = $this->checkProcessFunctions();
        if (!$funcs['proc_open']['available'] && !$funcs['exec']['available']) {
            return [
                'started' => false,
                'error' => 'Both proc_open() and exec() are disabled in PHP configuration.',
            ];
        }

        $cmd = [$phpBinary, $workerScript, $testId, 'sync'];
        $cmdString = escapeshellcmd($phpBinary) . ' ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($testId) . ' sync';

        $startTime = microtime(true);
        $stdout = '';
        $stderr = '';
        $exitCode = -1;
        $timedOut = false;

        if ($funcs['proc_open']['available']) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            // Attempt array command first, fallback to string if needed
            $process = @proc_open($cmd, $descriptors, $pipes, base_path());
            if (!is_resource($process)) {
                $process = @proc_open($cmdString, $descriptors, $pipes, base_path());
            }

            if (!is_resource($process)) {
                // If proc_open still failed, attempt exec() fallback
                if ($funcs['exec']['available']) {
                    $outputLines = [];
                    @exec($cmdString . ' 2>&1', $outputLines, $exitCode);
                    $stdout = implode("\n", $outputLines);
                } else {
                    return [
                        'started' => false,
                        'error' => 'proc_open() returned false and exec() is unavailable.',
                    ];
                }
            } else {
                fclose($pipes[0]);
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);

                $timeoutSeconds = 8.0;
                $startLoop = microtime(true);

                while (true) {
                    $status = proc_get_status($process);
                    $stdout .= stream_get_contents($pipes[1]);
                    $stderr .= stream_get_contents($pipes[2]);

                    if (!$status['running']) {
                        break;
                    }

                    if ((microtime(true) - $startLoop) > $timeoutSeconds) {
                        proc_terminate($process, 9);
                        $timedOut = true;
                        break;
                    }

                    usleep(50000); // 50ms
                }

                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = $timedOut ? -1 : proc_close($process);
            }
        } else {
            // exec() path
            $outputLines = [];
            @exec($cmdString . ' 2>&1', $outputLines, $exitCode);
            $stdout = implode("\n", $outputLines);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        // Read the result file if generated
        $resultFile = self::getStorageDirectory() . DIRECTORY_SEPARATOR . $testId . '.json';
        $fileResult = null;
        if (file_exists($resultFile)) {
            $rawContent = file_get_contents($resultFile);
            $fileResult = json_decode((string) $rawContent, true);
        }

        return [
            'started' => true,
            'timed_out' => $timedOut,
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
            'stdout_sanitized' => $this->sanitizePath(trim($stdout)),
            'stderr_sanitized' => $this->sanitizePath(trim($stderr)),
            'worker_payload' => $fileResult,
            'cli_ffi_success' => $fileResult['core_ffi_test']['success'] ?? false,
            'jme_initialization_success' => $fileResult['jme_library_test']['success'] ?? false,
        ];
    }

    /**
     * Test B: Asynchronous process execution.
     * Spawns a detached background CLI worker and returns immediately.
     */
    public function launchAsyncTest(string $phpBinary, string $testId): array
    {
        $workerScript = self::getWorkerScriptPath();
        if (!file_exists($workerScript)) {
            return [
                'launched' => false,
                'error' => "Worker script not found at: {$workerScript}",
            ];
        }

        $funcs = $this->checkProcessFunctions();
        if (!$funcs['proc_open']['available'] && !$funcs['exec']['available']) {
            return [
                'launched' => false,
                'error' => 'Both proc_open() and exec() are disabled in PHP configuration.',
            ];
        }

        $isWindows = PHP_OS_FAMILY === 'Windows';
        $startTime = microtime(true);

        try {
            if ($isWindows) {
                // Windows detached background spawn
                $cmd = 'start /B "" ' . escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($testId) . ' async';
                pclose(popen($cmd, 'r'));
            } else {
                // Linux detached background spawn via nohup
                $cmd = 'nohup ' . escapeshellcmd($phpBinary) . ' ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($testId) . ' async > /dev/null 2>&1 &';
                if ($funcs['exec']['available']) {
                    @exec($cmd);
                } else {
                    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                    $proc = @proc_open($cmd, $descriptors, $pipes);
                    if (is_resource($proc)) {
                        fclose($pipes[0]);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($proc);
                    }
                }
            }

            return [
                'launched' => true,
                'test_id' => $testId,
                'launch_duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'result_endpoint' => url("/admin/diagnostics/cli-execution-compatibility/result/{$testId}"),
            ];
        } catch (Throwable $e) {
            return [
                'launched' => false,
                'error' => $this->sanitizePath($e->getMessage()),
            ];
        }
    }

    /**
     * Read the async test result by test identifier.
     */
    public function getAsyncResult(string $testId): array
    {
        $resultFile = self::getStorageDirectory() . DIRECTORY_SEPARATOR . $testId . '.json';

        if (!file_exists($resultFile)) {
            return [
                'test_id' => $testId,
                'found' => false,
                'status' => 'NOT_STARTED_OR_BLOCKED',
                'summary' => 'Result file not created. The background process may have failed to spawn or was immediately blocked.',
                'started_marker_observed' => false,
                'completed_marker_observed' => false,
                'decision' => 'A',
                'decision_label' => 'Web PHP cannot launch CLI process or execution failed',
            ];
        }

        $raw = file_get_contents($resultFile);
        $data = json_decode((string) $raw, true) ?: [];

        $status = $data['status'] ?? 'unknown';
        $startedObserved = ($status === 'started' || $status === 'completed');
        $completedObserved = ($status === 'completed');

        $ffiSuccess = $data['core_ffi_test']['success'] ?? false;
        $jmeSuccess = $data['jme_library_test']['success'] ?? false;

        // Determine decision criterion
        $decision = 'C';
        $decisionLabel = 'Background process launched but did not complete';

        if ($completedObserved && $ffiSuccess && $jmeSuccess) {
            $decision = 'D';
            $decisionLabel = 'Web PHP can launch a background CLI process that reliably survives the HTTP request and completes FFI/JME calculations';
        } elseif ($completedObserved && (!$ffiSuccess || !$jmeSuccess)) {
            $decision = 'B_PARTIAL';
            $decisionLabel = 'Process completed, but CLI FFI or JME library initialisation failed';
        } elseif ($status === 'started') {
            // Check file modification age
            $age = time() - filemtime($resultFile);
            if ($age < 6) {
                $decision = 'IN_PROGRESS';
                $decisionLabel = 'Process is currently executing in background (check again in 2-3 seconds)';
            } else {
                $decision = 'C';
                $decisionLabel = 'Web PHP can launch a background CLI process, but it was terminated when the HTTP request ended (started marker observed, completed marker never written)';
            }
        }

        return [
            'test_id' => $testId,
            'found' => true,
            'status' => $status,
            'started_marker_observed' => $startedObserved,
            'completed_marker_observed' => $completedObserved,
            'execution_duration_ms' => $data['duration_ms'] ?? null,
            'started_at' => $data['started_at'] ?? null,
            'completed_at' => $data['completed_at'] ?? null,
            'cli_pid' => $data['pid'] ?? null,
            'cli_php_version' => $data['php_version'] ?? null,
            'cli_php_sapi' => $data['php_sapi'] ?? null,
            'cli_ffi_result' => $data['core_ffi_test'] ?? null,
            'jme_initialization_result' => $data['jme_library_test'] ?? null,
            'decision' => $decision,
            'decision_label' => $decisionLabel,
        ];
    }

    /**
     * Evaluate overall combined decision across sync and async runs.
     */
    public function evaluateDecision(array $env, ?array $syncResult, ?array $asyncLaunch): array
    {
        $syncStarted = $syncResult['started'] ?? false;
        $syncFfi = $syncResult['cli_ffi_success'] ?? false;
        $syncJme = $syncResult['jme_initialization_success'] ?? false;

        if (!$syncStarted && empty($asyncLaunch['launched'])) {
            return [
                'decision' => 'A',
                'label' => 'Web PHP cannot launch any CLI process',
                'summary' => 'Process launching is blocked by PHP configuration (e.g. disable_functions) or executable access restrictions.',
            ];
        }

        if ($syncStarted && $syncFfi && $syncJme) {
            return [
                'decision' => 'B',
                'label' => 'Web PHP can launch CLI synchronously, and CLI FFI/JME work',
                'summary' => 'Synchronous CLI execution works and FFI/JME are functional. To establish detached background survivability (Criterion D), verify the async result endpoint.',
            ];
        }

        return [
            'decision' => 'A_OR_B_FAIL',
            'label' => 'CLI process executed but FFI/JME failed',
            'summary' => 'CLI process was started, but either harmless FFI or JME library initialisation encountered an error.',
        ];
    }

    /**
     * Sanitize server filesystem paths from diagnostic output.
     */
    public function sanitizePath(string $message): string
    {
        if (function_exists('base_path')) {
            $base = base_path();
            $message = str_replace([$base, str_replace('/', '\\', $base), str_replace('\\', '/', $base)], '[APP_ROOT]', $message);
        }
        if (function_exists('storage_path')) {
            $storage = storage_path();
            $message = str_replace([$storage, str_replace('/', '\\', $storage), str_replace('\\', '/', $storage)], '[STORAGE_ROOT]', $message);
        }

        $message = preg_replace('#/home/[^/\s"\']+(?:/[^:\s"\']*)?#', '[REDACTED_PATH]', $message);
        $message = preg_replace('#[A-Za-z]:\\\\[^:\s"\']*#', '[REDACTED_PATH]', $message);

        return $message;
    }
}
