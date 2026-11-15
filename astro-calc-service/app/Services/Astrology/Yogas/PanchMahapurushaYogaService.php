<?php

declare(strict_types=1);

namespace App\Services\Astrology\Yogas;

use JayeshMepani\PanchangCore\Core\Enums\Rasi;

/**
 * Panch Mahapurusha Yogas Evaluation Service.
 *
 * Implements Work Package A2 of Astro Setu Milestone M9.2.
 * Classical Sources: BPHS, Ch. 75, Verses 1–12; Phaladeepika, Ch. 6, Verses 1–4.
 *
 * The 5 Mahapurusha Yogas:
 * 1. Ruchaka — Mars in Own Sign (Aries, Scorpio) or Exaltation (Capricorn) in a Kendra from Lagna (1, 4, 7, 10).
 * 2. Bhadra — Mercury in Own Sign (Gemini, Virgo) or Exaltation (Virgo) in a Kendra from Lagna (1, 4, 7, 10).
 * 3. Hamsa — Jupiter in Own Sign (Sagittarius, Pisces) or Exaltation (Cancer) in a Kendra from Lagna (1, 4, 7, 10).
 * 4. Malavya — Venus in Own Sign (Taurus, Libra) or Exaltation (Pisces) in a Kendra from Lagna (1, 4, 7, 10).
 * 5. Sasa — Saturn in Own Sign (Capricorn, Aquarius) or Exaltation (Libra) in a Kendra from Lagna (1, 4, 7, 10).
 *
 * Core Principles:
 * - Structural formation is strictly binary (True/False).
 * - Combustion, retrogression, and secondary afflictions do NOT erase structural formation.
 * - Lagna-dependent stability across the A1 birth interval is certified and reported.
 */
class PanchMahapurushaYogaService
{
    /**
     * Definition of the 5 Panch Mahapurusha Yogas.
     */
    public const YOGAS = [
        'ruchaka' => [
            'name' => 'Ruchaka Yoga',
            'name_hi' => 'रुचक योग',
            'planet' => 'mars',
            'planet_name' => 'Mars',
            'planet_name_hi' => 'मंगल',
            'own_signs' => [1, 8],      // Aries, Scorpio
            'exaltation_sign' => 10,     // Capricorn
        ],
        'bhadra' => [
            'name' => 'Bhadra Yoga',
            'name_hi' => 'भद्र योग',
            'planet' => 'mercury',
            'planet_name' => 'Mercury',
            'planet_name_hi' => 'बुध',
            'own_signs' => [3, 6],      // Gemini, Virgo
            'exaltation_sign' => 6,      // Virgo
        ],
        'hamsa' => [
            'name' => 'Hamsa Yoga',
            'name_hi' => 'हंस योग',
            'planet' => 'jupiter',
            'planet_name' => 'Jupiter',
            'planet_name_hi' => 'बृहस्पति',
            'own_signs' => [9, 12],     // Sagittarius, Pisces
            'exaltation_sign' => 4,      // Cancer
        ],
        'malavya' => [
            'name' => 'Malavya Yoga',
            'name_hi' => 'मालव्य योग',
            'planet' => 'venus',
            'planet_name' => 'Venus',
            'planet_name_hi' => 'शुक्र',
            'own_signs' => [2, 7],      // Taurus, Libra
            'exaltation_sign' => 12,     // Pisces
        ],
        'sasa' => [
            'name' => 'Sasa Yoga',
            'name_hi' => 'शश योग',
            'planet' => 'saturn',
            'planet_name' => 'Saturn',
            'planet_name_hi' => 'शनि',
            'own_signs' => [10, 11],    // Capricorn, Aquarius
            'exaltation_sign' => 7,      // Libra
        ],
    ];

    public const KENDRA_HOUSES = [1, 4, 7, 10];

    /**
     * Unified evaluation entrypoint for Panch Mahapurusha Yogas.
     *
     * @param array<string, array<string, mixed>> $planets
     * @param array<string, mixed>|int|null $ascendantData Ascendant sign ID or evaluated ascendant data
     * @param array<string, mixed> $stabilityData
     * @return array<string, mixed>
     */
    public function evaluate(array $planets, $ascendantData = null, array $stabilityData = []): array
    {
        $lagnaRashiId = is_int($ascendantData)
            ? $ascendantData
            : ($ascendantData['id'] ?? null);

        if (is_array($ascendantData)) {
            if (isset($ascendantData['status']) && in_array($ascendantData['status'], ['suppressed', 'unavailable'], true)) {
                $lagnaRashiId = null;
            }
            if (empty($stabilityData) && isset($ascendantData['is_invariant'])) {
                $stabilityData['d1_ascendant'] = $ascendantData;
            }
        }

        return $this->evaluateAll($planets, $lagnaRashiId, $stabilityData);
    }

