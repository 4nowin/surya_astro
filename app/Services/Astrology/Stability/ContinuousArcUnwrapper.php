<?php

declare(strict_types=1);

namespace App\Services\Astrology\Stability;

use Closure;
use InvalidArgumentException;

/**
 * Reusable mathematical utilities for continuous angular unwrapping,
 * stationary point detection, monotonic interval partitioning, and boundary crossing detection.
 *
 * Implements Work Package A1 of Astro Setu Milestone M9.2.
 */
class ContinuousArcUnwrapper
{
    public const DEFAULT_NUMERICAL_TOLERANCE = 1e-6;
    public const MAX_BISECTION_ITERATIONS = 60;

    /**
     * Normalize an angle to [0, 360) degrees.
     */
    public static function normalize(float $deg): float
    {
        $m = fmod($deg, 360.0);
        return $m < 0.0 ? $m + 360.0 : $m;
    }

    /**
     * Compute circular angular difference |a - b| on the circle (0 to 180 degrees).
     */
    public static function circularDifference(float $a, float $b): float
    {
        $diff = abs(self::normalize($a) - self::normalize($b));
        return $diff > 180.0 ? 360.0 - $diff : $diff;
    }

    /**
     * Unwrap an array of circular degrees [0, 360) into a continuous real-valued sequence
     * by resolving modulo 360° discontinuities.
     *
     * @param float[] $angles Sequence of circular degrees
     * @return float[] Continuous unwrapped angles
     */
    public static function unwrapSequence(array $angles): array
    {
        if (empty($angles)) {
            return [];
        }

        $unwrapped = [];
        $unwrapped[0] = (float) $angles[0];

        $count = count($angles);
        for ($i = 1; $i < $count; $i++) {
            $prevNorm = self::normalize($angles[$i - 1]);
            $currNorm = self::normalize($angles[$i]);

            $diff = $currNorm - $prevNorm;
            if ($diff > 180.0) {
                $diff -= 360.0;
            } elseif ($diff < -180.0) {
                $diff += 360.0;
            }

            $unwrapped[$i] = $unwrapped[$i - 1] + $diff;
        }

        return $unwrapped;
    }

