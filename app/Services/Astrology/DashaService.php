<?php

declare(strict_types=1);

namespace App\Services\Astrology;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\VimshottariDasha;
use JayeshMepani\PanchangCore\Core\ZodiacDivision;

class DashaService
{
    public const TOTAL_CYCLE_YEARS = 120;
    public const DAYS_PER_SOLAR_YEAR = 365.25;

    /**
     * Sequence of Vimshottari lords starting from Ketu.
     * Each entry: [planet_key, name_en, name_hi, years]
     */
    public const VIMSHOTTARI_CYCLE = [
        ['key' => 'ketu', 'name' => 'Ketu', 'name_hi' => 'केतु', 'years' => 7],
        ['key' => 'venus', 'name' => 'Venus', 'name_hi' => 'शुक्र', 'years' => 20],
        ['key' => 'sun', 'name' => 'Sun', 'name_hi' => 'सूर्य', 'years' => 6],
        ['key' => 'moon', 'name' => 'Moon', 'name_hi' => 'चन्द्र', 'years' => 10],
        ['key' => 'mars', 'name' => 'Mars', 'name_hi' => 'मंगल', 'years' => 7],
        ['key' => 'rahu', 'name' => 'Rahu', 'name_hi' => 'राहु', 'years' => 18],
        ['key' => 'jupiter', 'name' => 'Jupiter', 'name_hi' => 'गुरु', 'years' => 16],
        ['key' => 'saturn', 'name' => 'Saturn', 'name_hi' => 'शनि', 'years' => 19],
        ['key' => 'mercury', 'name' => 'Mercury', 'name_hi' => 'बुध', 'years' => 17],
    ];

    /**
     * Compute full 120-year Vimshottari Dasha timeline from natal Moon longitude.
     *
     * @param float|null $moonLongitude Natal sidereal Moon longitude (0..360)
     * @param DateTimeInterface|string $birthDate User's birth date/time
     * @param string $precision Birth time precision ('verified', 'approximate', 'unknown')
     * @param DateTimeInterface|string|null $targetDate Date for evaluating current dasha (defaults to now)
     * @param string $locale 'en' or 'hi'
     * @return array<string, mixed>
     */
    public function calculateVimshottariTimeline(
        ?float $moonLongitude,
        DateTimeInterface|string $birthDate,
        string $precision = 'verified',
        DateTimeInterface|string|null $targetDate = null,
        string $locale = 'en'
    ): array {
        if ($moonLongitude === null || $precision === 'unknown') {
            return [
                'status' => 'unavailable',
                'reason' => 'Exact birth time is required to compute precise Vimshottari Dasha balance.',
                'current_dasha' => null,
                'mahadashas' => [],
            ];
        }

        $birth = $birthDate instanceof DateTimeInterface
            ? CarbonImmutable::instance($birthDate)
            : CarbonImmutable::parse($birthDate);

        $target = $targetDate === null
            ? CarbonImmutable::now($birth->getTimezone() ?: 'UTC')
            : ($targetDate instanceof DateTimeInterface
                ? CarbonImmutable::instance($targetDate)
                : CarbonImmutable::parse($targetDate));

        // 1. Identify Janma Nakshatra and position
        $nakshatraIndex = (int) floor($moonLongitude / ZodiacDivision::DEGREES_PER_NAKSHATRA);
        $nakshatraIndex = max(0, min(26, $nakshatraIndex));
        $nakshatraStart = $nakshatraIndex * ZodiacDivision::DEGREES_PER_NAKSHATRA;
        $degreesTraversed = $moonLongitude - $nakshatraStart;
        $fractionRemaining = max(0.0, min(1.0, (ZodiacDivision::DEGREES_PER_NAKSHATRA - $degreesTraversed) / ZodiacDivision::DEGREES_PER_NAKSHATRA));

        // 2. Lord of Janma Nakshatra (0..8 in VIMSHOTTARI_CYCLE)
        $startLordIndex = $nakshatraIndex % 9;
        $firstLordInfo = self::VIMSHOTTARI_CYCLE[$startLordIndex];
        $firstLordYears = $firstLordInfo['years'];

        // Balance of first Mahadasha at birth in years
        $balanceYearsAtBirth = $fractionRemaining * $firstLordYears;
        $elapsedYearsBeforeBirth = $firstLordYears - $balanceYearsAtBirth;

        // Theoretical start of the birth Mahadasha
        $firstMahadashaStartDays = (int) round($elapsedYearsBeforeBirth * self::DAYS_PER_SOLAR_YEAR);
        $theoreticalStart = $birth->subDays($firstMahadashaStartDays);

        // 3. Generate all 9 Mahadashas (120 years total)
        $mahadashas = [];
        $currentStart = $theoreticalStart;
        $activeMahadasha = null;
        $activeAntardasha = null;

        for ($i = 0; $i < 9; $i++) {
            $lordIdx = ($startLordIndex + $i) % 9;
            $lordData = self::VIMSHOTTARI_CYCLE[$lordIdx];
            $dashaYears = $lordData['years'];
            $durationDays = (int) round($dashaYears * self::DAYS_PER_SOLAR_YEAR);
            $currentEnd = $currentStart->addDays($durationDays);

            // Generate 9 Antardashas within this Mahadasha
            $antardashas = $this->generateAntardashas(
                mahadashaIndex: $lordIdx,
                mahadashaStart: $currentStart,
                mahadashaYears: $dashaYears,
                locale: $locale
            );

            $isCurrentMahadasha = ($target->greaterThanOrEqualTo($currentStart) && $target->lessThan($currentEnd));

            $mahadashaRecord = [
                'order' => $i + 1,
                'planet' => $lordData['key'],
                'name' => $locale === 'hi' ? $lordData['name_hi'] : $lordData['name'],
                'name_hi' => $lordData['name_hi'],
                'lord' => $lordData['name'],
                'lord_hi' => $lordData['name_hi'],
                'years' => $dashaYears,
                'duration_years' => $dashaYears,
                'start_date' => $currentStart->format('Y-m-d'),
                'end_date' => $currentEnd->format('Y-m-d'),
                'is_birth_dasha' => ($i === 0),
                'balance_at_birth_years' => ($i === 0) ? round($balanceYearsAtBirth, 4) : null,
                'is_active' => $isCurrentMahadasha,
                'is_current' => $isCurrentMahadasha,
                'antardashas' => $antardashas,
            ];

            if ($isCurrentMahadasha) {
                $activeMahadasha = [
                    'planet' => $lordData['key'],
                    'name' => $locale === 'hi' ? $lordData['name_hi'] : $lordData['name'],
                    'name_hi' => $lordData['name_hi'],
                    'lord' => $lordData['name'],
                    'lord_hi' => $lordData['name_hi'],
                    'start_date' => $currentStart->format('Y-m-d'),
                    'end_date' => $currentEnd->format('Y-m-d'),
                    'total_years' => $dashaYears,
                ];

                // Find active Antardasha
                foreach ($antardashas as $ad) {
                    $adStart = CarbonImmutable::parse($ad['start_date']);
                    $adEnd = CarbonImmutable::parse($ad['end_date']);
                    if ($target->greaterThanOrEqualTo($adStart) && $target->lessThan($adEnd)) {
                        $remainingDays = $target->diffInDays($adEnd);
                        $remYears = (int) floor($remainingDays / 365.25);
                        $remMonths = (int) floor(($remainingDays % 365.25) / 30.4375);
                        $remDays = (int) round(($remainingDays % 365.25) % 30.4375);

                        $remFormatted = $locale === 'hi'
                            ? "{$remYears} वर्ष, {$remMonths} माह, {$remDays} दिन शेष"
                            : "{$remYears}y {$remMonths}m {$remDays}d remaining";

                        $activeAntardasha = [
                            'planet' => $ad['planet'],
                            'name' => $ad['name'],
                            'name_hi' => $ad['name_hi'],
                            'lord' => $ad['name'],
                            'lord_hi' => $ad['name_hi'],
                            'start_date' => $ad['start_date'],
                            'end_date' => $ad['end_date'],
                            'remaining_days' => (int) round($remainingDays),
                            'remaining_formatted' => $remFormatted,
                        ];
                        break;
                    }
                }
            }

            $mahadashas[] = $mahadashaRecord;
            $currentStart = $currentEnd;
        }

        return [
            'status' => 'available',
            'system' => 'Vimshottari (120-Year Cycle)',
            'janma_nakshatra_lord' => $locale === 'hi' ? $firstLordInfo['name_hi'] : $firstLordInfo['name'],
            'birth_dasha_balance_years' => round($balanceYearsAtBirth, 4),
            'target_date' => $target->format('Y-m-d'),
            'current_dasha' => [
                'mahadasha' => $activeMahadasha,
                'antardasha' => $activeAntardasha,
                'remaining_formatted' => $activeAntardasha['remaining_formatted'] ?? null,
            ],
            'mahadashas' => $mahadashas,
            'disclaimer' => $locale === 'hi'
                ? 'विंशोत्तरी दशा चक्र शास्त्रीय वैदिक गणना पर आधारित है। यह समय अवधि का संकेत मात्र है, नियतिवादी भविष्यवाणी नहीं।'
                : 'Vimshottari Dasha timelines indicate traditional astrological planetary periods. They are interpretive and educational, not deterministic predictions.',
        ];
    }

