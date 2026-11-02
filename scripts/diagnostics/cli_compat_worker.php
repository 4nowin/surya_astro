<?php

declare(strict_types=1);

/**
 * Astro Setu — Fixed Production-Safe CLI Compatibility Test Worker.
 *
 * Invoked strictly via PHP CLI:
 *   php scripts/diagnostics/cli_compat_worker.php <test_id> <mode>
 *
 * Arguments:
 *   $argv[1] = test_id (alphanumeric string, 10-64 chars)
 *   $argv[2] = mode ('sync' or 'async')
 */

// Verify CLI execution
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Error: This worker script must be executed via PHP CLI.\n");
    exit(1);
}

// Validate arguments
if ($argc < 3) {
    fwrite(STDERR, "Usage: php cli_compat_worker.php <test_id> <sync|async>\n");
    exit(1);
}

$testId = trim($argv[1]);
$mode = strtolower(trim($argv[2]));

if (!preg_match('/^[a-zA-Z0-9_-]{10,64}$/', $testId)) {
    fwrite(STDERR, "Error: Invalid test_id format.\n");
    exit(1);
}

if (!in_array($mode, ['sync', 'async'], true)) {
    fwrite(STDERR, "Error: Invalid mode. Must be 'sync' or 'async'.\n");
    exit(1);
}

$startTime = microtime(true);

// Bootstrap Laravel
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
$bootstrapPath = __DIR__ . '/../../bootstrap/app.php';

if (!file_exists($autoloadPath) || !file_exists($bootstrapPath)) {
    fwrite(STDERR, "Error: Laravel bootstrap or autoloader not found.\n");
    exit(1);
}

require $autoloadPath;
$app = require_once $bootstrapPath;
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Ensure dedicated output directory
$storageDir = storage_path('app/diagnostics/cli_tests');
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0755, true);
}

$outputFile = $storageDir . DIRECTORY_SEPARATOR . $testId . '.json';

// For async mode: write started marker immediately and sleep briefly to test request detachment
if ($mode === 'async') {
    $startedMarker = [
        'test_id' => $testId,
        'mode' => 'async',
        'status' => 'started',
        'pid' => getmypid(),
        'started_at' => now()->toIso8601String(),
        'php_version' => PHP_VERSION,
        'php_sapi' => php_sapi_name(),
    ];
    file_put_contents($outputFile, json_encode($startedMarker, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Sleep 2 seconds to guarantee the originating HTTP request has fully concluded
    sleep(2);
}

// Test 1: FFI Extension Availability
$ffiLoaded = extension_loaded('ffi');
$ffiClass = class_exists('FFI');
$ffiEnable = (string) ini_get('ffi.enable');

// Test 2: Harmless C FFI Execution
$coreFfiSuccess = false;
$coreFfiValue = null;
$coreFfiError = null;

if ($ffiLoaded && $ffiClass) {
    try {
        $isWin = PHP_OS_FAMILY === 'Windows';
        $cLib = $isWin ? 'msvcrt.dll' : 'libc.so.6';

        try {
            $ffi = \FFI::cdef("int abs(int value);", $cLib);
        } catch (\Throwable $eFallback) {
            $ffi = \FFI::cdef("int abs(int value);");
        }

        $coreFfiValue = $ffi->abs(-42);
        $coreFfiSuccess = ($coreFfiValue === 42);
    } catch (\Throwable $e) {
        $coreFfiError = get_class($e) . ': ' . $e->getMessage();
    }
} else {
    $coreFfiError = 'FFI extension is not loaded in CLI PHP.';
}

// Test 3: Project JME Library Initialization
$jmeSuccess = false;
$jmeConfigured = null;
$jmeCalculation = null;
$jmeError = null;

try {
    if (class_exists(\App\Services\Astrology\NativeEphemerisResolver::class)) {
        $libPath = \App\Services\Astrology\NativeEphemerisResolver::resolve();
        $jmeConfigured = basename($libPath);

        if (class_exists(\JmeEph\FFI\JmeEphFFI::class)) {
            $jme = new \JmeEph\FFI\JmeEphFFI($libPath);
            // Pure C mathematical calculation in Moshier ephemeris
            // JD 2460000.5 corresponds to 2023-02-25 (Saturday = day 6)
            $dow = $jme->jme_day_of_week(2460000.5);
            $jmeCalculation = $dow;
            $jmeSuccess = ($dow === 6);
        } else {
            $jmeError = 'Class JmeEph\FFI\JmeEphFFI not found.';
        }
    } else {
        $jmeError = 'Class NativeEphemerisResolver not found.';
    }
} catch (\Throwable $e) {
    $jmeError = get_class($e) . ': ' . $e->getMessage();
}

$durationMs = round((microtime(true) - $startTime) * 1000, 2);

$completedPayload = [
    'test_id' => $testId,
    'mode' => $mode,
    'status' => 'completed',
    'pid' => getmypid(),
    'started_at' => $startedMarker['started_at'] ?? now()->toIso8601String(),
    'completed_at' => now()->toIso8601String(),
    'duration_ms' => $durationMs,
    'php_version' => PHP_VERSION,
    'php_sapi' => php_sapi_name(),
    'ffi_extension_loaded' => $ffiLoaded,
    'ffi_enable_directive' => $ffiEnable === '' ? '(empty)' : $ffiEnable,
    'core_ffi_test' => [
        'success' => $coreFfiSuccess,
        'return_value' => $coreFfiValue,
        'error' => $coreFfiError,
    ],
    'jme_library_test' => [
        'success' => $jmeSuccess,
        'library' => $jmeConfigured,
        'sample_calculation' => $jmeCalculation,
        'error' => $jmeError,
    ],
];

// Write result file
file_put_contents($outputFile, json_encode($completedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Output summary for synchronous caller
echo json_encode([
    'test_id' => $testId,
    'mode' => $mode,
    'status' => 'completed',
    'duration_ms' => $durationMs,
    'core_ffi' => $coreFfiSuccess ? 'PASS' : 'FAIL',
    'jme' => $jmeSuccess ? 'PASS' : 'FAIL',
], JSON_UNESCAPED_SLASHES) . "\n";

exit($coreFfiSuccess && $jmeSuccess ? 0 : 2);
