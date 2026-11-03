<?php

declare(strict_types=1);

namespace App\Services\Astrology\Stability;

use App\Services\Astrology\DashaService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\ZodiacDivision;

/**
 * Service for calculating Vimshottari Dasha uncertainty bounds and Pratyantardasha period-boundary reliability.
 *
 * Implements Work Package A1 of Astro Setu Milestone M9.2.
 */
class VimshottariUncertaintyService
{
    public const TOTAL_CYCLE_YEARS = 120.0;
    public const DAYS_PER_SOLAR_YEAR = 365.25;
    public const RELIABILITY_THRESHOLD_FRACTION = 0.25; // 25% threshold

    protected DashaService $dashaService;

    public function __construct(?DashaService $dashaService = null)
    {
        $this->dashaService = $dashaService ?? app(DashaService::class);
    }

    /**
     * Compute Vimshottari timeline with calculated uncertainty date intervals.
     *
     * @param float[] $moonLongitudes Sampled Moon longitudes across the uncertainty interval
     * @param CarbonImmutable $birthStart Earliest possible birth timestamp
     * @param CarbonImmutable $birthEnd Latest possible birth timestamp
     * @param CarbonImmutable $birthMid Nominal midpoint birth timestamp
     * @param DateTimeInterface|string|null $targetDate Date for evaluating active periods
     * @param string $locale 'en' or 'hi'
     * @return array<string, mixed>
     */
    public function calculateWithUncertainty(
        array $moonLongitudes,
        CarbonImmutable $birthStart,
        CarbonImmutable $birthEnd,
        CarbonImmutable $birthMid,
        DateTimeInterface|string|null $targetDate = null,
        string $locale = 'en'
    ): array {
        if (empty($moonLongitudes)) {
            return [
                'status' => 'unavailable',
                'reason' => 'No Moon longitude samples available across birth interval.',
            ];
        }

        $target = $targetDate === null
            ? CarbonImmutable::now($birthMid->getTimezone() ?: 'UTC')
            : ($targetDate instanceof DateTimeInterface
                ? CarbonImmutable::instance($targetDate)
                : CarbonImmutable::parse((string) $targetDate));

        // 1. Continuous unwrapping of Moon longitudes to handle 0°/360° circular boundary
        $unwrapped = ContinuousArcUnwrapper::unwrapSequence($moonLongitudes);

        // 2. Check Nakshatra invariance across samples
        $nakshatraIndices = [];
        foreach ($unwrapped as $uLon) {
            $norm = ContinuousArcUnwrapper::normalize($uLon);
            $idx = (int) floor($norm / ZodiacDivision::DEGREES_PER_NAKSHATRA);
            $nakshatraIndices[] = max(0, min(26, $idx));
        }

        $minNakIdx = min($nakshatraIndices);
        $maxNakIdx = max($nakshatraIndices);

        if ($minNakIdx !== $maxNakIdx) {
            $nak1Enum = Nakshatra::from($minNakIdx);
            $nak2Enum = Nakshatra::from($maxNakIdx);

            return [
                'status' => 'uncertain_nakshatra_transition',
                'is_invariant' => false,
                'reason' => 'Moon crosses Nakshatra boundary within uncertainty interval (' .
                    $nak1Enum->name . ' to ' . $nak2Enum->name . '). Multiple distinct Dasha sequences exist.',
                'candidate_nakshatras' => [
                    ['id' => $minNakIdx + 1, 'name' => $nak1Enum->name, 'vedic_name' => $nak1Enum->getName()],
                    ['id' => $maxNakIdx + 1, 'name' => $nak2Enum->name, 'vedic_name' => $nak2Enum->getName()],
                ],
                'current_dasha' => null,
                'mahadashas' => [],
            ];
        }

        $nakshatraIndex = $minNakIdx;
        $nakEnum = Nakshatra::from($nakshatraIndex);

        // 3. Calculate relative elapsed arc within the invariant Nakshatra
        $relativeArcs = [];
        foreach ($unwrapped as $uLon) {
            $norm = ContinuousArcUnwrapper::normalize($uLon);
            $startOfNak = $nakshatraIndex * ZodiacDivision::DEGREES_PER_NAKSHATRA;
            $arc = $norm - $startOfNak;
            if ($arc < 0.0) {
                $arc += 360.0;
            }
            $relativeArcs[] = $arc;
        }

        $minElapsedArc = min($relativeArcs);
        $maxElapsedArc = max($relativeArcs);
        $midNorm = ContinuousArcUnwrapper::normalize($unwrapped[(int) floor(count($unwrapped) / 2)]);
        $midElapsedArc = $midNorm - ($nakshatraIndex * ZodiacDivision::DEGREES_PER_NAKSHATRA);
        if ($midElapsedArc < 0.0) {
            $midElapsedArc += 360.0;
        }

        // 4. Calculate remaining Mahadasha balance at birth
        $startLordIndex = $nakshatraIndex % 9;
        $firstLordInfo = DashaService::VIMSHOTTARI_CYCLE[$startLordIndex];
        $firstLordYears = $firstLordInfo['years'];

        // Maximum remaining balance corresponds to minimum elapsed arc
        $maxRemainingFraction = (ZodiacDivision::DEGREES_PER_NAKSHATRA - $minElapsedArc) / ZodiacDivision::DEGREES_PER_NAKSHATRA;
        // Minimum remaining balance corresponds to maximum elapsed arc
        $minRemainingFraction = (ZodiacDivision::DEGREES_PER_NAKSHATRA - $maxElapsedArc) / ZodiacDivision::DEGREES_PER_NAKSHATRA;
        $midRemainingFraction = (ZodiacDivision::DEGREES_PER_NAKSHATRA - $midElapsedArc) / ZodiacDivision::DEGREES_PER_NAKSHATRA;

        $maxBalanceYears = $maxRemainingFraction * $firstLordYears;
        $minBalanceYears = $minRemainingFraction * $firstLordYears;
        $midBalanceYears = $midRemainingFraction * $firstLordYears;

        // Theoretical birth Mahadasha start bounds
        // Earliest theoretical start = birthStart - (elapsed years for max balance)
        $minElapsedYears = $firstLordYears - $maxBalanceYears;
        $maxElapsedYears = $firstLordYears - $minBalanceYears;
        $midElapsedYears = $firstLordYears - $midBalanceYears;

        $nominalStart = $birthMid->subDays((int) round($midElapsedYears * self::DAYS_PER_SOLAR_YEAR));
        $earliestStart = $birthStart->subDays((int) round($maxElapsedYears * self::DAYS_PER_SOLAR_YEAR));
        $latestStart = $birthEnd->subDays((int) round($minElapsedYears * self::DAYS_PER_SOLAR_YEAR));

        // 5. Generate Mahadashas with date uncertainty bounds
        $mahadashas = [];
        $currNominalStart = $nominalStart;
        $currEarliestStart = $earliestStart;
        $currLatestStart = $latestStart;

        $activeMahadasha = null;
        $activeAntardasha = null;
        $activePratyantardasha = null;

        for ($i = 0; $i < 9; $i++) {
            $lordIdx = ($startLordIndex + $i) % 9;
            $lordData = DashaService::VIMSHOTTARI_CYCLE[$lordIdx];
            $dashaYears = $lordData['years'];
            $durationDays = (int) round($dashaYears * self::DAYS_PER_SOLAR_YEAR);

            $currNominalEnd = $currNominalStart->addDays($durationDays);
            $currEarliestEnd = $currEarliestStart->addDays($durationDays);
            $currLatestEnd = $currLatestStart->addDays($durationDays);

            // Generate Antardashas with uncertainty
            $antardashas = $this->generateAntardashasWithUncertainty(
                mahadashaIndex: $lordIdx,
                nominalStart: $currNominalStart,
                earliestStart: $currEarliestStart,
                latestStart: $currLatestStart,
                mahadashaYears: $dashaYears,
                target: $target,
                locale: $locale
            );

            $isCurrent = ($target->greaterThanOrEqualTo($currEarliestStart) && $target->lessThan($currLatestEnd));

            $mahadashaRecord = [
                'order' => $i + 1,
                'planet' => $lordData['key'],
                'name' => $locale === 'hi' ? $lordData['name_hi'] : $lordData['name'],
                'name_hi' => $lordData['name_hi'],
                'lord' => $lordData['name'],
                'lord_hi' => $lordData['name_hi'],
                'years' => $dashaYears,
                'start_date' => $currNominalStart->format('Y-m-d'),
                'end_date' => $currNominalEnd->format('Y-m-d'),
                'uncertainty_bounds' => [
                    'start_earliest' => $currEarliestStart->format('Y-m-d'),
                    'start_latest' => $currLatestStart->format('Y-m-d'),
                    'end_earliest' => $currEarliestEnd->format('Y-m-d'),
                    'end_latest' => $currLatestEnd->format('Y-m-d'),
                    'date_uncertainty_days' => (int) round(abs($currLatestStart->diffInDays($currEarliestStart))),
                ],
                'is_birth_dasha' => ($i === 0),
                'balance_at_birth_years' => ($i === 0) ? round($midBalanceYears, 4) : null,
                'balance_bounds_years' => ($i === 0) ? [
                    'min' => round($minBalanceYears, 4),
                    'max' => round($maxBalanceYears, 4),
                ] : null,
                'is_active' => $isCurrent,
                'antardashas' => $antardashas['list'],
            ];

            if ($isCurrent) {
                $activeMahadasha = $mahadashaRecord;
                if ($antardashas['active'] !== null) {
                    $activeAntardasha = $antardashas['active'];
                    $activePratyantardasha = $antardashas['active_prat'];
                }
            }

            $mahadashas[] = $mahadashaRecord;
            $currNominalStart = $currNominalEnd;
            $currEarliestStart = $currEarliestEnd;
            $currLatestStart = $currLatestEnd;
        }

        return [
            'status' => 'available',
            'is_invariant' => true,
            'janma_nakshatra' => [
                'id' => $nakshatraIndex + 1,
                'index' => $nakshatraIndex,
                'name' => $nakEnum->name,
                'vedic_name' => $nakEnum->getName(),
                'lord' => $firstLordInfo['name'],
                'lord_hi' => $firstLordInfo['name_hi'],
            ],
            'birth_balance' => [
                'nominal_years' => round($midBalanceYears, 4),
                'min_years' => round($minBalanceYears, 4),
                'max_years' => round($maxBalanceYears, 4),
                'elapsed_arc_bounds_deg' => [
                    'min' => round($minElapsedArc, 4),
                    'max' => round($maxElapsedArc, 4),
                ],
            ],
            'current_dasha' => [
                'mahadasha' => $activeMahadasha ? [
                    'planet' => $activeMahadasha['planet'],
                    'name' => $activeMahadasha['name'],
                    'name_hi' => $activeMahadasha['name_hi'],
                    'start_date' => $activeMahadasha['start_date'],
                    'end_date' => $activeMahadasha['end_date'],
                    'uncertainty_bounds' => $activeMahadasha['uncertainty_bounds'],
                ] : null,
                'antardasha' => $activeAntardasha,
                'pratyantardasha' => $activePratyantardasha,
            ],
            'mahadashas' => $mahadashas,
        ];
    }

