<?php

declare(strict_types=1);

namespace JmeEph\Service;

use JmeEph\FFI\JmeEphFFI;

class JmeService
{
    private readonly string $engine;

    public function __construct(private readonly JmeEphFFI $ffi, string $engine = 'AUTO', private readonly ?string $ephemerisPath = null)
    {
        $this->engine = $this->normalizeEngine($engine);
    }

    public function calc(float $jd_et, int $body, int $flags = JmeEphFFI::JME_CALC_NONE, &$results = null, &$error = null)
    {
        $results ??= $this->ffi->getFFI()->new('double[6]');
        $error ??= $this->ffi->getFFI()->new('char[256]');
        $this->ffi->configureEngine($this->engine, $this->ephemerisPath);
        return $this->ffi->getFFI()->jme_calc($jd_et, $body, $flags, $results, $error);
    }

    private function normalizeEngine(string $engine): string
    {
        return match (strtoupper($engine)) {
            'AUTO' => 'AUTO',
            'JPL' => 'JPL',
            'MOSHIER' => 'MOSHIER',
            'VSOP_ELP_MEEUS' => 'VSOP_ELP_MEEUS',
            default => $engine,
        };
    }
}
