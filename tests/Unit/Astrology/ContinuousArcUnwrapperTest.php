<?php

declare(strict_types=1);

namespace Tests\Unit\Astrology;

use App\Services\Astrology\Stability\ContinuousArcUnwrapper;
use PHPUnit\Framework\TestCase;

class ContinuousArcUnwrapperTest extends TestCase
{
    /**
     * Test normal angle normalization to [0, 360).
     */
    public function test_normalization(): void
    {
        $this->assertEquals(0.0, ContinuousArcUnwrapper::normalize(0.0));
        $this->assertEquals(0.0, ContinuousArcUnwrapper::normalize(360.0));
        $this->assertEquals(0.0, ContinuousArcUnwrapper::normalize(720.0));
        $this->assertEquals(359.0, ContinuousArcUnwrapper::normalize(-1.0));
        $this->assertEquals(180.0, ContinuousArcUnwrapper::normalize(-180.0));
        $this->assertEquals(90.0, ContinuousArcUnwrapper::normalize(450.0));
    }

    /**
     * Test circular angular difference.
     */
    public function test_circular_difference(): void
    {
        $this->assertEquals(1.0, ContinuousArcUnwrapper::circularDifference(359.5, 0.5));
        $this->assertEquals(1.0, ContinuousArcUnwrapper::circularDifference(0.5, 359.5));
        $this->assertEquals(180.0, ContinuousArcUnwrapper::circularDifference(0.0, 180.0));
        $this->assertEquals(180.0, ContinuousArcUnwrapper::circularDifference(10.0, 190.0));
        $this->assertEquals(90.0, ContinuousArcUnwrapper::circularDifference(10.0, 100.0));
    }

    /**
     * Test continuous unwrapping forward across 359° to 1°.
     */
    public function test_continuous_unwrapping_forward_across_zero(): void
    {
        $samples = [358.0, 359.0, 0.0, 1.0, 2.0];
        $unwrapped = ContinuousArcUnwrapper::unwrapSequence($samples);

        $this->assertCount(5, $unwrapped);
        $this->assertEquals(358.0, $unwrapped[0]);
        $this->assertEquals(359.0, $unwrapped[1]);
        $this->assertEquals(360.0, $unwrapped[2]);
        $this->assertEquals(361.0, $unwrapped[3]);
        $this->assertEquals(362.0, $unwrapped[4]);
    }

    /**
     * Test continuous unwrapping reverse across 1° to 359°.
     */
    public function test_continuous_unwrapping_reverse_across_zero(): void
    {
        $samples = [2.0, 1.0, 0.0, 359.0, 358.0];
        $unwrapped = ContinuousArcUnwrapper::unwrapSequence($samples);

        $this->assertCount(5, $unwrapped);
        $this->assertEquals(2.0, $unwrapped[0]);
        $this->assertEquals(1.0, $unwrapped[1]);
        $this->assertEquals(0.0, $unwrapped[2]);
        $this->assertEquals(-1.0, $unwrapped[3]);
        $this->assertEquals(-2.0, $unwrapped[4]);
    }

    /**
     * Test stationary point detection where planet reverses direction.
     * E.g. planet moves direct, slows to 0 at t=5.0, then retrogrades.
     */
    public function test_stationary_point_detection_and_interval_partitioning(): void
    {
        // Trajectory: lambda(t) = 100 + 10*t - t^2
        // Velocity: v(t) = 10 - 2*t
        // Stationary point at t = 5.0, where lambda(5) = 100 + 50 - 25 = 125.0 (maximum)
        $evaluator = function (float $t): float {
            return 100.0 + 10.0 * $t - ($t * $t);
        };
        $velocity = function (float $t): float {
            return 10.0 - 2.0 * $t;
        };

        $result = ContinuousArcUnwrapper::analyzeInterval($evaluator, $velocity, 0.0, 10.0, 20);

        // Should detect stationary point near t = 5.0
        $this->assertCount(1, $result['stationary_points']);
        $sp = $result['stationary_points'][0];
        $this->assertEqualsWithDelta(5.0, $sp['t'], 1e-4);
        $this->assertEqualsWithDelta(125.0, $sp['angle_unwrapped'], 1e-4);

        // Monotonic partitions: [0, 5] increasing, [5, 10] decreasing
        $this->assertCount(2, $result['monotonic_partitions']);
        $this->assertTrue($result['monotonic_partitions'][0]['is_increasing']);
        $this->assertFalse($result['monotonic_partitions'][1]['is_increasing']);

        // Min and max unwrapped
        // At t=0: 100, t=5: 125, t=10: 100
        $this->assertEqualsWithDelta(100.0, $result['min_unwrapped'], 1e-4);
        $this->assertEqualsWithDelta(125.0, $result['max_unwrapped'], 1e-4);
    }