    /**
     * Evaluates a continuous angular function over an interval [tStart, tEnd],
     * detect stationary points (where velocity changes sign), partitions into monotonic segments,
     * and finds the global minimum and maximum unwrapped coordinates.
     *
     * @param Closure(float): float $angleEvaluator Function t => circular degree in [0, 360)
     * @param Closure(float): float $velocityEvaluator Function t => motional velocity dlambda/dt
     * @param float $tStart Start timestamp/coordinate
     * @param float $tEnd End timestamp/coordinate
     * @param int $sampleCount Number of baseline evaluation intervals (min 4)
     * @return array{
     *     unwrapped_start: float,
     *     unwrapped_end: float,
     *     min_unwrapped: float,
     *     max_unwrapped: float,
     *     stationary_points: list<array{t: float, angle_unwrapped: float, angle_circular: float}>,
     *     monotonic_partitions: list<array{t_start: float, t_end: float, angle_start: float, angle_end: float, is_increasing: bool}>
     * }
     */
    public static function analyzeInterval(
        Closure $angleEvaluator,
        Closure $velocityEvaluator,
        float $tStart,
        float $tEnd,
        int $sampleCount = 24
    ): array {
        if ($tEnd < $tStart) {
            throw new InvalidArgumentException("tEnd ({$tEnd}) cannot be less than tStart ({$tStart}).");
        }

        if ($tEnd == $tStart) {
            $val = self::normalize($angleEvaluator($tStart));
            return [
                'unwrapped_start' => $val,
                'unwrapped_end' => $val,
                'min_unwrapped' => $val,
                'max_unwrapped' => $val,
                'stationary_points' => [],
                'monotonic_partitions' => [
                    [
                        't_start' => $tStart,
                        't_end' => $tEnd,
                        'angle_start' => $val,
                        'angle_end' => $val,
                        'is_increasing' => true,
                    ],
                ],
            ];
        }

        $sampleCount = max(4, $sampleCount);
        $dt = ($tEnd - $tStart) / $sampleCount;

        // 1. Grid sample timestamps, circular angles, and velocities
        $tSamples = [];
        $rawAngles = [];
        $velocities = [];

        for ($i = 0; $i <= $sampleCount; $i++) {
            $t = $tStart + $i * $dt;
            if ($i === $sampleCount) {
                $t = $tEnd; // Avoid float accumulation error
            }
            $tSamples[$i] = $t;
            $rawAngles[$i] = self::normalize($angleEvaluator($t));
            $velocities[$i] = $velocityEvaluator($t);
        }

        // 2. Continuous unwrapping across grid
        $unwrapped = self::unwrapSequence($rawAngles);

        // Continuous function to get unwrapped angle at any t in [tStart, tEnd]
        $getUnwrappedAt = function (float $t) use ($tStart, $tEnd, $angleEvaluator, $tSamples, $unwrapped, $sampleCount): float {
            if ($t <= $tStart) {
                return $unwrapped[0];
            }
            if ($t >= $tEnd) {
                return $unwrapped[$sampleCount];
            }

            // Find enclosing grid segment
            $frac = ($t - $tStart) / ($tEnd - $tStart) * $sampleCount;
            $idx = (int) floor($frac);
            $idx = max(0, min($sampleCount - 1, $idx));

            $raw = self::normalize($angleEvaluator($t));
            $prevUnwrapped = $unwrapped[$idx];
            $prevRaw = self::normalize($tSamples[$idx] == $t ? $raw : $angleEvaluator($tSamples[$idx]));

            $diff = $raw - $prevRaw;
            if ($diff > 180.0) {
                $diff -= 360.0;
            } elseif ($diff < -180.0) {
                $diff += 360.0;
            }

            return $prevUnwrapped + $diff;
        };

        // 3. Find stationary points where velocity changes sign: v(t1) * v(t2) <= 0
        $stationaryPoints = [];
        for ($i = 0; $i < $sampleCount; $i++) {
            $v1 = $velocities[$i];
            $v2 = $velocities[$i + 1];

            // Direct zero hit at start
            if (abs($v1) < self::DEFAULT_NUMERICAL_TOLERANCE && $i > 0) {
                $tZero = $tSamples[$i];
                $stationaryPoints[] = [
                    't' => $tZero,
                    'angle_unwrapped' => $unwrapped[$i],
                    'angle_circular' => self::normalize($unwrapped[$i]),
                ];
                continue;
            }

            // Sign change between t[i] and t[i+1]
            if (($v1 > 0 && $v2 < 0) || ($v1 < 0 && $v2 > 0)) {
                $tStation = self::bisectRoot($velocityEvaluator, $tSamples[$i], $tSamples[$i + 1]);
                $angleUnwrappedStation = $getUnwrappedAt($tStation);
                $stationaryPoints[] = [
                    't' => $tStation,
                    'angle_unwrapped' => $angleUnwrappedStation,
                    'angle_circular' => self::normalize($angleUnwrappedStation),
                ];
            }
        }

        // 4. Partition interval at critical points (start, stationary points, end)
        $partitionTimestamps = [$tStart];
        foreach ($stationaryPoints as $sp) {
            if ($sp['t'] > $tStart + self::DEFAULT_NUMERICAL_TOLERANCE && $sp['t'] < $tEnd - self::DEFAULT_NUMERICAL_TOLERANCE) {
                $partitionTimestamps[] = $sp['t'];
            }
        }
        $partitionTimestamps[] = $tEnd;
        sort($partitionTimestamps);

        // Deduplicate very close partition points
        $cleanPartitions = [];
        foreach ($partitionTimestamps as $pt) {
            if (empty($cleanPartitions) || abs($pt - end($cleanPartitions)) > self::DEFAULT_NUMERICAL_TOLERANCE) {
                $cleanPartitions[] = $pt;
            }
        }

        // 5. Build monotonic segments and evaluate global extrema
        $monotonicPartitions = [];
        $pCount = count($cleanPartitions);
        $minUnwrapped = $unwrapped[0];
        $maxUnwrapped = $unwrapped[0];

        for ($p = 0; $p < $pCount - 1; $p++) {
            $pStart = $cleanPartitions[$p];
            $pEnd = $cleanPartitions[$p + 1];

            $aStart = $getUnwrappedAt($pStart);
            $aEnd = $getUnwrappedAt($pEnd);

            $minUnwrapped = min($minUnwrapped, $aStart, $aEnd);
            $maxUnwrapped = max($maxUnwrapped, $aStart, $aEnd);

            $monotonicPartitions[] = [
                't_start' => $pStart,
                't_end' => $pEnd,
                'angle_start' => $aStart,
                'angle_end' => $aEnd,
                'is_increasing' => ($aEnd >= $aStart),
            ];
        }

        return [
            'unwrapped_start' => $unwrapped[0],
            'unwrapped_end' => end($unwrapped),
            'min_unwrapped' => $minUnwrapped,
            'max_unwrapped' => $maxUnwrapped,
            'stationary_points' => $stationaryPoints,
            'monotonic_partitions' => $monotonicPartitions,
        ];
    }

