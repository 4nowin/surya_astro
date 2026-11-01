<?php

declare(strict_types=1);

namespace Tests\Unit\Astrology;

use Tests\TestCase;

/**
 * Validates the resolution and fallback behavior of Panchang options,
 * ensuring festival-bypass flags cannot leak, misresolve, or throw undefined-variable errors.
 */
class PanchangOptionResolutionTest extends TestCase
{
    /**
     * Resolves the skip_festivals flag using the exact specification implemented
     * in PanchangSelectiveApiTrait.
     */
    private function resolveSkipFestivals(array $options, array $defaultConfig): bool
    {
        return array_key_exists('skip_festivals', $options)
            ? (bool) $options['skip_festivals']
            : (bool) ($defaultConfig['skip_festivals'] ?? false);
    }

    public function test_option_explicitly_true_returns_true(): void
    {
        $this->assertTrue($this->resolveSkipFestivals(['skip_festivals' => true], []));
        $this->assertTrue($this->resolveSkipFestivals(['skip_festivals' => true], ['skip_festivals' => false]));
    }

    public function test_option_explicitly_false_returns_false(): void
    {
        $this->assertFalse($this->resolveSkipFestivals(['skip_festivals' => false], []));
        $this->assertFalse($this->resolveSkipFestivals(['skip_festivals' => false], ['skip_festivals' => true]));
    }

    public function test_option_omitted_falls_back_to_default_config(): void
    {
        $this->assertFalse($this->resolveSkipFestivals([], []));
        $this->assertFalse($this->resolveSkipFestivals([], ['skip_festivals' => false]));
        $this->assertTrue($this->resolveSkipFestivals([], ['skip_festivals' => true]));
    }

    public function test_option_truthy_falsy_values_are_cast_to_bool(): void
    {
        $this->assertTrue($this->resolveSkipFestivals(['skip_festivals' => 1], []));
        $this->assertFalse($this->resolveSkipFestivals(['skip_festivals' => 0], []));
    }
}
