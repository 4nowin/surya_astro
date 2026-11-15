<?php

declare(strict_types=1);

namespace App\Services\Astrology\Aspects;

use JayeshMepani\PanchangCore\Core\Enums\Rasi;

/**
 * Pure Parashari 7-Graha Drishti (Aspect) Matrix Engine.
 *
 * Implements Work Package A2 of Astro Setu Milestone M9.2.
 * Follows classical Brihat Parashara Hora Shastra (BPHS) Graha Drishti principles:
 * - 7 classical bodies: Sun, Moon, Mars, Mercury, Jupiter, Venus, Saturn (Rahu & Ketu excluded).
 * - Universal 7th house / 7th sign aspect for all 7 planets.
 * - Special full aspects (Vishesha Drishti):
 *   - Mars: 4th and 8th aspects (in addition to 7th).
 *   - Jupiter: 5th and 9th aspects (in addition to 7th).
 *   - Saturn: 3rd and 10th aspects (in addition to 7th).
 * - Pure sign/house-based Parashari relationships (no Western orbs or degrees).
 */
class ParashariAspectService
{
    /**
     * The 7 classical Parashari planets included in aspect calculations.
     */
    public const INCLUDED_PLANETS = [
        'sun',
        'moon',
        'mars',
        'mercury',
        'jupiter',
        'venus',
        'saturn',
    ];

    /**
     * Classical Parashari full aspects (1-based relative house/sign count from aspecting body).
     * Universal 7th aspect applies to all; Mars, Jupiter, Saturn possess special full aspects.
     */
    public const PLANET_ASPECT_OFFSETS = [
        'sun'     => [7],
        'moon'    => [7],
        'mars'    => [4, 7, 8],
        'mercury' => [7],
        'jupiter' => [5, 7, 9],
        'venus'   => [7],
        'saturn'  => [3, 7, 10],
    ];

    /**
     * Calculate the complete deterministic Parashari aspect matrix for all included planets.
     *
     * @param array<string, array<string, mixed>> $planets Array of planet data with 'rashi' containing 'id' (1..12)
     * @param int|null $lagnaRashiId Natal Ascendant sign ID (1..12), if known
     * @return array<string, mixed> Structured aspect matrix
     */
    public function calculateAspectMatrix(array $planets, ?int $lagnaRashiId = null): array
    {
        return $this->computeAspectMatrix($planets, $lagnaRashiId);
    }