    /**
     * Detect all boundary crossings of a given division step size (e.g. 30° for Rashi, 3°20' for Navamsha)
     * over an analyzed interval.
     *
     * @param array $analyzedInterval Output of analyzeInterval()
     * @param Closure(float): float $angleEvaluator Function t => circular degree in [0, 360)
     * @param float $stepDegrees Angular step size (e.g. 30.0 for Rashi, 13.3333333333 for Nakshatra, 3.3333333333 for Navamsha)
     * @return array{
     *     is_invariant: bool,
     *     step_degrees: float,
     *     min_index: int,
     *     max_index: int,
     *     crossings: list<array{
     *         t: float,
     *         boundary_index: int,
     *         boundary_degrees: float,
     *         from_index: int,
     *         to_index: int,
     *         direction: string
     *     }>
     * }
     */
    public static function detectBoundaryCrossings(
        array $analyzedInterval,
        Closure $angleEvaluator,
        float $stepDegrees
    ): array {
        if ($stepDegrees <= 0.0) {
            throw new InvalidArgumentException("stepDegrees must be positive.");
        }

        $minUnwrapped = $analyzedInterval['min_unwrapped'];
        $maxUnwrapped = $analyzedInterval['max_unwrapped'];

        $minIdx = (int) floor($minUnwrapped / $stepDegrees);
        $maxIdx = (int) floor($maxUnwrapped / $stepDegrees);

        $isInvariant = ($minIdx === $maxIdx);
        $crossings = [];

        if (!$isInvariant) {
            // Find crossings within each monotonic segment
            foreach ($analyzedInterval['monotonic_partitions'] as $segment) {
                $tStart = $segment['t_start'];
                $tEnd = $segment['t_end'];
                $aStart = $segment['angle_start'];
                $aEnd = $segment['angle_end'];

                $idxStart = (int) floor($aStart / $stepDegrees);
                $idxEnd = (int) floor($aEnd / $stepDegrees);

                if ($idxStart === $idxEnd) {
                    continue;
                }

                if ($segment['is_increasing']) {
                    for ($bIdx = $idxStart + 1; $bIdx <= $idxEnd; $bIdx++) {
                        $targetUnwrapped = $bIdx * $stepDegrees;
                        $tCross = self::bisectUnwrappedValue(
                            $angleEvaluator,
                            $tStart,
                            $tEnd,
                            $aStart,
                            $aEnd,
                            $targetUnwrapped
                        );

                        $crossings[] = [
                            't' => $tCross,
                            'boundary_index' => $bIdx,
                            'boundary_degrees' => self::normalize($targetUnwrapped),
                            'from_index' => $bIdx - 1,
                            'to_index' => $bIdx,
                            'direction' => 'forward',
                        ];
                    }
                } else {
                    for ($bIdx = $idxStart; $bIdx > $idxEnd; $bIdx--) {
                        $targetUnwrapped = $bIdx * $stepDegrees;
                        $tCross = self::bisectUnwrappedValue(
                            $angleEvaluator,
                            $tStart,
                            $tEnd,
                            $aStart,
                            $aEnd,
                            $targetUnwrapped
                        );

                        $crossings[] = [
                            't' => $tCross,
                            'boundary_index' => $bIdx,
                            'boundary_degrees' => self::normalize($targetUnwrapped),
                            'from_index' => $bIdx,
                            'to_index' => $bIdx - 1,
                            'direction' => 'reverse',
                        ];
                    }
                }
            }
        }

        return [
            'is_invariant' => $isInvariant,
            'step_degrees' => $stepDegrees,
            'min_index' => $minIdx,
            'max_index' => $maxIdx,
            'crossings' => $crossings,
        ];
    }

