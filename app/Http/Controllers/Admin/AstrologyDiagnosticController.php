<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Diagnostics\FfiDiagnosticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AstrologyDiagnosticController extends Controller
{
    protected FfiDiagnosticService $diagnosticService;

    public function __construct(
        FfiDiagnosticService $diagnosticService
    ) {
        $this->middleware('auth:admin')->except([
            'ffiCompatibilityTest',
        ]);
        $this->diagnosticService = $diagnosticService;
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
     * 2. Secret query parameter ?key=... or header X-Diagnostic-Key matching DIAGNOSTIC_KEY.
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

        return false;
    }
}
