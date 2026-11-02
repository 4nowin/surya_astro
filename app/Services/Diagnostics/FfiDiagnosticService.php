<?php

namespace App\Services\Diagnostics;

use Throwable;

class FfiDiagnosticService
{
    /**
     * Run all diagnostic checks.
     *
     * @return array
     */
    public function runDiagnostics(): array
    {
        $env = $this->checkPhpEnvironment();
        $ffiCore = $this->testFfiCore();
        $nativeLib = $this->testNativeLibraryLoading();
        $fs = $this->checkFilesystemAndSecurity();
        $candidates = $this->evaluateAstrologyCandidates($env, $ffiCore, $nativeLib);
        $verdict = $this->generateVerdict($env, $ffiCore, $nativeLib);
        $panchangCache = $this->getPanchangCacheDiagnostics();

        return [
            'timestamp' => now()->toIso8601String(),
            'context' => php_sapi_name() === 'cli' ? 'CLI Runtime' : 'Web Runtime (HTTP)',
            'php_environment' => $env,
            'ffi_core_test' => $ffiCore,
            'native_library_test' => $nativeLib,
            'filesystem_and_security' => $fs,
            'astrology_candidates' => $candidates,
            'panchang_cache' => $panchangCache,
            'overall_verdict' => $verdict,
        ];
    }

