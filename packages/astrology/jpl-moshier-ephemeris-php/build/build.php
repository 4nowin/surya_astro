<?php

declare(strict_types=1);

$generator = __DIR__ . '/generate-wrapper.php';
$compile = __DIR__ . '/compile.sh';

passthru('php ' . escapeshellarg($generator), $generateExitCode);
if ($generateExitCode !== 0) {
    exit($generateExitCode);
}

passthru('bash ' . escapeshellarg($compile), $compileExitCode);
exit($compileExitCode);