    /**
     * Bisection root finding to solve f(t) = 0 in [tA, tB] where f(tA) * f(tB) <= 0.
     *
     * @param Closure(float): float $fn Continuous scalar function
     * @param float $tA Left endpoint
     * @param float $tB Right endpoint
     * @param float $tolerance Numerical tolerance
     * @param float|null $residualTolerance Optional maximum residual tolerance for root verification
     * @return float
     * @throws InvalidArgumentException If [tA, tB] does not bracket a root (f(tA) * f(tB) > 0)
     * @throws RuntimeException If bisection fails to converge to an actual root within tolerance
     */
    public static function bisectRoot(
        Closure $fn,
        float $tA,
        float $tB,
        float $tolerance = self::DEFAULT_NUMERICAL_TOLERANCE,
        ?float $residualTolerance = null
    ): float {
        $fA = $fn($tA);
        $fB = $fn($tB);

        if (abs($fA) <= $tolerance) {
            return $tA;
        }
        if (abs($fB) <= $tolerance) {
            return $tB;
        }

        // Validate bracket: f(tA) and f(tB) must have opposite signs
        if (($fA > 0.0 && $fB > 0.0) || ($fA < 0.0 && $fB < 0.0)) {
            throw new InvalidArgumentException(
                sprintf("Interval [%.6f, %.6f] does not bracket a root (f(tA) = %.6f, f(tB) = %.6f).", $tA, $tB, $fA, $fB)
            );
        }

        $low = $tA;
        $high = $tB;

        for ($iter = 0; $iter < self::MAX_BISECTION_ITERATIONS; $iter++) {
            $mid = ($low + $high) / 2.0;
            $fMid = $fn($mid);

            if (abs($fMid) <= $tolerance || ($high - $low) / 2.0 <= $tolerance) {
                return $mid;
            }

            if (($fA > 0.0 && $fMid > 0.0) || ($fA < 0.0 && $fMid < 0.0)) {
                $low = $mid;
                $fA = $fMid;
            } else {
                $high = $mid;
            }
        }

        $finalMid = ($low + $high) / 2.0;
        $finalResidual = abs($fn($finalMid));
        $maxAllowedResidual = $residualTolerance ?? max(1e-3, $tolerance * 100.0);

        if ($finalResidual > $maxAllowedResidual && abs($fA) > $maxAllowedResidual && abs($fB) > $maxAllowedResidual) {
            throw new RuntimeException(
                sprintf("Bisection root finding failed to converge to a root within tolerance in [%.6f, %.6f] (residual = %.6f).", $tA, $tB, $finalResidual)
            );
        }

        return $finalMid;
    }

    /**
     * Bisection root finding to solve unwrapped_angle(t) = targetUnwrapped in monotonic segment [tStart, tEnd].
     */
    protected static function bisectUnwrappedValue(
        Closure $angleEvaluator,
        float $tStart,
        float $tEnd,
        float $aStartUnwrapped,
        float $aEndUnwrapped,
        float $targetUnwrapped
    ): float {
        // If the monotonic interval spans a large range (e.g. 24h civil day unknown time spanning 360°),
        // we first narrow down to a sub-bracket [subA, subB] where angle change is strictly <= 15°
        $subA = $tStart;
        $subB = $tEnd;
        $subAUnwrapped = $aStartUnwrapped;

        $totalArc = max(1.0, abs($aEndUnwrapped - $aStartUnwrapped));
        $steps = max(1, (int) ceil($totalArc / 15.0));

        if ($steps > 1) {
            $dt = ($tEnd - $tStart) / $steps;
            $prevT = $tStart;
            $prevUnwrapped = $aStartUnwrapped;
            $prevRaw = self::normalize($angleEvaluator($tStart));

            for ($s = 1; $s <= $steps; $s++) {
                $currT = ($s === $steps) ? $tEnd : ($tStart + $s * $dt);
                $currRaw = self::normalize($angleEvaluator($currT));
                $d = $currRaw - $prevRaw;
                if ($d > 180.0) {
                    $d -= 360.0;
                } elseif ($d < -180.0) {
                    $d += 360.0;
                }
                $currUnwrapped = $prevUnwrapped + $d;

                // Check if target is bracketed in [prevUnwrapped, currUnwrapped]
                if (($prevUnwrapped <= $targetUnwrapped && $targetUnwrapped <= $currUnwrapped) ||
                    ($currUnwrapped <= $targetUnwrapped && $targetUnwrapped <= $prevUnwrapped)) {
                    $subA = $prevT;
                    $subB = $currT;
                    $subAUnwrapped = $prevUnwrapped;
                    break;
                }

                $prevT = $currT;
                $prevUnwrapped = $currUnwrapped;
                $prevRaw = $currRaw;
            }
        }

        $rawStart = self::normalize($angleEvaluator($subA));

        $fn = function (float $t) use ($angleEvaluator, $subA, $rawStart, $subAUnwrapped, $targetUnwrapped): float {
            $raw = self::normalize($angleEvaluator($t));
            $diff = $raw - $rawStart;
            if ($diff > 180.0) {
                $diff -= 360.0;
            } elseif ($diff < -180.0) {
                $diff += 360.0;
            }
            $currentUnwrapped = $subAUnwrapped + $diff;
            return $currentUnwrapped - $targetUnwrapped;
        };

        return self::bisectRoot($fn, $subA, $subB);
    }
}
