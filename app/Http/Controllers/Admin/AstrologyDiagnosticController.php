<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Diagnostics\FfiDiagnosticService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AstrologyDiagnosticController extends Controller
{
    protected FfiDiagnosticService $diagnosticService;

    public function __construct(FfiDiagnosticService $diagnosticService)
    {
        $this->middleware('auth:admin');
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
}
