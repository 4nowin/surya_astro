<?php

declare(strict_types=1);

namespace App\Services\Astrology\Yogas;

use App\Services\Astrology\KundliService;
use App\Services\Astrology\Stability\ContinuousArcUnwrapper;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;

/**
 * Budhaditya Yoga Evaluation Service.
 *
 * Implements Work Package A2 of Astro Setu Milestone M9.2.
 * Classical Sources: Saravali, Ch. 35; Brihat Jataka, Ch. 14.
 *
 * Rule:
 * - Sun and Mercury occupy the SAME sidereal Rashi.
 * - Structural formation is strictly based on same-sign co-presence.
 * - Quality reporting: reports exact calculated angular separation and astronomical combustion status.
 * - Anti-arbitrary cutoff rule: combustion does NOT automatically cancel Budhaditya Yoga;
 *   structural formation and combustion status are reported independently.
 */
class BudhadityaYogaService
{
    /**
     * Evaluate Budhaditya Yoga.
     *
     * @param array<string, array<string, mixed>> $planets Array of planet data
     * @param array<string, mixed> $stabilityData Output from A1 AstrologyIntervalStabilityService
     * @return array<string, mixed>
     */
    public function evaluate(array $planets, array $stabilityData = []): array
    {
        $sun = $planets['sun'] ?? null;
        $mercury = $planets['mercury'] ?? null;

        if (!$sun || !$mercury) {
            return [
                'yoga_key' => 'budhaditya',
                'name' => 'Budhaditya Yoga',
                'name_hi' => 'बुधादित्य योग',
                'is_formed' => false,
                'status' => 'missing_data',
                'reason' => 'Sun or Mercury celestial position data not available.',
                'sun' => null,
                'mercury' => null,
                'angular_separation_deg' => null,
                'is_combust' => false,
                'stability' => [
                    'status' => 'missing_data',
                    'is_sun_invariant' => false,
                    'is_mercury_invariant' => false,
                ],
            ];
        }

        $sunSignId = $this->extractRashiId($sun);
        $mercurySignId = $this->extractRashiId($mercury);

        if ($sunSignId === null || $mercurySignId === null) {
            return [
                'yoga_key' => 'budhaditya',
                'name' => 'Budhaditya Yoga',
                'name_hi' => 'बुधादित्य योग',
                'is_formed' => false,
                'status' => 'missing_data',
                'reason' => 'Sign ID could not be determined for Sun or Mercury.',
                'sun' => null,
                'mercury' => null,
                'angular_separation_deg' => null,
                'is_combust' => false,
                'stability' => [
                    'status' => 'missing_data',
                    'is_sun_invariant' => false,
                    'is_mercury_invariant' => false,
                ],
            ];
        }

        // 1. Classical Structural Formation: Same Sidereal Rashi
        $isFormed = ($sunSignId === $mercurySignId);

        // 2. Exact Angular Separation
        $angularSeparation = null;
        if (isset($sun['longitude']) && isset($mercury['longitude'])) {
            $angularSeparation = round(
                ContinuousArcUnwrapper::circularDifference((float) $sun['longitude'], (float) $mercury['longitude']),
                4
            );
        }

        // 3. Astronomical Combustion Status
        $isMercuryRetrograde = (bool) ($mercury['is_retrograde'] ?? false);
        $threshold = $isMercuryRetrograde ? 12.0 : (KundliService::COMBUSTION_THRESHOLDS['mercury'] ?? 14.0);
        $isCombust = (isset($mercury['is_combust']))
            ? (bool) $mercury['is_combust']
            : ($angularSeparation !== null && $angularSeparation <= $threshold);

        // 4. Dignities
        $sunSignEnum = Rasi::from($sunSignId - 1);
        $mercurySignEnum = Rasi::from($mercurySignId - 1);

        $sunDignity = $this->determineSunDignity($sunSignId);
        $mercuryDignity = $this->determineMercuryDignity($mercurySignId);

        // 5. Interval Stability Integration
        $planetaryStability = $stabilityData['planetary_stability'] ?? ($stabilityData['planetary_navamshas'] ?? null);
        $isSunInvariant = true;
        $isMercuryInvariant = true;

        if ($planetaryStability) {
            if (isset($planetaryStability['sun'])) {
                $isSunInvariant = (bool) ($planetaryStability['sun']['is_invariant'] ?? true);
            }
            if (isset($planetaryStability['mercury'])) {
                $isMercuryInvariant = (bool) ($planetaryStability['mercury']['is_invariant'] ?? true);
            }
        }

        $stabilityStatus = 'confirmed';
        $stabilityReason = 'Evaluated with confirmed planetary coordinates.';

        if (!$isSunInvariant || !$isMercuryInvariant) {
            $stabilityStatus = 'uncertain_sign_transition';
            $stabilityReason = 'Sun or Mercury crosses sign boundary within birth uncertainty interval.';
        }

        return [
            'yoga_key' => 'budhaditya',
            'name' => 'Budhaditya Yoga',
            'name_hi' => 'बुधादित्य योग',
            'is_formed' => $isFormed,
            'formation_status' => $isFormed ? 'formed' : 'not_formed',
            'rashi_id' => $isFormed ? $sunSignId : null,
            'angular_separation_degrees' => $angularSeparation,
            'angular_separation_deg' => $angularSeparation,
            'is_combust' => $isCombust,
            'is_unblemished' => ($isFormed && !$isCombust),
            'sun' => [
                'sign_id' => $sunSignId,
                'sign_name' => $sunSignEnum->getEnglishName(),
                'sign_vedic_name' => $sunSignEnum->getName(),
                'dignity' => $sunDignity,
                'longitude' => $sun['longitude'] ?? null,
            ],
            'mercury' => [
                'sign_id' => $mercurySignId,
                'sign_name' => $mercurySignEnum->getEnglishName(),
                'sign_vedic_name' => $mercurySignEnum->getName(),
                'dignity' => $mercuryDignity,
                'is_combust' => $isCombust,
                'is_retrograde' => $isMercuryRetrograde,
                'longitude' => $mercury['longitude'] ?? null,
            ],
            'quality' => [
                'angular_separation_deg' => $angularSeparation,
                'is_mercury_combust' => $isCombust,
                'combustion_threshold_deg' => $threshold,
                'mercury_dignity' => $mercuryDignity,
                'sun_dignity' => $sunDignity,
                'combustion_note' => $isCombust
                    ? 'Mercury is combust by solar proximity (within ' . $threshold . '°); classical Budhaditya Yoga remains structurally formed while intellectual expression operates on internal/intuitive planes rather than outer display.'
                    : 'Mercury is free from solar combustion (separation exceeds ' . $threshold . '°).',
                'assessment' => $isFormed
                    ? ($isCombust ? 'Budhaditya Yoga is structurally formed with combust Mercury.' : 'Budhaditya Yoga is cleanly formed without combustion.')
                    : 'Budhaditya Yoga is not formed (Sun and Mercury occupy different signs).',
            ],
            'stability' => [
                'status' => $stabilityStatus,
                'reason' => $stabilityReason,
                'is_sun_invariant' => $isSunInvariant,
                'is_mercury_invariant' => $isMercuryInvariant,
            ],
        ];
    }

    protected function determineSunDignity(int $signId): string
    {
        return match ($signId) {
            1 => 'exalted',     // Aries
            7 => 'debilitated', // Libra
            5 => 'own_sign',    // Leo
            default => 'neutral',
        };
    }

    protected function determineMercuryDignity(int $signId): string
    {
        return match ($signId) {
            6 => 'exalted',     // Virgo
            12 => 'debilitated', // Pisces
            3 => 'own_sign',    // Gemini
            default => 'neutral',
        };
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