    /**
     * Evaluate all 5 Panch Mahapurusha Yogas.
     *
     * @param array<string, array<string, mixed>> $planets
     * @param int|null $lagnaRashiId Nominal/confirmed Lagna sign ID (1..12)
     * @param array<string, mixed> $stabilityData Output from A1 AstrologyIntervalStabilityService
     * @return array<string, mixed>
     */
    public function evaluateAll(array $planets, ?int $lagnaRashiId, array $stabilityData = []): array
    {
        $results = [];
        $formedCount = 0;
        $formedList = [];

        $d1AscStability = $stabilityData['d1_ascendant'] ?? null;
        $isLagnaInvariant = ($d1AscStability !== null)
            ? ($d1AscStability['is_invariant'] ?? false)
            : ($lagnaRashiId !== null);

        $candidateLagnas = [];
        if ($d1AscStability && !empty($d1AscStability['candidate_signs'])) {
            foreach ($d1AscStability['candidate_signs'] as $cs) {
                if (isset($cs['id'])) {
                    $candidateLagnas[] = (int) $cs['id'];
                }
            }
            $candidateLagnas = array_values(array_unique($candidateLagnas));
        } elseif ($lagnaRashiId !== null) {
            $candidateLagnas = [$lagnaRashiId];
        }

        foreach (self::YOGAS as $yogaKey => $def) {
            $planetKey = $def['planet'];
            $planet = $planets[$planetKey] ?? null;

            if (!$planet) {
                $results[$yogaKey] = $this->buildMissingPlanetResult($yogaKey, $def);
                continue;
            }

            $signId = $this->extractRashiId($planet);
            if ($signId === null) {
                $results[$yogaKey] = $this->buildMissingPlanetResult($yogaKey, $def);
                continue;
            }

            // Check sign dignity (Own or Exaltation)
            $isOwn = in_array($signId, $def['own_signs'], true);
            $isExalted = ($signId === $def['exaltation_sign']);
            $hasEligibleDignity = ($isOwn || $isExalted);

            $dignityType = null;
            if ($isExalted) {
                $dignityType = 'exalted';
            } elseif ($isOwn) {
                $dignityType = 'own_sign';
            }

            // Check Kendra from Lagna
            $nominalHouse = null;
            $isKendra = false;

            if ($lagnaRashiId !== null) {
                $nominalHouse = (($signId - $lagnaRashiId + 12) % 12) + 1;
                $isKendra = in_array($nominalHouse, self::KENDRA_HOUSES, true);
            }

            $isFormed = ($hasEligibleDignity && $isKendra);

            // Stability analysis across A1 uncertainty window
            $stabilityStatus = 'confirmed';
            $stabilityReason = 'Evaluated with confirmed birth-time coordinates.';
            $candidateEvaluations = [];
            $precision = $stabilityData['birth_time_precision']
                ?? $stabilityData['interval']['precision']
                ?? null;

            if ($lagnaRashiId === null || $precision === 'unknown') {
                $isUnknown = ($precision === 'unknown' || empty($candidateLagnas));
                $stabilityStatus = $isUnknown ? 'suppressed_unknown_lagna' : 'uncertain_lagna_variation';
                $stabilityReason = $isUnknown
                    ? 'Birth time unknown; Kendra house placement cannot be determined.'
                    : 'Lagna crosses boundary; Kendra placement varies among candidate signs.';
                $isFormed = false;

                if (!$isUnknown && !empty($candidateLagnas)) {
                    foreach ($candidateLagnas as $candLagnaId) {
                        $h = (($signId - $candLagnaId + 12) % 12) + 1;
                        $candKendra = in_array($h, self::KENDRA_HOUSES, true);
                        $candidateEvaluations[] = [
                            'lagna_id' => $candLagnaId,
                            'lagna_name' => Rasi::from($candLagnaId - 1)->getEnglishName(),
                            'house' => $h,
                            'is_kendra' => $candKendra,
                            'is_formed' => ($hasEligibleDignity && $candKendra),
                        ];
                    }
                }
            } elseif (!$isLagnaInvariant) {
                // Lagna crosses boundary: evaluate formation in each candidate Lagna
                $formedInCandidates = [];
                foreach ($candidateLagnas as $candLagnaId) {
                    $h = (($signId - $candLagnaId + 12) % 12) + 1;
                    $candKendra = in_array($h, self::KENDRA_HOUSES, true);
                    $candFormed = ($hasEligibleDignity && $candKendra);
                    $formedInCandidates[] = $candFormed;
                    $candidateEvaluations[] = [
                        'lagna_id' => $candLagnaId,
                        'lagna_name' => Rasi::from($candLagnaId - 1)->getEnglishName(),
                        'house' => $h,
                        'is_kendra' => $candKendra,
                        'is_formed' => $candFormed,
                    ];
                }

                $allFormed = !in_array(false, $formedInCandidates, true);
                $anyFormed = in_array(true, $formedInCandidates, true);

                if ($allFormed) {
                    $stabilityStatus = 'confirmed';
                    $stabilityReason = 'Yoga formed across all candidate Lagnas in uncertainty window.';
                    $isFormed = true;
                } elseif ($anyFormed) {
                    $stabilityStatus = 'uncertain_lagna_variation';
                    $stabilityReason = 'Yoga formation depends on exact birth time; formed only in a subset of candidate Ascendants.';
                    $isFormed = true; // Structurally possible
                } else {
                    $stabilityStatus = 'confirmed_not_formed';
                    $stabilityReason = 'Yoga is not formed in any candidate Ascendant in uncertainty window.';
                    $isFormed = false;
                }
            }

            // Quality indicators (combustion, retrogression)
            $isCombust = (bool) ($planet['is_combust'] ?? false);
            $isRetrograde = (bool) ($planet['is_retrograde'] ?? false);
            $signEnum = Rasi::from($signId - 1);

            $qualityFactors = [];
            if ($isCombust) {
                $qualityFactors[] = 'combustion';
            }
            if ($isRetrograde) {
                $qualityFactors[] = 'retrogression';
            }

            $result = [
                'yoga_key' => $yogaKey,
                'yoga_name' => $def['name'],
                'yoga_name_hi' => $def['name_hi'],
                'planet_key' => $planetKey,
                'planet_name' => $def['planet_name'],
                'planet_name_hi' => $def['planet_name_hi'],
                'is_formed' => $isFormed,
                'sign' => [
                    'id' => $signId,
                    'name' => $signEnum->getEnglishName(),
                    'vedic_name' => $signEnum->getName(),
                ],
                'house' => $nominalHouse,
                'is_in_kendra' => $isKendra,
                'has_eligible_dignity' => $hasEligibleDignity,
                'dignity' => $dignityType,
                'dignity_type' => $dignityType,
                'is_combust' => $isCombust,
                'is_retrograde' => $isRetrograde,
                'is_unblemished' => ($isFormed && empty($qualityFactors)),
                'quality_factors' => $qualityFactors,
                'stability_status' => $stabilityStatus,
                'quality' => [
                    'is_combust' => $isCombust,
                    'is_retrograde' => $isRetrograde,
                    'combustion_note' => $isCombust
                        ? 'Planet is combust by solar proximity; classical formation remains structurally valid while external strength is modified.'
                        : 'Planet is not combust.',
                ],
                'stability' => [
                    'status' => $stabilityStatus,
                    'reason' => $stabilityReason,
                    'is_lagna_invariant' => $isLagnaInvariant,
                    'candidate_evaluations' => $candidateEvaluations,
                ],
            ];

            $results[$yogaKey] = $result;

            if ($isFormed) {
                $formedCount++;
                $formedList[] = $yogaKey;
            }
        }

        $evaluationStatus = ($lagnaRashiId === null) ? 'suppressed' : 'confirmed';

        return [
            'total_evaluated' => count(self::YOGAS),
            'formed_count' => $formedCount,
            'formed_yogas' => $formedList,
            'has_any_mahapurusha' => ($formedCount > 0),
            'evaluation_status' => $evaluationStatus,
            'yogas' => $results,
            'details' => $results,
        ];
    }