    /**
     * Generate 9 Antardashas and evaluate active Pratyantardashas with period-boundary uncertainty.
     */
    protected function generateAntardashasWithUncertainty(
        int $mahadashaIndex,
        CarbonImmutable $nominalStart,
        CarbonImmutable $earliestStart,
        CarbonImmutable $latestStart,
        int $mahadashaYears,
        CarbonImmutable $target,
        string $locale
    ): array {
        $antardashasList = [];
        $activeAd = null;
        $activePrat = null;

        $adNominal = $nominalStart;
        $adEarliest = $earliestStart;
        $adLatest = $latestStart;

        for ($j = 0; $j < 9; $j++) {
            $subLordIdx = ($mahadashaIndex + $j) % 9;
            $subLordData = DashaService::VIMSHOTTARI_CYCLE[$subLordIdx];
            $subLordYears = $subLordData['years'];

            $durationYears = ($mahadashaYears * $subLordYears) / self::TOTAL_CYCLE_YEARS;
            $durationDays = (int) round($durationYears * self::DAYS_PER_SOLAR_YEAR);

            $adNominalEnd = $adNominal->addDays($durationDays);
            $adEarliestEnd = $adEarliest->addDays($durationDays);
            $adLatestEnd = $adLatest->addDays($durationDays);

            $isCurrentAd = ($target->greaterThanOrEqualTo($adEarliest) && $target->lessThan($adLatestEnd));

            $adRecord = [
                'sub_order' => $j + 1,
                'planet' => $subLordData['key'],
                'name' => $locale === 'hi' ? $subLordData['name_hi'] : $subLordData['name'],
                'name_hi' => $subLordData['name_hi'],
                'duration_years' => round($durationYears, 4),
                'start_date' => $adNominal->format('Y-m-d'),
                'end_date' => $adNominalEnd->format('Y-m-d'),
                'uncertainty_bounds' => [
                    'start_earliest' => $adEarliest->format('Y-m-d'),
                    'start_latest' => $adLatest->format('Y-m-d'),
                    'end_earliest' => $adEarliestEnd->format('Y-m-d'),
                    'end_latest' => $adLatestEnd->format('Y-m-d'),
                ],
                'is_active' => $isCurrentAd,
            ];

            if ($isCurrentAd) {
                $activeAd = $adRecord;
                $activePrat = $this->evaluateActivePratyantardasha(
                    mahaIndex: $mahadashaIndex,
                    antarIndex: $subLordIdx,
                    adNominalStart: $adNominal,
                    adEarliestStart: $adEarliest,
                    adLatestStart: $adLatest,
                    mahaYears: $mahadashaYears,
                    antarYears: $subLordYears,
                    target: $target,
                    locale: $locale
                );
            }

            $antardashasList[] = $adRecord;
            $adNominal = $adNominalEnd;
            $adEarliest = $adEarliestEnd;
            $adLatest = $adLatestEnd;
        }

        return [
            'list' => $antardashasList,
            'active' => $activeAd,
            'active_prat' => $activePrat,
        ];
    }

