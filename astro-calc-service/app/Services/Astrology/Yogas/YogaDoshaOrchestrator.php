<?php

declare(strict_types=1);

namespace App\Services\Astrology\Yogas;

use App\Services\Astrology\Aspects\ParashariAspectService;
use Carbon\CarbonImmutable;

/**
 * Master Orchestrator for Classical Calculations, Yogas, and Manglik Dosha.
 *
 * Implements Work Package A2 of Astro Setu Milestone M9.2.
 * Integrates:
 *  - Parashari Graha Drishti (ParashariAspectService)
 *  - Panch Mahapurusha Yogas (PanchMahapurushaYogaService)
 *  - Gaja Kesari Yoga (GajaKesariYogaService)
 *  - Budhaditya Yoga (BudhadityaYogaService)
 *  - Manglik Dosha and 5 Classical Bhangas (ManglikDoshaService)
 */
class YogaDoshaOrchestrator
{
    public const ENGINE_VERSION = 'M9.2_A2_V1';

    protected ParashariAspectService $aspectService;
    protected PanchMahapurushaYogaService $panchMahapurushaService;
    protected GajaKesariYogaService $gajaKesariService;
    protected BudhadityaYogaService $budhadityaService;
    protected ManglikDoshaService $manglikService;

    public function __construct(
        ?ParashariAspectService $aspectService = null,
        ?PanchMahapurushaYogaService $panchMahapurushaService = null,
        ?GajaKesariYogaService $gajaKesariService = null,
        ?BudhadityaYogaService $budhadityaService = null,
        ?ManglikDoshaService $manglikService = null
    ) {
        $this->aspectService = $aspectService ?? app(ParashariAspectService::class);
        $this->panchMahapurushaService = $panchMahapurushaService ?? app(PanchMahapurushaYogaService::class);
        $this->gajaKesariService = $gajaKesariService ?? app(GajaKesariYogaService::class);
        $this->budhadityaService = $budhadityaService ?? app(BudhadityaYogaService::class);
        $this->manglikService = $manglikService ?? app(ManglikDoshaService::class);
    }

    /**
     * Evaluate complete A2 payload from natal planetary positions and optional stability / ascendant data.
     *
     * @param array<string, mixed> $planets Keyed by planet name (sun, moon, mars, mercury, jupiter, venus, saturn, rahu, ketu)
     * @param array<string, mixed>|null $ascendantData Evaluated D-1 Ascendant structure (from KundliService or AscendantStabilityService)
     * @param array<string, mixed>|null $stabilityData Complete interval stability result (from AstrologyIntervalStabilityService)
     * @param int|null $userAgeYears User age in full years for mature mitigation evaluation
     * @return array<string, mixed> Unified calculation payload
     */
    public function evaluate(
        array $planets,
        ?array $ascendantData = null,
        ?array $stabilityData = null,
        ?int $userAgeYears = null
    ): array {
        // Resolve effective Ascendant data
        $effectiveAscendant = $ascendantData ?? $stabilityData['d1_ascendant'] ?? null;
        if ($effectiveAscendant !== null && isset($stabilityData['d1_ascendant']['candidate_signs']) && empty($effectiveAscendant['candidate_signs'])) {
            $effectiveAscendant['candidate_signs'] = $stabilityData['d1_ascendant']['candidate_signs'];
        }
        $lagnaRashiId = $effectiveAscendant['id'] ?? null;

        // 1. Graha Drishti (Parashari Aspects)
        $aspects = $this->aspectService->computeAspectMatrix($planets, $lagnaRashiId);

        // 2. Panch Mahapurusha Yogas (Ruchaka, Bhadra, Hamsa, Malavya, Sasa)
        $panchMahapurusha = $this->panchMahapurushaService->evaluate($planets, $effectiveAscendant, $stabilityData ?? []);

        // 3. Gaja Kesari Yoga
        $gajaKesari = $this->gajaKesariService->evaluate($planets, $stabilityData ?? []);

        // 4. Budhaditya Yoga
        $budhaditya = $this->budhadityaService->evaluate($planets, $stabilityData ?? []);

        // 5. Manglik Dosha and 5 Classical Bhangas
        $manglikDosha = $this->manglikService->evaluate($planets, $effectiveAscendant, $userAgeYears, null, $stabilityData ?? []);

        // Metadata extraction
        $isLagnaInvariant = true;
        if (isset($stabilityData['d1_ascendant']['is_invariant'])) {
            $isLagnaInvariant = (bool) $stabilityData['d1_ascendant']['is_invariant'];
        } elseif ($effectiveAscendant !== null && array_key_exists('is_invariant', $effectiveAscendant)) {
            $isLagnaInvariant = (bool) $effectiveAscendant['is_invariant'];
        } elseif ($effectiveAscendant !== null && (empty($effectiveAscendant['id']) || in_array($effectiveAscendant['status'] ?? '', ['suppressed', 'unavailable'], true))) {
            $isLagnaInvariant = false;
        }

        $durationSeconds = 0;
        if (isset($stabilityData['interval']['duration_seconds'])) {
            $durationSeconds = (int) $stabilityData['interval']['duration_seconds'];
        } elseif (isset($effectiveAscendant['interval']['duration_seconds'])) {
            $durationSeconds = (int) $effectiveAscendant['interval']['duration_seconds'];
        }

        return [
            'aspects' => $aspects,
            'yogas' => [
                'panch_mahapurusha' => $panchMahapurusha,
                'gaja_kesari' => $gajaKesari,
                'budhaditya' => $budhaditya,
            ],
            'manglik_dosha' => $manglikDosha,
            'meta' => [
                'engine_version' => self::ENGINE_VERSION,
                'evaluated_at' => CarbonImmutable::now()->toIso8601String(),
                'is_lagna_invariant' => $isLagnaInvariant,
                'uncertainty_window_seconds' => $durationSeconds,
            ],
        ];
    }

    /**
     * Convenient entrypoint to evaluate from an existing Kundli output payload.
     *
     * @param array<string, mixed> $kundliData Output from KundliService::computeKundli
     * @param array<string, mixed>|null $stabilityData Interval stability analysis output
     * @param int|null $userAgeYears User age in full completed years
     * @return array<string, mixed>
     */
    public function evaluateFromKundli(
        array $kundliData,
        ?array $stabilityData = null,
        ?int $userAgeYears = null
    ): array {
        $planets = $kundliData['planets'] ?? [];
        $ascendantData = $kundliData['ascendant'] ?? null;

        return $this->evaluate($planets, $ascendantData, $stabilityData, $userAgeYears);
    }
}
