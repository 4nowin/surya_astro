<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Diagnostics\FfiDiagnosticService;

class DiagnoseAstrologyEngine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'astrology:diagnose {--json : Output the diagnostic report in raw JSON format} {--export= : Save report to a local file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform diagnostic tests for PHP FFI, native libraries, and environment capabilities for the Astrology engine';

    /**
     * Execute the console command.
     */
    public function handle(FfiDiagnosticService $service): int
    {
        $diagnostics = $service->runDiagnostics();

        if ($this->option('json')) {
            $json = json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $this->line($json);
            return self::SUCCESS;
        }

        $this->newLine();
        $this->alert("ASTRO SETU — ASTROLOGY ENGINE FFI DIAGNOSTICS (CLI RUNTIME)");
        $this->info("Timestamp : " . $diagnostics['timestamp']);
        $this->info("Context   : " . $diagnostics['context'] . " (SAPI: " . php_sapi_name() . ")");
        $this->newLine();

        // 1. PHP Environment
        $this->line("<fg=yellow;options=bold>1. PHP ENVIRONMENT CHECKS</>");
        $envRows = [];
        foreach ($diagnostics['php_environment'] as $key => $item) {
            $statusColor = $this->getStatusColor($item['status']);
            $envRows[] = [
                $item['label'],
                $item['value'],
                "<fg={$statusColor};options=bold>{$item['status']}</>",
                $item['detail'] ?? '',
            ];
        }
        $this->table(['Check / Directive', 'Value', 'Status', 'Details'], $envRows);
        $this->newLine();

        // 2. FFI Core Test
        $this->line("<fg=yellow;options=bold>2. FFI CORE FUNCTIONALITY TEST (abs(-42) -> 42)</>");
        $c = $diagnostics['ffi_core_test'];
        $cColor = $this->getStatusColor($c['status']);
        $this->table(
            ['Property', 'Result'],
            [
                ['Test', $c['name']],
                ['Status', "<fg={$cColor};options=bold>{$c['status']}</>"],
                ['Expected', (string)$c['expected_value']],
                ['Actual Value', var_export($c['actual_value'], true)],
                ['Library Linked', $c['library_used'] ?: 'None'],
                ['Error Message', $c['error_message'] ?: 'None'],
                ['Analysis', $c['analysis']],
            ]
        );
        $this->newLine();

        // 3. Custom Native Library Test
        $this->line("<fg=yellow;options=bold>3. CUSTOM SHARED LIBRARY LOADING TEST</>");
        $n = $diagnostics['native_library_test'];
        $nColor = $this->getStatusColor($n['status']);
        $this->table(
            ['Property', 'Result'],
            [
                ['Test', $n['name']],
                ['Status', "<fg={$nColor};options=bold>{$n['status']}</>"],
                ['Target Path', $n['target_file']],
                ['File Exists', $n['file_exists'] ? 'YES' : 'NO'],
                ['Is Readable', $n['is_readable'] ? 'YES' : 'NO'],
                ['File Size', $n['filesize'] . ' bytes'],
                ['Expected', (string)$n['expected_value']],
                ['Actual Value', var_export($n['actual_value'], true)],
                ['Error Message', $n['error_message'] ?: 'None'],
                ['Analysis', $n['analysis']],
                ['Build Instruction', $n['build_instruction']],
            ]
        );
        $this->newLine();

        // 4. Filesystem & Process Restrictions
        $this->line("<fg=yellow;options=bold>4. FILESYSTEM & PROCESS RESTRICTIONS</>");
        $fsRows = [];
        foreach ($diagnostics['filesystem_and_security'] as $item) {
            $fsRows[] = [
                $item['label'],
                $item['path'],
                $item['is_readable'] ? 'YES' : 'NO',
                isset($item['is_writable']) ? ($item['is_writable'] ? 'YES' : 'NO') : 'N/A',
                "<fg=" . $this->getStatusColor($item['status']) . ";options=bold>{$item['status']}</>",
            ];
        }
        $this->table(['Resource', 'Path', 'Readable', 'Writable', 'Status'], $fsRows);
        $this->newLine();

        // 5. Candidate Suitability
        $this->line("<fg=yellow;options=bold>5. ASTROLOGY PACKAGE FEASIBILITY EVALUATION</>");
        $candRows = [];
        foreach ($diagnostics['astrology_candidates'] as $pkg) {
            $candRows[] = [
                $pkg['name'],
                $pkg['feasibility_status'],
                $pkg['commercial_licensing'],
                $pkg['reason'],
            ];
        }
        $this->table(['Candidate', 'Status', 'Licensing', 'Assessment'], $candRows);
        $this->newLine();

        // 6. Overall Verdict
        $v = $diagnostics['overall_verdict'];
        $vColor = $v['level'] === 'SUCCESS' ? 'green' : ($v['level'] === 'PARTIAL_SUCCESS' ? 'yellow' : 'red');

        $this->line("<fg={$vColor};options=bold>===============================================================</>");
        $this->line("<fg={$vColor};options=bold>OVERALL VERDICT: {$v['title']}</>");
        $this->line("<fg={$vColor};options=bold>===============================================================</>");
        $this->line($v['summary']);
        $this->line("<options=bold>Recommended Next Step:</> " . $v['next_step']);
        $this->newLine();

        $this->warn("IMPORTANT: This CLI result does NOT guarantee Web PHP compatibility.");
        $this->comment("On Hostinger, Web PHP (FPM / LiteSpeed) and CLI PHP use independent configuration files.");
        $this->comment("Always verify Web PHP by visiting: https://<domain>/admin/diagnostics/astrology-engine");
        $this->newLine();

        if ($exportPath = $this->option('export')) {
            $textContent = $service->formatAsText($diagnostics);
            file_put_contents($exportPath, $textContent);
            $this->info("Report successfully exported to: {$exportPath}");
        }

        return self::SUCCESS;
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'PASS', 'SUCCESS' => 'green',
            'WARNING', 'INFO', 'PARTIAL_SUCCESS' => 'yellow',
            'NOT_TESTED' => 'blue',
            default => 'red',
        };
    }
}