    /**
     * Test boundary crossing detection with bisection refinement.
     */
    public function test_boundary_crossing_detection(): void
    {
        // Angle increases monotonically from 25° to 35° between t=0 and t=10
        // Crosses 30° Rashi boundary at t = 5.0
        $evaluator = function (float $t): float {
            return 25.0 + $t;
        };
        $velocity = function (float $t): float {
            return 1.0;
        };

        $analyzed = ContinuousArcUnwrapper::analyzeInterval($evaluator, $velocity, 0.0, 10.0, 10);
        $boundaries = ContinuousArcUnwrapper::detectBoundaryCrossings($analyzed, $evaluator, 30.0);

        $this->assertFalse($boundaries['is_invariant']);
        $this->assertCount(1, $boundaries['crossings']);

        $crossing = $boundaries['crossings'][0];
        $this->assertEqualsWithDelta(5.0, $crossing['t'], 1e-4);
        $this->assertEquals(1, $crossing['boundary_index']);
        $this->assertEquals(0, $crossing['from_index']);
        $this->assertEquals(1, $crossing['to_index']);
        $this->assertEquals('forward', $crossing['direction']);
    }

    /**
     * Test invariant interval when no boundary is crossed.
     */
    public function test_invariant_interval_detection(): void
    {
        // Angle varies between 32° and 38° in Rashi 1 (Taurus: 30° to 60°)
        $evaluator = function (float $t): float {
            return 35.0 + 3.0 * sin($t);
        };
        $velocity = function (float $t): float {
            return 3.0 * cos($t);
        };

        $analyzed = ContinuousArcUnwrapper::analyzeInterval($evaluator, $velocity, 0.0, 6.28318, 20);
        $boundaries = ContinuousArcUnwrapper::detectBoundaryCrossings($analyzed, $evaluator, 30.0);

        $this->assertTrue($boundaries['is_invariant']);
        $this->assertEmpty($boundaries['crossings']);
        $this->assertEquals(1, $boundaries['min_index']);
        $this->assertEquals(1, $boundaries['max_index']);
    }

    /**
     * Test bisectRoot with a valid bracket.
     */
    public function test_bisect_root_with_valid_bracket(): void
    {
        // f(x) = x^2 - 4 on [1, 3], root at x = 2.0
        $fn = fn(float $x): float => ($x * $x) - 4.0;
        $root = ContinuousArcUnwrapper::bisectRoot($fn, 1.0, 3.0);

        $this->assertEqualsWithDelta(2.0, $root, 1e-5);
        $this->assertEqualsWithDelta(0.0, $fn($root), 1e-4);
    }

    /**
     * Test bisectRoot when left endpoint is exact root.
     */
    public function test_bisect_root_at_exact_endpoint(): void
    {
        $fn = fn(float $x): float => $x - 5.0;
        $rootLeft = ContinuousArcUnwrapper::bisectRoot($fn, 5.0, 10.0);
        $this->assertEqualsWithDelta(5.0, $rootLeft, 1e-6);

        $rootRight = ContinuousArcUnwrapper::bisectRoot($fn, 0.0, 5.0);
        $this->assertEqualsWithDelta(5.0, $rootRight, 1e-6);
    }

    /**
     * Test bisectRoot throws InvalidArgumentException on invalid (unbracketed) interval.
     */
    public function test_bisect_root_throws_on_invalid_bracket(): void
    {
        // f(x) = x^2 + 1 on [1, 3], both endpoints positive (f(1)=2, f(3)=10)
        $fn = fn(float $x): float => ($x * $x) + 1.0;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not bracket a root');

        ContinuousArcUnwrapper::bisectRoot($fn, 1.0, 3.0);
    }
}
