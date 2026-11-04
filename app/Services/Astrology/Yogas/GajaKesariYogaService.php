<?php

declare(strict_types=1);

namespace App\Services\Astrology\Yogas;

use App\Services\Astrology\Stability\ContinuousArcUnwrapper;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;

/**
 * Gaja Kesari Yoga Evaluation Service.
 *
 * Implements Work Package A2 of Astro Setu Milestone M9.2.
 * Classical Sources: BPHS, Ch. 36, Verses 3–4; Phaladeepika, Ch. 6, Verses 7–8.
 *
 * Rule:
 * - Jupiter in a Kendra (1st, 4th, 7th, or 10th house/sign) from the natal Moon.
 * - Structural formation is strictly independent from planetary dignity.
 * - Qualitative impediments (e.g., Jupiter debilitated in Capricorn, or solar combustion)
 *   are documented as dignity/quality indicators without arbitrary formation cancellation thresholds.
 * - Interval stability across natal Moon's birth interval is respected.
 */
class GajaKesariYogaService
{
    public const KENDRA_HOUSES_FROM_MOON = [1, 4, 7, 10];

    /**
     * Evaluate Gaja Kesari Yoga.
     *
     * @param array<string, array<string, mixed>> $planets Array of planet data
     * @param array<string, mixed> $stabilityData Output from A1 AstrologyIntervalStabilityService
     * @return array<string, mixed>
     */
    public function evaluate(array $planets, array $stabilityData = []): array
    {
        $moon = $planets['moon'] ?? null;
        $jupiter = $planets['jupiter'] ?? null;

        if (!$moon || !$jupiter) {
            return [
                'yoga_key' => 'gaja_kesari',
                'name' => 'Gaja Kesari Yoga',
                'name_hi' => 'गजकेसरी योग',
                'is_formed' => false,
                'status' => 'missing_data',
                'reason' => 'Moon or Jupiter celestial position data not available.',
                'moon' => null,
                'jupiter' => null,
                'house_from_moon' => null,
                'quality' => null,
                'stability' => [
                    'status' => 'missing_data',
                    'is_moon_invariant' => false,
                ],
            ];
        }

        $moonSignId = $this->extractRashiId($moon);
        $jupiterSignId = $this->extractRashiId($jupiter);

        if ($moonSignId === null || $jupiterSignId === null) {
            return [
                'yoga_key' => 'gaja_kesari',
                'name' => 'Gaja Kesari Yoga',
                'name_hi' => 'गजकेसरी योग',
                'is_formed' => false,
                'status' => 'missing_data',
                'reason' => 'Sign ID could not be determined for Moon or Jupiter.',
                'moon' => null,
                'jupiter' => null,
                'house_from_moon' => null,
                'quality' => null,
                'stability' => [
                    'status' => 'missing_data',
                    'is_moon_invariant' => false,
                ],
            ];
        }

        // 1. House from Moon: 1-based relative sign count (1..12)
        $houseFromMoon = (($jupiterSignId - $moonSignId + 12) % 12) + 1;
        $isFormed = in_array($houseFromMoon, self::KENDRA_HOUSES_FROM_MOON, true);

        // 2. Dignities
        $jupiterDignity = $this->determineJupiterDignity($jupiterSignId);
        $moonDignity = $this->determineMoonDignity($moonSignId);

        // 3. Angular separation
        $angularSeparation = null;
        if (isset($moon['longitude']) && isset($jupiter['longitude'])) {
            $angularSeparation = round(
                ContinuousArcUnwrapper::circularDifference((float) $moon['longitude'], (float) $jupiter['longitude']),
                4
            );
        }

        $isJupiterCombust = (bool) ($jupiter['is_combust'] ?? false);
        $isJupiterRetrograde = (bool) ($jupiter['is_retrograde'] ?? false);

        // 4. Stability analysis
        $panchangaStability = $stabilityData['panchanga'] ?? ($stabilityData['birth_panchanga'] ?? null);
        $isMoonInvariant = true;

        if (isset($stabilityData['is_moon_invariant'])) {
            $isMoonInvariant = (bool) $stabilityData['is_moon_invariant'];
        } elseif ($panchangaStability && isset($panchangaStability['nakshatra'])) {
            $isMoonInvariant = (bool) ($panchangaStability['nakshatra']['is_invariant'] ?? true);
        } elseif (isset($stabilityData['nakshatra']['is_invariant'])) {
            $isMoonInvariant = (bool) $stabilityData['nakshatra']['is_invariant'];
        } elseif (isset($stabilityData['is_invariant'])) {
            $isMoonInvariant = (bool) $stabilityData['is_invariant'];
        }

        $planetaryStability = $stabilityData['planetary_stability'] ?? ($stabilityData['planetary_navamshas'] ?? null);
        $isJupiterInvariant = true;
        if (isset($stabilityData['is_jupiter_invariant'])) {
            $isJupiterInvariant = (bool) $stabilityData['is_jupiter_invariant'];
        } elseif ($planetaryStability && isset($planetaryStability['jupiter'])) {
            $isJupiterInvariant = (bool) ($planetaryStability['jupiter']['is_invariant'] ?? true);
        }

        $stabilityStatus = 'confirmed';
        $stabilityReason = 'Evaluated with confirmed planetary coordinates.';

        if (!$isMoonInvariant && !$isJupiterInvariant) {
            $stabilityStatus = 'uncertain_moon_and_jupiter';
            $stabilityReason = 'Both natal Moon and Jupiter cross boundaries within birth uncertainty interval; Gaja Kesari relationship cannot be certified.';
        } elseif (!$isMoonInvariant) {
            $stabilityStatus = 'uncertain_moon_sign';
            $stabilityReason = 'Natal Moon crosses boundary within birth uncertainty interval; Gaja Kesari relationship cannot be certified.';
        } elseif (!$isJupiterInvariant) {
            $stabilityStatus = 'uncertain_jupiter_sign';
            $stabilityReason = 'Jupiter crosses sign boundary within birth uncertainty interval; Gaja Kesari relationship cannot be certified.';
        }

        $moonSignEnum = Rasi::from($moonSignId - 1);
        $jupiterSignEnum = Rasi::from($jupiterSignId - 1);

        $qualityNotes = [];
        if ($jupiterDignity === 'debilitated') {
            $qualityNotes[] = 'jupiter_debilitated';
        }
        if ($moonDignity === 'debilitated') {
            $qualityNotes[] = 'moon_debilitated';
        }
        if ($isJupiterCombust) {
            $qualityNotes[] = 'jupiter_combust';
        }

        return [
            'yoga_key' => 'gaja_kesari',
            'name' => 'Gaja Kesari Yoga',
            'name_hi' => 'गजकेसरी योग',
            'is_formed' => $isFormed,
            'is_confirmed' => ($isFormed && $isMoonInvariant && $isJupiterInvariant),
            'stability_status' => $stabilityStatus,
            'relationship' => $isFormed ? "Jupiter in {$houseFromMoon}th house from Moon (Kendra)" : "Jupiter in {$houseFromMoon}th house from Moon (Non-Kendra)",
            'house_from_moon' => $houseFromMoon,
            'relative_house_from_moon' => $houseFromMoon,
            'is_kendra_from_moon' => $isFormed,
            'jupiter_dignity' => $jupiterDignity,
            'moon_dignity' => $moonDignity,
            'has_impediment' => ($jupiterDignity === 'debilitated' || $moonDignity === 'debilitated' || $isJupiterCombust),
            'quality_notes' => $qualityNotes,
            'moon' => [
                'sign_id' => $moonSignId,
                'sign_name' => $moonSignEnum->getEnglishName(),
                'sign_vedic_name' => $moonSignEnum->getName(),
                'dignity' => $moonDignity,
            ],
            'jupiter' => [
                'sign_id' => $jupiterSignId,
                'sign_name' => $jupiterSignEnum->getEnglishName(),
                'sign_vedic_name' => $jupiterSignEnum->getName(),
                'dignity' => $jupiterDignity,
                'is_combust' => $isJupiterCombust,
                'is_retrograde' => $isJupiterRetrograde,
            ],
            'quality' => [
                'angular_separation_deg' => $angularSeparation,
                'jupiter_dignity' => $jupiterDignity,
                'moon_dignity' => $moonDignity,
                'is_jupiter_combust' => $isJupiterCombust,
                'has_debilitation' => ($jupiterDignity === 'debilitated' || $moonDignity === 'debilitated'),
                'assessment' => $this->buildQualityAssessment($isFormed, $jupiterDignity, $moonDignity, $isJupiterCombust),
            ],
            'stability' => [
                'status' => $stabilityStatus,
                'reason' => $stabilityReason,
                'is_moon_invariant' => $isMoonInvariant,
                'is_jupiter_invariant' => $isJupiterInvariant,
            ],
        ];
    }

