<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Diagnostics\CliExecutionCompatibilityService;
use App\Services\Diagnostics\FfiDiagnosticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class AstrologyDiagnosticController extends Controller
{
    protected FfiDiagnosticService $diagnosticService;
    protected CliExecutionCompatibilityService $cliService;

    public function __construct(
        FfiDiagnosticService $diagnosticService,
        CliExecutionCompatibilityService $cliService
    ) {
        $this->middleware('auth:admin')->except([
            'ffiCompatibilityTest',
            'cliExecutionCompatibility',
            'cliExecutionCompatibilityResult',
        ]);
        $this->diagnosticService = $diagnosticService;
        $this->cliService = $cliService;
    }

    /**
     * Display the diagnostic dashboard.
     */
    public function index()
    {
        $diagnostics = $this->diagnosticService->runDiagnostics();

        return view('admin.diagnostics.astrology_engine', [
            'diagnostics' => $diagnostics,
            'jsonReport' => json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'textReport' => $this->diagnosticService->formatAsText($diagnostics),
        ]);
    }

    /**
     * Download or export the diagnostic report without exposing secrets.
     */
    public function export(Request $request)
    {
        $format = $request->query('format', 'json');
        $diagnostics = $this->diagnosticService->runDiagnostics();
        $timestamp = date('Ymd_His');

        if ($format === 'txt') {
            $content = $this->diagnosticService->formatAsText($diagnostics);
            $filename = "astro_setu_ffi_diagnostics_{$timestamp}.txt";

            return response($content, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        $content = json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filename = "astro_setu_ffi_diagnostics_{$timestamp}.json";

        return response($content, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Dedicated protected endpoint for Hostinger Web Runtime FFI Compatibility Test.
     * Accessible by:
     * 1. Authenticated admin session (auth:admin), OR
     * 2. Secret query parameter ?key=... or header X-Diagnostic-Key.
     */
    public function ffiCompatibilityTest(Request $request): JsonResponse
    {
        if (!$this->isDiagnosticAuthorized($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized diagnostic access. Admin session or valid diagnostic key required.',
            ], 403);
        }

        $result = $this->diagnosticService->testHostingerFfiCompatibility();

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Dedicated protected endpoint for Hostinger LiteSpeed CLI Execution Compatibility Test.
     * GET /admin/diagnostics/cli-execution-compatibility?mode=all|sync|async
     */
    public function cliExecutionCompatibility(Request $request): JsonResponse
    {
        if (!$this->isDiagnosticAuthorized($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized diagnostic access. Admin session or valid DIAGNOSTIC_KEY required.',
            ], 403);
        }

        // Concurrency lock to prevent abuse (5 seconds)
        $lock = Cache::lock('astro_cli_diagnostic_lock', 5);
        if (!$lock->get()) {
            return response()->json([
                'success' => false,
                'error' => 'A diagnostic execution is currently in progress. Please wait a moment and try again.',
            ], 429);
        }

        try {
            $mode = strtolower((string) $request->query('mode', 'all'));
            if (!in_array($mode, ['all', 'sync', 'async'], true)) {
                $mode = 'all';
            }

            $envReport = [
                'web_php_version' => PHP_VERSION,
                'web_php_sapi' => php_sapi_name(),
                'process_functions' => $this->cliService->checkProcessFunctions(),
                'php_cli_discovery' => $this->cliService->discoverPhpCliBinary(),
            ];

            $phpBinary = $envReport['php_cli_discovery']['selected_binary'];

            $syncResult = null;
            $asyncLaunch = null;

            // Generate unique test identifier for this run
            $testId = 'cli_compat_' . bin2hex(random_bytes(8)) . '_' . time();

            // Run Test A: Synchronous Process Execution
            if ($mode === 'all' || $mode === 'sync') {
                $syncTestId = $testId . '_sync';
                $syncResult = $this->cliService->runSyncTest($phpBinary, $syncTestId);
            }

            // Run Test B: Asynchronous Background Process Execution
            if ($mode === 'all' || $mode === 'async') {
                $asyncTestId = $testId . '_async';
                $asyncLaunch = $this->cliService->launchAsyncTest($phpBinary, $asyncTestId);
            }

            $overallDecision = $this->cliService->evaluateDecision($envReport, $syncResult, $asyncLaunch);

            return response()->json([
                'success' => true,
                'diagnostic' => 'Hostinger LiteSpeed CLI Execution Compatibility',
                'timestamp' => now()->toIso8601String(),
                'environment' => $envReport,
                'test_a_synchronous' => $syncResult,
                'test_b_asynchronous_launch' => $asyncLaunch,
                'preliminary_decision' => $overallDecision,
                'next_step' => !empty($asyncLaunch['launched'])
                    ? "Check Test B detached survivability at: " . ($asyncLaunch['result_endpoint'] ?? '') . "?key=" . ($request->query('key') ? '[YOUR_KEY]' : '')
                    : 'Process launch failed; examine process_functions and php_cli_discovery.',
            ], 200, [
                'Cache-Control' => 'no-cache, no-store, must-revalidate, private',
                'Pragma' => 'no-cache',
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Dedicated protected endpoint to check Test B (asynchronous background execution) result.
     * GET /admin/diagnostics/cli-execution-compatibility/result/{test_id}
     */
    public function cliExecutionCompatibilityResult(Request $request, string $testId): JsonResponse
    {
        if (!$this->isDiagnosticAuthorized($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized diagnostic access. Admin session or valid DIAGNOSTIC_KEY required.',
            ], 403);
        }

        if (!preg_match('/^[a-zA-Z0-9_-]{10,64}$/', $testId)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid test identifier format.',
            ], 400);
        }

        $result = $this->cliService->getAsyncResult($testId);

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Verify whether caller is authorized to view diagnostics.
     */
    protected function isDiagnosticAuthorized(Request $request): bool
    {
        // 1. Authenticated admin guard
        if (auth('admin')->check()) {
            return true;
        }

        // 2. Custom environment key from .env (Strict requirement for production diagnostic)
        $expectedKey = env('DIAGNOSTIC_KEY');
        $providedKey = (string) ($request->header('X-Diagnostic-Key') ?? $request->query('key') ?? '');

        if (!empty($expectedKey) && !empty($providedKey) && hash_equals((string) $expectedKey, $providedKey)) {
            return true;
        }

        // 3. Fallback: Accept previous astro_ffi_check_2026 key only if in local environment
        if (app()->environment('local') && $providedKey === 'astro_ffi_check_2026') {
            return true;
        }

        return false;
    }
}
