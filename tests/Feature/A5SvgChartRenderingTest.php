<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Astrology\Pdf\SvgVargaRenderer;
use Tests\TestCase;

/**
 * Test Suite for Vector SVG North Indian Diamond Chart Rendering in Work Package A5.
 *
 * Verifies:
 *  - Valid SVG XML structure on 400x400 canvas.
 *  - D-1, Chandra Kundli, and D-9 Navamsha chart generation.
 *  - Resident planet placements, retrograde (R), combustion * indicators.
 *  - Ascendant degree annotations in House 1.
 *  - Devanagari numerals and abbreviations in Hindi locale.
 *  - D-9 Ascendant glyph suppression when volatile.
 */
class A5SvgChartRenderingTest extends TestCase
{
    protected SvgVargaRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = app(SvgVargaRenderer::class);
    }

    /**
     * Test D-1 Lagna Kundli renders with valid SVG and Ascendant degrees.
     */
    public function test_d1_lagna_kundli_renders_valid_svg_with_ascendant_degrees(): void
    {
        $houses = [];
        for ($h = 1; $h <= 12; $h++) {
            $houses[$h] = [
                'house_number' => $h,
                'sign_id' => $h,
                'resident_planets' => [],
            ];
        }

        // Add resident planets in House 1 and House 10
        $houses[1]['resident_planets'] = [
            ['key' => 'sun', 'abbr' => 'Su', 'is_retrograde' => false, 'is_combust' => false],
            ['key' => 'mercury', 'abbr' => 'Me', 'is_retrograde' => true, 'is_combust' => true],
        ];

        $svg = $this->renderer->renderD1($houses, 'en', '15° 32\' 10"');

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringEndsWith('</svg>', $svg);
        $this->assertStringContainsString('viewBox="0 0 400 400"', $svg);
        $this->assertStringContainsString('15° 32\' 10"', $svg); // Ascendant degrees
        $this->assertStringContainsString('As', $svg); // Ascendant glyph
        $this->assertStringContainsString('Su', $svg); // Sun
        $this->assertStringContainsString('Me(R)*', $svg); // Retrograde + combust Mercury
        $this->assertStringContainsString('Lagna Kundli (D-1)', $svg);
    }

    /**
     * Test Chandra Kundli renders with Moon as House 1 and title.
     */
    public function test_chandra_kundli_renders_without_ascendant_glyph(): void
    {
        $houses = [];
        for ($h = 1; $h <= 12; $h++) {
            $houses[$h] = [
                'house_number' => $h,
                'sign_id' => (($h + 3) % 12) + 1,
                'resident_planets' => [],
            ];
        }
        $houses[1]['resident_planets'] = [
            ['key' => 'moon', 'abbr' => 'Mo', 'is_retrograde' => false, 'is_combust' => false],
        ];

        $svg = $this->renderer->renderChandra($houses, 'en');

        $this->assertStringContainsString('viewBox="0 0 400 400"', $svg);
        $this->assertStringContainsString('Chandra Kundli', $svg);
        $this->assertStringContainsString('Mo', $svg);
        // Chandra Kundli should NOT show 'As' glyph for House 1
        $this->assertStringNotContainsString('>As<', $svg);
    }

    /**
     * Test D-9 Navamsha chart renders with Ascendant glyph when invariant.
     */
    public function test_d9_navamsha_chart_with_invariant_ascendant(): void
    {
        $planetaryD9 = [
            'jupiter' => [
                'key' => 'jupiter',
                'name' => 'Jupiter',
                'is_invariant' => true,
                'is_suppressed' => false,
                'd9_navamsha' => ['id' => 4, 'name' => 'Cancer'],
            ],
            'saturn' => [
                'key' => 'saturn',
                'name' => 'Saturn',
                'is_invariant' => true,
                'is_suppressed' => false,
                'd9_navamsha' => ['id' => 7, 'name' => 'Libra'],
            ],
        ];

        $d9Ascendant = [
            'is_invariant' => true,
            'status' => 'confirmed',
            'id' => 4, // Cancer rising in D-9
            'degrees_dms' => '02° 15\'',
        ];

        $svg = $this->renderer->renderD9($planetaryD9, $d9Ascendant, 'en');

        $this->assertStringContainsString('Navamsha Kundli (D-9)', $svg);
        $this->assertStringContainsString('As', $svg);
        $this->assertStringContainsString('Ju', $svg);
        $this->assertStringContainsString('Sa', $svg);
        $this->assertStringContainsString('02° 15\'', $svg);
    }

    /**
     * Test D-9 Navamsha chart suppresses Ascendant glyph when volatile, rendering unrotated sign distribution.
     */
    public function test_d9_navamsha_chart_suppresses_ascendant_when_volatile(): void
    {
        $planetaryD9 = [
            'sun' => [
                'key' => 'sun',
                'name' => 'Sun',
                'is_invariant' => true,
                'is_suppressed' => false,
                'd9_navamsha' => ['id' => 1, 'name' => 'Aries'],
            ],
        ];

        $d9Ascendant = [
            'is_invariant' => false,
            'status' => 'suppressed',
            'id' => null,
        ];

        $svg = $this->renderer->renderD9($planetaryD9, $d9Ascendant, 'en');

        // Must show unrotated sign distribution title and NO 'As' glyph
        $this->assertStringContainsString('D-9 Navamsha (Sign Distribution)', $svg);
        $this->assertStringNotContainsString('>As<', $svg);
        $this->assertStringContainsString('Su', $svg);
    }

    /**
     * Test Hindi locale renders Devanagari numerals and Hindi abbreviations.
     */
    public function test_hindi_locale_renders_devanagari_numerals_and_abbr(): void
    {
        $houses = [
            1 => [
                'house_number' => 1,
                'sign_id' => 4, // Cancer (४ in Devanagari)
                'resident_planets' => [
                    ['key' => 'jupiter', 'abbr_hi' => 'गु', 'is_retrograde' => false, 'is_combust' => false],
                ],
            ],
        ];

        $svg = $this->renderer->renderChart($houses, 'hi', 'लग्न कुण्डली');

        $this->assertStringContainsString('४', $svg); // Devanagari digit 4
        $this->assertStringContainsString('गु', $svg); // Hindi Guru abbr
        $this->assertStringContainsString('लग्न कुण्डली', $svg);
    }
}