    /**
     * Generate 9 Antardashas within a Mahadasha.
     * Formula: Duration(M, S) = (D_M * D_S) / 120 years.
     *
     * @return list<array<string, mixed>>
     */
    protected function generateAntardashas(
        int $mahadashaIndex,
        CarbonImmutable $mahadashaStart,
        int $mahadashaYears,
        string $locale
    ): array {
        $antardashas = [];
        $adStart = $mahadashaStart;

        for ($j = 0; $j < 9; $j++) {
            $subLordIdx = ($mahadashaIndex + $j) % 9;
            $subLordData = self::VIMSHOTTARI_CYCLE[$subLordIdx];
            $subLordYears = $subLordData['years'];

            // Duration in fractional years: (DM * DS) / 120
            $durationYears = ($mahadashaYears * $subLordYears) / (float) self::TOTAL_CYCLE_YEARS;
            $durationDays = (int) round($durationYears * self::DAYS_PER_SOLAR_YEAR);
            $adEnd = ($j === 8)
                ? $mahadashaStart->addDays((int) round($mahadashaYears * self::DAYS_PER_SOLAR_YEAR))
                : $adStart->addDays($durationDays);

            $antardashas[] = [
                'sub_order' => $j + 1,
                'planet' => $subLordData['key'],
                'name' => $locale === 'hi' ? $subLordData['name_hi'] : $subLordData['name'],
                'name_hi' => $subLordData['name_hi'],
                'duration_years' => round($durationYears, 4),
                'start_date' => $adStart->format('Y-m-d'),
                'end_date' => $adEnd->format('Y-m-d'),
            ];

            $adStart = $adEnd;
        }

        return $antardashas;
    }
}