    protected function buildMissingPlanetResult(string $yogaKey, array $def): array
    {
        return [
            'yoga_key' => $yogaKey,
            'yoga_name' => $def['name'],
            'yoga_name_hi' => $def['name_hi'],
            'planet_key' => $def['planet'],
            'planet_name' => $def['planet_name'],
            'planet_name_hi' => $def['planet_name_hi'],
            'is_formed' => false,
            'sign' => null,
            'house' => null,
            'is_in_kendra' => false,
            'has_eligible_dignity' => false,
            'dignity_type' => null,
            'quality' => null,
            'stability' => [
                'status' => 'missing_data',
                'reason' => 'Planetary data not available.',
                'is_lagna_invariant' => false,
                'candidate_evaluations' => [],
            ],
        ];
    }

    protected function extractRashiId(array $planet): ?int
    {
        if (isset($planet['rashi']['id'])) {
            return (int) $planet['rashi']['id'];
        }
        if (isset($planet['rashi_id'])) {
            return (int) $planet['rashi_id'];
        }
        if (isset($planet['rashi']['index'])) {
            return ((int) $planet['rashi']['index']) + 1;
        }
        if (isset($planet['longitude'])) {
            return ((int) floor((float) $planet['longitude'] / 30.0)) + 1;
        }
        return null;
    }
}