    /**
     * Evaluate active Pratyantardasha and apply the period-boundary uncertainty reliability rule:
     *
     * W_start = latest possible start - earliest possible start
     * W_end = latest possible end - earliest possible end
     * W_period = max(W_start, W_end)
     * D_Prat = nominal end - nominal start
     * Rule: W_period <= 0.25 * D_Prat
     */
    protected function evaluateActivePratyantardasha(
        int $mahaIndex,
        int $antarIndex,
        CarbonImmutable $adNominalStart,
        CarbonImmutable $adEarliestStart,
        CarbonImmutable $adLatestStart,
        int $mahaYears,
        int $antarYears,
        CarbonImmutable $target,
        string $locale
    ): ?array {
        $pratNominal = $adNominalStart;
        $pratEarliest = $adEarliestStart;
        $pratLatest = $adLatestStart;

        for ($k = 0; $k < 9; $k++) {
            $pratLordIdx = ($antarIndex + $k) % 9;
            $pratLordData = DashaService::VIMSHOTTARI_CYCLE[$pratLordIdx];
            $pratLordYears = $pratLordData['years'];

            // Pratyantardasha duration: (D_Maha * D_Antar * D_Prat) / (120 * 120) * 365.25 days
            $durationYears = ($mahaYears * $antarYears * $pratLordYears) / (self::TOTAL_CYCLE_YEARS * self::TOTAL_CYCLE_YEARS);
            $durationDays = max(1.0, $durationYears * self::DAYS_PER_SOLAR_YEAR);

            $pratNominalEnd = $pratNominal->addDays((int) round($durationDays));
            $pratEarliestEnd = $pratEarliest->addDays((int) round($durationDays));
            $pratLatestEnd = $pratLatest->addDays((int) round($durationDays));

            if ($target->greaterThanOrEqualTo($pratEarliest) && $target->lessThan($pratLatestEnd)) {
                // Period-boundary uncertainty calculation
                $wStartDays = abs($pratLatest->diffInDays($pratEarliest));
                $wEndDays = abs($pratLatestEnd->diffInDays($pratEarliestEnd));
                $wPeriodDays = max($wStartDays, $wEndDays);

                $dPratDays = max(1.0, abs($pratNominalEnd->diffInDays($pratNominal)));
                $uncertaintyRatio = $wPeriodDays / $dPratDays;

                $isReliable = ($uncertaintyRatio <= self::RELIABILITY_THRESHOLD_FRACTION);

                return [
                    'planet' => $pratLordData['key'],
                    'name' => $locale === 'hi' ? $pratLordData['name_hi'] : $pratLordData['name'],
                    'name_hi' => $pratLordData['name_hi'],
                    'start_date' => $pratNominal->format('Y-m-d'),
                    'end_date' => $pratNominalEnd->format('Y-m-d'),
                    'nominal_duration_days' => (int) round($dPratDays),
                    'period_uncertainty_days' => (int) round($wPeriodDays),
                    'uncertainty_ratio' => round($uncertaintyRatio, 4),
                    'is_reliable' => $isReliable,
                    'is_suppressed' => !$isReliable,
                    'uncertainty_bounds' => [
                        'start_earliest' => $pratEarliest->format('Y-m-d'),
                        'start_latest' => $pratLatest->format('Y-m-d'),
                        'end_earliest' => $pratEarliestEnd->format('Y-m-d'),
                        'end_latest' => $pratLatestEnd->format('Y-m-d'),
                        'w_start_days' => (int) round($wStartDays),
                        'w_end_days' => (int) round($wEndDays),
                        'w_period_days' => (int) round($wPeriodDays),
                        'uncertainty_ratio' => round($uncertaintyRatio, 4),
                    ],
                    'status' => $isReliable ? 'confirmed' : 'suppressed_due_to_uncertainty',
                    'reliability_rule' => 'W_period <= 0.25 * D_Prat',
                ];
            }

            $pratNominal = $pratNominalEnd;
            $pratEarliest = $pratEarliestEnd;
            $pratLatest = $pratLatestEnd;
        }

        return null;
    }
}