    /**
     * Test Group A: PHP Environment
     */
    public function checkPhpEnvironment(): array
    {
        $ffiLoaded = extension_loaded('FFI');
        $ffiClassExists = class_exists('FFI');
        $ffiEnableRaw = ini_get('ffi.enable');

        // Normalize ffi.enable
        $ffiEnableDesc = 'disabled';
        $ffiEnableStatus = 'FAIL';

        if ($ffiEnableRaw === '1' || strtolower((string)$ffiEnableRaw) === 'true') {
            $ffiEnableDesc = 'true (Fully enabled for Web & CLI)';
            $ffiEnableStatus = 'PASS';
        } elseif (strtolower((string)$ffiEnableRaw) === 'preload') {
            if (php_sapi_name() === 'cli') {
                $ffiEnableDesc = 'preload (Enabled for CLI scripts and preloaded files only)';
                $ffiEnableStatus = 'PASS';
            } else {
                $ffiEnableDesc = 'preload (BLOCKED for Web HTTP requests; requires ffi.enable=true)';
                $ffiEnableStatus = 'FAIL';
            }
        } elseif ($ffiEnableRaw === '0' || strtolower((string)$ffiEnableRaw) === 'false' || empty($ffiEnableRaw)) {
            $ffiEnableDesc = 'false / 0 (Disabled)';
            $ffiEnableStatus = 'FAIL';
        } else {
            $ffiEnableDesc = "Custom/Unknown: {$ffiEnableRaw}";
            $ffiEnableStatus = 'WARNING';
        }

        $disabledFuncsRaw = (string)ini_get('disable_functions');
        $disabledFuncsList = array_filter(array_map('trim', explode(',', $disabledFuncsRaw)));

        $processFunctions = ['exec', 'shell_exec', 'proc_open', 'system', 'passthru', 'popen'];
        $disabledProcessFuncs = array_intersect($processFunctions, $disabledFuncsList);

        return [
            'php_version' => [
                'label' => 'PHP Version',
                'value' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '8.3.0', '>=') ? 'PASS' : 'WARNING',
                'detail' => 'Laravel 13 and modern FFI engines require PHP 8.3+.'
            ],
            'php_sapi' => [
                'label' => 'PHP SAPI',
                'value' => php_sapi_name(),
                'status' => 'INFO',
                'detail' => php_sapi_name() === 'cli' ? 'CLI environment (Console commands)' : 'Web server interface (FPM / LiteSpeed / Apache)'
            ],
            'os' => [
                'label' => 'Operating System',
                'value' => PHP_OS . ' (' . PHP_OS_FAMILY . ') ' . php_uname('m'),
                'status' => 'INFO',
                'detail' => 'Determines whether .so (Linux) or .dll (Windows) libraries are required.'
            ],
            'architecture' => [
                'label' => 'Architecture',
                'value' => PHP_INT_SIZE === 8 ? '64-bit' : '32-bit',
                'status' => PHP_INT_SIZE === 8 ? 'PASS' : 'WARNING',
                'detail' => 'Calculations and modern ephemerides require 64-bit systems.'
            ],
            'ffi_extension_loaded' => [
                'label' => "extension_loaded('FFI')",
                'value' => $ffiLoaded ? 'Loaded' : 'Not Loaded',
                'status' => $ffiLoaded ? 'PASS' : 'FAIL',
                'detail' => $ffiLoaded
                    ? 'The FFI extension module is compiled and available in PHP.'
                    : 'The FFI extension is not loaded in this PHP runtime.'
            ],
            'ffi_class_exists' => [
                'label' => "class_exists('FFI')",
                'value' => $ffiClassExists ? 'Yes' : 'No',
                'status' => $ffiClassExists ? 'PASS' : 'FAIL',
                'detail' => $ffiClassExists
                    ? 'The global \\FFI class is defined and ready.'
                    : 'The \\FFI class cannot be instantiated.'
            ],
            'ffi_enable_ini' => [
                'label' => "ini_get('ffi.enable')",
                'value' => $ffiEnableRaw === '' ? '(empty / default)' : (string)$ffiEnableRaw,
                'status' => $ffiEnableStatus,
                'detail' => $ffiEnableDesc
            ],
            'memory_limit' => [
                'label' => 'Memory Limit',
                'value' => ini_get('memory_limit') ?: 'N/A',
                'status' => 'INFO',
                'detail' => 'Memory allocation cap for this request.'
            ],
            'max_execution_time' => [
                'label' => 'Max Execution Time',
                'value' => (ini_get('max_execution_time') ?: '0') . 's',
                'status' => 'INFO',
                'detail' => 'Script execution timeout for this request.'
            ],
            'disabled_process_functions' => [
                'label' => 'Process Execution Functions',
                'value' => empty($disabledProcessFuncs) ? 'None disabled' : implode(', ', $disabledProcessFuncs),
                'status' => empty($disabledProcessFuncs) ? 'PASS' : 'WARNING',
                'detail' => empty($disabledProcessFuncs)
                    ? 'Process spawning functions are available.'
                    : 'Process execution functions are disabled. CLI wrappers (e.g. swetest) cannot run via shell.'
            ],
        ];
    }

    /**
     * Test Group B: Standard C Library FFI Functionality (abs(-42) -> 42)
     */
    public function testFfiCore(): array
    {
        $result = [
            'name' => 'Standard C Library FFI Test (abs(-42))',
            'status' => 'FAIL',
            'expected_value' => 42,
            'actual_value' => null,
            'library_used' => null,
            'error_message' => null,
            'analysis' => '',
        ];

        // 1. Guard check: class existence
        if (!class_exists('FFI')) {
            $result['status'] = 'FAIL';
            $result['error_message'] = 'Class "FFI" does not exist in this PHP runtime.';
            $result['analysis'] = 'FFI extension is not enabled or compiled in this PHP version.';
            return $result;
        }

        // 2. Guard check: ffi.enable setting
        $ffiEnable = strtolower((string)ini_get('ffi.enable'));
        if (php_sapi_name() !== 'cli' && $ffiEnable !== '1' && $ffiEnable !== 'true') {
            $result['status'] = 'BLOCKED';
            $result['error_message'] = "FFI API is restricted by php.ini directive ffi.enable='{$ffiEnable}'.";
            $result['analysis'] = 'On shared hosting, ffi.enable is set to "preload" or "false" for web requests. Even though the extension is installed, calling FFI::cdef() from Web PHP is actively blocked by the engine.';
            return $result;
        }

        // 3. Define platform-specific candidate library paths for libc
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $candidates = [];

        if ($isWindows) {
            $candidates = [
                'msvcrt.dll' => 'msvcrt.dll',
                'ucrtbase.dll' => 'ucrtbase.dll',
                'null (Process Space)' => null,
            ];
        } else {
            // Linux / Unix libc paths
            $candidates = [
                'libc.so.6' => 'libc.so.6',
                '/lib/x86_64-linux-gnu/libc.so.6' => '/lib/x86_64-linux-gnu/libc.so.6',
                '/lib64/libc.so.6' => '/lib64/libc.so.6',
                '/usr/lib64/libc.so.6' => '/usr/lib64/libc.so.6',
                '/usr/lib/libc.so.6' => '/usr/lib/libc.so.6',
                'null (Global Process Space)' => null,
            ];
        }

        $cdefHeader = "int abs(int value);";
        $errors = [];

        foreach ($candidates as $label => $libPath) {
            try {
                // If library path is a string and not null, we can check or pass directly
                $ffi = $libPath === null
                    ? \FFI::cdef($cdefHeader)
                    : \FFI::cdef($cdefHeader, $libPath);

                $computed = $ffi->abs(-42);

                if ($computed === 42) {
                    $result['status'] = 'PASS';
                    $result['actual_value'] = 42;
                    $result['library_used'] = $label;
                    $result['error_message'] = null;
                    $result['analysis'] = "SUCCESS: PHP FFI successfully loaded '{$label}' and called abs(-42), returning 42.";
                    return $result;
                } else {
                    $errors[$label] = "Returned unexpected value: " . var_export($computed, true);
                }
            } catch (Throwable $e) {
                $errors[$label] = $e->getMessage();
            }
        }

        // If we reached here, none of the candidates worked
        $result['status'] = 'FAIL';
        $result['error_message'] = 'All candidate libc bindings failed: ' . json_encode($errors);
        $result['analysis'] = 'FFI is enabled, but the system C library could not be linked or executed. See specific error breakdown.';

        return $result;
    }

    /**
     * Test Group C: Application-Controlled Native Library Loading
     */
    public function testNativeLibraryLoading(): array
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $targetFileName = $isWindows ? 'libastrotest.dll' : 'libastrotest.so';
        $targetDir = storage_path('app/native');
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $targetFileName;

        $result = [
            'name' => "Custom Shared Library Test ({$targetFileName})",
            'target_file' => $targetPath,
            'file_exists' => file_exists($targetPath),
            'is_readable' => is_readable($targetPath),
            'filesize' => file_exists($targetPath) ? filesize($targetPath) : 0,
            'status' => 'NOT_TESTED',
            'expected_value' => 42,
            'actual_value' => null,
            'error_message' => null,
            'analysis' => '',
            'build_instruction' => $isWindows
                ? 'Compile on Windows with MinGW: gcc -O2 -shared -o libastrotest.dll test_lib.c'
                : 'Compile on Linux/Hostinger SSH: gcc -O2 -fPIC -shared -o libastrotest.so test_lib.c'
        ];

        // 1. Check directory existence
        if (!is_dir($targetDir)) {
            $result['status'] = 'FAIL';
            $result['error_message'] = "Target directory does not exist: {$targetDir}";
            $result['analysis'] = 'Directory storage/app/native must be created and readable.';
            return $result;
        }

        // 2. Check if library binary file is present
        if (!file_exists($targetPath)) {
            $result['status'] = 'NOT_TESTED';
            $result['error_message'] = "Shared library file '{$targetFileName}' not found in storage/app/native.";
            $result['analysis'] = "File not uploaded yet. Deploy {$targetFileName} to storage/app/native to run this test.";
            return $result;
        }

        // 3. Check readability
        if (!is_readable($targetPath)) {
            $result['status'] = 'FAIL';
            $result['error_message'] = "File exists but is not readable by the PHP process: {$targetPath}";
            $result['analysis'] = "Check filesystem permissions (e.g. chmod 644 {$targetFileName}).";
            return $result;
        }

        // 4. Guard check on FFI
        if (!class_exists('FFI')) {
            $result['status'] = 'FAIL';
            $result['error_message'] = 'FFI class does not exist. Cannot test native library loading.';
            return $result;
        }

        $ffiEnable = strtolower((string)ini_get('ffi.enable'));
        if (php_sapi_name() !== 'cli' && $ffiEnable !== '1' && $ffiEnable !== 'true') {
            $result['status'] = 'BLOCKED';
            $result['error_message'] = "FFI is blocked by ffi.enable='{$ffiEnable}' for web requests.";
            $result['analysis'] = 'Cannot load custom shared library because FFI is restricted by PHP configuration for this SAPI.';
            return $result;
        }

        // 5. Attempt loading the custom library via FFI
        try {
            $cdefHeader = "
                int astro_test_engine(int val);
                int astro_get_version(void);
            ";

            $ffi = \FFI::cdef($cdefHeader, $targetPath);
            $computed = $ffi->astro_test_engine(21);
            $version = $ffi->astro_get_version();

            if ($computed === 42 && $version === 100) {
                $result['status'] = 'PASS';
                $result['actual_value'] = 42;
                $result['error_message'] = null;
                $result['analysis'] = "SUCCESS: Hostinger / PHP successfully loaded custom shared library {$targetFileName} from application storage and executed astro_test_engine(21), returning 42.";
                return $result;
            } else {
                $result['status'] = 'FAIL';
                $result['actual_value'] = $computed;
                $result['error_message'] = "Function returned unexpected value: {$computed} (expected 42)";
                $result['analysis'] = 'Library was loaded but returned unexpected values.';
                return $result;
            }
        } catch (Throwable $e) {
            $result['status'] = 'FAIL';
            $result['error_message'] = $e->getMessage();
            $result['analysis'] = 'FFI attempted to load the shared library but failed. Common causes: missing dynamic linkers, ELF architecture mismatch (e.g. 32-bit vs 64-bit), or OS security policies restricting mmap/exec of user files.';
            return $result;
        }
    }

    /**
     * Test Group D: Filesystem and Security Diagnostics
     */
    public function checkFilesystemAndSecurity(): array
    {
        $storageDir = storage_path();
        $nativeDir = storage_path('app/native');
        $tempDir = sys_get_temp_dir();

        return [
            'storage_directory' => [
                'label' => 'Laravel Storage Directory',
                'path' => $storageDir,
                'is_readable' => is_readable($storageDir),
                'is_writable' => is_writable($storageDir),
                'status' => (is_readable($storageDir) && is_writable($storageDir)) ? 'PASS' : 'FAIL',
            ],
            'native_library_directory' => [
                'label' => 'Native Library Directory (storage/app/native)',
                'path' => $nativeDir,
                'exists' => is_dir($nativeDir),
                'is_readable' => is_dir($nativeDir) && is_readable($nativeDir),
                'is_writable' => is_dir($nativeDir) && is_writable($nativeDir),
                'status' => (is_dir($nativeDir) && is_readable($nativeDir)) ? 'PASS' : 'WARNING',
            ],
            'system_temp_directory' => [
                'label' => 'System Temporary Directory',
                'path' => $tempDir,
                'is_readable' => is_readable($tempDir),
                'is_writable' => is_writable($tempDir),
                'status' => (is_readable($tempDir) && is_writable($tempDir)) ? 'PASS' : 'FAIL',
            ],
        ];
    }

    /**
     * Candidate Feasibility Evaluation based on diagnostic results
     */
    public function evaluateAstrologyCandidates(array $env, array $ffiCore, array $nativeLib): array
    {
        $ffiWorking = ($ffiCore['status'] === 'PASS');
        $customLibWorking = ($nativeLib['status'] === 'PASS');

        return [
            'panchang_core' => [
                'name' => 'jayeshmepani/panchang-core',
                'required_extensions' => ['FFI (ext-ffi)'],
                'native_binaries' => ['libjme', 'libcalceph'],
                'ffi_mandatory' => true,
                'needs_server_compiler' => false,
                'loads_precompiled_so' => true,
                'commercial_licensing' => 'MIT License (Permissive, safe for commercial use)',
                'feasibility_status' => $ffiWorking ? 'VIABLE ON THIS RUNTIME' : 'BLOCKED',
                'reason' => $ffiWorking
                    ? 'FFI is operational in this runtime. Panchang Core can theoretically run.'
                    : 'FFI is blocked or unavailable in this runtime. Panchang Core cannot execute.'
            ],
            'jpl_moshier_ephemeris' => [
                'name' => 'jayeshmepani/jpl-moshier-ephemeris-php',
                'required_extensions' => ['FFI (ext-ffi)'],
                'native_binaries' => ['libjme', 'libcalceph'],
                'ffi_mandatory' => true,
                'needs_server_compiler' => false,
                'loads_precompiled_so' => true,
                'commercial_licensing' => 'MIT / CeCILL-B (Permissive for commercial use with attribution)',
                'feasibility_status' => $ffiWorking ? 'VIABLE ON THIS RUNTIME' : 'BLOCKED',
                'reason' => $ffiWorking
                    ? 'FFI bindings can link to bundled C ephemeris libraries.'
                    : 'Requires FFI. Will crash if ffi.enable is not active.'
            ],
            'libjme' => [
                'name' => 'libjme (Steve Moshier Ephemeris C Library)',
                'required_extensions' => ['FFI or C PHP extension'],
                'native_binaries' => ['libjme.so'],
                'ffi_mandatory' => true,
                'needs_server_compiler' => false,
                'loads_precompiled_so' => true,
                'commercial_licensing' => 'Steve Moshier Copyright (Permissive attribution; free commercial use)',
                'feasibility_status' => $customLibWorking ? 'VIABLE' : ($ffiWorking ? 'LIKELY VIABLE' : 'BLOCKED'),
                'reason' => 'Requires loading precompiled dynamic library into PHP.'
            ],
            'libcalceph' => [
                'name' => 'libcalceph (IMCCE / Paris Observatory Ephemeris Reader)',
                'required_extensions' => ['FFI'],
                'native_binaries' => ['libcalceph.so', 'JPL .bsp kernels (DE440/441)'],
                'ffi_mandatory' => true,
                'needs_server_compiler' => false,
                'loads_precompiled_so' => true,
                'commercial_licensing' => 'CeCILL-B (BSD/MIT equivalent; requires copyright attribution in docs)',
                'feasibility_status' => $customLibWorking ? 'VIABLE' : ($ffiWorking ? 'LIKELY VIABLE' : 'BLOCKED'),
                'reason' => 'Reads binary SPK planetary kernels via C FFI functions.'
            ],
        ];
    }

    /**
     * Generate concise overall verdict
     */
    public function generateVerdict(array $env, array $ffiCore, array $nativeLib): array
    {
        $isCli = php_sapi_name() === 'cli';
        $ffiPassed = ($ffiCore['status'] === 'PASS');
        $customPassed = ($nativeLib['status'] === 'PASS');

        if ($ffiPassed && $customPassed) {
            return [
                'level' => 'SUCCESS',
                'title' => 'FFI and Custom Native Libraries are Fully Operational',
                'summary' => "This PHP runtime ({$env['php_sapi']['value']}) supports both standard C library calls and loading application-provided shared libraries. FFI-based packages like panchang-core and jpl-moshier-ephemeris-php are technically viable here.",
                'next_step' => 'Check the companion runtime (Web if this was CLI, or CLI if this was Web) to verify parity.'
            ];
        } elseif ($ffiPassed && !$customPassed) {
            return [
                'level' => 'PARTIAL_SUCCESS',
                'title' => 'Core FFI Works, Custom Library Pending Verification',
                'summary' => "Standard C FFI calls succeed! However, the custom shared library test is either not yet uploaded or encountered an issue. Once libastrotest.so is placed in storage/app/native, custom library loading can be validated.",
                'next_step' => 'Upload or compile libastrotest.so in storage/app/native and re-run.'
            ];
        } else {
            return [
                'level' => 'BLOCKED',
                'title' => 'FFI Execution is Blocked on this PHP Runtime',
                'summary' => "Even if the FFI extension is enabled in the hosting dashboard, the current runtime ({$env['php_sapi']['value']}) cannot execute FFI calls. " . ($ffiCore['error_message'] ?? 'Unknown restriction.'),
                'next_step' => $isCli
                    ? 'Check php.ini for CLI. If Web FFI works but CLI fails, verify different php.ini paths.'
                    : 'Hostinger web runtime likely enforces ffi.enable=preload. You must either configure ffi.enable=true in web php.ini (if allowed) or adopt the Pure-PHP engine architecture.'
            ];
        }
    }

    /**
     * Inspect Panchanga deterministic cache configuration and status.
     */
    public function getPanchangCacheDiagnostics(): array
    {
        $driver = config('cache.default', 'file');
        $canonicalKeyOk = class_exists(\App\Services\Astrology\CanonicalPanchangCacheKey::class);
        $sampleKey = '';
        if ($canonicalKeyOk) {
            $attrs = \App\Services\Astrology\CanonicalPanchangCacheKey::canonicalAttributes(
                '2026-11-02', 28.6139, 77.2090, 'Asia/Kolkata'
            );
            $sampleKey = 'panchang:daily:' . \App\Services\Astrology\CanonicalPanchangCacheKey::generateHash($attrs);
        }

        return [
            'cache_driver' => [
                'label' => 'Cache Driver',
                'value' => (string) $driver,
                'status' => 'PASS',
                'detail' => 'Underlying storage engine for deterministic astronomical caching.',
            ],
            'canonical_key_generator' => [
                'label' => 'Canonical Panchang Cache Identity',
                'value' => $canonicalKeyOk ? 'Active (PAN_RULES_V1)' : 'Missing',
                'status' => $canonicalKeyOk ? 'PASS' : 'FAIL',
                'detail' => $sampleKey ? "Deterministic key pattern: {$sampleKey}" : 'Key generator unavailable.',
            ],
        ];
    }

    /**
     * Format report as clean plain-text
     */
    public function formatAsText(array $diagnostics): string
    {
        $out = [];
        $out[] = "========================================================";
        $out[] = "ASTRO SETU — PHP FFI & ENGINE DIAGNOSTIC REPORT";
        $out[] = "========================================================";
        $out[] = "Timestamp : " . $diagnostics['timestamp'];
        $out[] = "Context   : " . $diagnostics['context'];
        $out[] = "Overall   : " . $diagnostics['overall_verdict']['title'];
        $out[] = "Summary   : " . $diagnostics['overall_verdict']['summary'];
        $out[] = "";

        $out[] = "--------------------------------------------------------";
        $out[] = "1. PHP ENVIRONMENT";
        $out[] = "--------------------------------------------------------";
        foreach ($diagnostics['php_environment'] as $key => $item) {
            $status = str_pad("[{$item['status']}]", 10);
            $out[] = "{$status} {$item['label']}: {$item['value']}";
            if (!empty($item['detail'])) {
                $out[] = "           Detail: {$item['detail']}";
            }
        }
        $out[] = "";

        $out[] = "--------------------------------------------------------";
        $out[] = "2. FFI CORE FUNCTIONALITY TEST";
        $out[] = "--------------------------------------------------------";
        $c = $diagnostics['ffi_core_test'];
        $out[] = "Test Name       : " . $c['name'];
        $out[] = "Status          : [" . $c['status'] . "]";
        $out[] = "Expected Value  : " . $c['expected_value'];
        $out[] = "Actual Value    : " . var_export($c['actual_value'], true);
        $out[] = "Library Used    : " . ($c['library_used'] ?: 'None');
        if ($c['error_message']) {
            $out[] = "Error Message   : " . $c['error_message'];
        }
        $out[] = "Analysis        : " . $c['analysis'];
        $out[] = "";

        $out[] = "--------------------------------------------------------";
        $out[] = "3. NATIVE SHARED LIBRARY LOADING TEST";
        $out[] = "--------------------------------------------------------";
        $n = $diagnostics['native_library_test'];
        $out[] = "Test Name       : " . $n['name'];
        $out[] = "Status          : [" . $n['status'] . "]";
        $out[] = "Target File     : " . $n['target_file'];
        $out[] = "File Exists     : " . ($n['file_exists'] ? 'YES' : 'NO');
        $out[] = "Is Readable     : " . ($n['is_readable'] ? 'YES' : 'NO');
        $out[] = "File Size       : " . $n['filesize'] . " bytes";
        $out[] = "Expected Value  : " . $n['expected_value'];
        $out[] = "Actual Value    : " . var_export($n['actual_value'], true);
        if ($n['error_message']) {
            $out[] = "Error Message   : " . $n['error_message'];
        }
        $out[] = "Analysis        : " . $n['analysis'];
        $out[] = "Build Command   : " . $n['build_instruction'];
        $out[] = "";

        $out[] = "--------------------------------------------------------";
        $out[] = "4. FILESYSTEM & PROCESS RESTRICTIONS";
        $out[] = "--------------------------------------------------------";
        foreach ($diagnostics['filesystem_and_security'] as $item) {
            $out[] = $item['label'] . ":";
            $out[] = "  Path        : " . $item['path'];
            $out[] = "  Readable    : " . ($item['is_readable'] ? 'YES' : 'NO');
            if (isset($item['is_writable'])) {
                $out[] = "  Writable    : " . ($item['is_writable'] ? 'YES' : 'NO');
            }
            $out[] = "  Status      : [" . $item['status'] . "]";
        }
        $out[] = "";

        $out[] = "--------------------------------------------------------";
        $out[] = "5. ASTROLOGY PACKAGES FEASIBILITY";
        $out[] = "--------------------------------------------------------";
        foreach ($diagnostics['astrology_candidates'] as $pkg) {
            $out[] = "Package     : " . $pkg['name'];
            $out[] = "Status      : [" . $pkg['feasibility_status'] . "]";
            $out[] = "Licensing   : " . $pkg['commercial_licensing'];
            $out[] = "Assessment  : " . $pkg['reason'];
            $out[] = "";
        }

        $out[] = "========================================================";
        $out[] = "END OF REPORT";
        $out[] = "========================================================";

        return implode("\n", $out);
    }
}