    protected function determineJupiterDignity(int $signId): string
    {
        return match ($signId) {
            4 => 'exalted',     // Cancer
            10 => 'debilitated', // Capricorn
            9, 12 => 'own_sign', // Sagittarius, Pisces
            default => 'neutral',
        };
    }

    protected function determineMoonDignity(int $signId): string
    {
        return match ($signId) {
            2 => 'exalted',     // Taurus
            8 => 'debilitated', // Scorpio
            4 => 'own_sign',    // Cancer
            default => 'neutral',
        };
    }

    protected function buildQualityAssessment(bool $isFormed, string $jupDig, string $moonDig, bool $combust): string
    {
        if (!$isFormed) {
            return 'Gaja Kesari Yoga is not structurally formed (Jupiter is not in Kendra from Moon).';
        }

        $notes = ['Gaja Kesari Yoga is structurally formed.'];
        if ($jupDig === 'exalted') {
            $notes[] = 'Jupiter is exalted in Cancer, conferring supreme auspiciousness.';
        } elseif ($jupDig === 'own_sign') {
            $notes[] = 'Jupiter occupies its own sign, granting strong dignified manifestation.';
        } elseif ($jupDig === 'debilitated') {
            $notes[] = 'Jupiter is debilitated in Capricorn; structural formation holds, but external material manifestation is constrained.';
        }

        if ($moonDig === 'debilitated') {
            $notes[] = 'Moon is debilitated in Scorpio, indicating emotional fluctuations.';
        }

        if ($combust) {
            $notes[] = 'Jupiter is combust by solar proximity, which moderates visible external benefits.';
        }

        return implode(' ', $notes);
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