    /**
     * Compute aspect matrix (alias for calculateAspectMatrix).
     *
     * @param array<string, array<string, mixed>> $planets
     * @param int|null $lagnaRashiId
     * @return array<string, mixed>
     */
    public function computeAspectMatrix(array $planets, ?int $lagnaRashiId = null): array
    {
        $filteredPlanets = [];
        $planetsBySign = [];

        // 1. Index all planets by sign (for receiving aspects)
        foreach ($planets as $key => $p) {
            if (!is_array($p)) {
                continue;
            }
            $rashiId = $this->extractRashiId($p);
            if ($rashiId === null) {
                continue;
            }
            $planetsBySign[$rashiId][] = $key;
        }

        // 2. Canonical deterministic ordering for aspecting planets (Sun through Saturn)
        foreach (self::INCLUDED_PLANETS as $key) {
            if (!isset($planets[$key]) || !is_array($planets[$key])) {
                continue;
            }
            $p = $planets[$key];
            $rashiId = $this->extractRashiId($p);
            if ($rashiId === null) {
                continue;
            }

            $house = ($lagnaRashiId !== null)
                ? (($rashiId - $lagnaRashiId + 12) % 12) + 1
                : null;

            $filteredPlanets[$key] = [
                'key' => $key,
                'name' => $p['name'] ?? ucfirst($key),
                'name_hi' => $p['name_hi'] ?? '',
                'sign_id' => $rashiId,
                'sign_name' => Rasi::from($rashiId - 1)->getEnglishName(),
                'sign_vedic_name' => Rasi::from($rashiId - 1)->getName(),
                'house' => $house,
                'longitude' => $p['longitude'] ?? null,
            ];
        }

        // 3. Generate aspects
        $allAspects = [];
        $aspectsByPlanet = [];
        $aspectsByTargetSign = array_fill(1, 12, ['aspecting_planets' => [], 'aspects' => []]);
        $aspectedHouses = ($lagnaRashiId !== null) ? array_fill(1, 12, []) : null;
        $aspectsReceivedByPlanet = array_fill_keys(array_keys($planets), []);

        foreach ($filteredPlanets as $fromKey => $fromPlanet) {
            $fromSignId = $fromPlanet['sign_id'];
            $fromHouse = $fromPlanet['house'];
            $offsets = self::PLANET_ASPECT_OFFSETS[$fromKey] ?? [7];

            $aspectsByPlanet[$fromKey] = [];

            foreach ($offsets as $aspectCount) {
                // Circular sign arithmetic (1-based: 1=Aries..12=Pisces)
                // e.g. from sign 1 with 7th aspect -> ((1 - 1 + 6) % 12) + 1 = 7 (Libra)
                $targetSignId = (($fromSignId - 1 + ($aspectCount - 1)) % 12) + 1;
                $targetSignEnum = Rasi::from($targetSignId - 1);

                $targetHouse = ($fromHouse !== null)
                    ? (($fromHouse - 1 + ($aspectCount - 1)) % 12) + 1
                    : null;

                $isSpecial = ($aspectCount !== 7);
                $aspectTypeName = "{$aspectCount}th";

                // Receiving planets in the aspected sign
                $receivingPlanets = $planetsBySign[$targetSignId] ?? [];

                $aspectRecord = [
                    'aspecting_planet' => $fromKey,
                    'aspecting_planet_name' => $fromPlanet['name'],
                    'aspecting_sign_id' => $fromSignId,
                    'aspecting_sign_name' => $fromPlanet['sign_name'],
                    'aspecting_house' => $fromHouse,
                    'aspect_type' => $aspectTypeName,
                    'aspect_count' => $aspectCount,
                    'is_special_aspect' => $isSpecial,
                    'aspected_sign_id' => $targetSignId,
                    'target_rashi_id' => $targetSignId,
                    'aspected_sign_name' => $targetSignEnum->getEnglishName(),
                    'aspected_sign_vedic_name' => $targetSignEnum->getName(),
                    'aspected_house' => $targetHouse,
                    'target_house' => $targetHouse,
                    'receiving_planets' => $receivingPlanets,
                ];

                $allAspects[] = $aspectRecord;
                $aspectsByPlanet[$fromKey][] = $aspectRecord;
                $aspectsByTargetSign[$targetSignId]['aspecting_planets'][] = $fromKey;
                $aspectsByTargetSign[$targetSignId]['aspects'][] = $aspectRecord;

                if ($targetHouse !== null) {
                    $aspectedHouses[$targetHouse][] = [
                        'aspecting_planet' => $fromKey,
                        'aspect_type' => $aspectTypeName,
                        'is_special' => $isSpecial,
                    ];
                }

                foreach ($receivingPlanets as $recKey) {
                    $aspectsReceivedByPlanet[$recKey][] = [
                        'aspecting_planet' => $fromKey,
                        'aspect_type' => $aspectTypeName,
                        'is_special' => $isSpecial,
                        'aspecting_house' => $fromHouse,
                    ];
                }
            }
        }

        return [
            'included_planets' => self::INCLUDED_PLANETS,
            'lagna_sign_id' => $lagnaRashiId,
            'aspects_count' => count($allAspects),
            'aspects' => $allAspects,
            'all_aspects' => $allAspects,
            'aspects_by_planet' => $aspectsByPlanet,
            'by_planet' => $aspectsByPlanet,
            'aspects_by_target_sign' => $aspectsByTargetSign,
            'by_target_sign' => $aspectsByTargetSign,
            'aspected_houses' => $aspectedHouses,
            'aspects_received_by_planet' => $aspectsReceivedByPlanet,
        ];
    }

    /**
     * Check if a specific planet casts a Parashari aspect upon a target sign.
     *
     * @param string $aspectingKey Planet key (e.g. 'mars', 'jupiter')
     * @param int $fromSignId Source sign ID (1..12)
     * @param int $targetSignId Target sign ID (1..12)
     * @return array{does_aspect: bool, aspect_type: ?string, is_special: bool}
     */
    public function doesPlanetAspectSign(string $aspectingKey, int $fromSignId, int $targetSignId): array
    {
        $offsets = self::PLANET_ASPECT_OFFSETS[$aspectingKey] ?? [];

        foreach ($offsets as $aspectCount) {
            $expectedSign = (($fromSignId - 1 + ($aspectCount - 1)) % 12) + 1;
            if ($expectedSign === $targetSignId) {
                return [
                    'does_aspect' => true,
                    'aspect_type' => "{$aspectCount}th",
                    'is_special' => ($aspectCount !== 7),
                ];
            }
        }

        return [
            'does_aspect' => false,
            'aspect_type' => null,
            'is_special' => false,
        ];
    }

    /**
     * Extract 1-based Rashi ID (1..12) from various planet array shapes.
     */
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
