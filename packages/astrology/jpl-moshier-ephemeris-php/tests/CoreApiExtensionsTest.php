<?php

declare(strict_types=1);

namespace JmeEph\Tests;

use FFI;
use FFI\CData;
use JmeEph\FFI\JmeEphFFI;
use PHPUnit\Framework\TestCase;

/**
 * C engine API added in jpl-ephemeris 1.1.0. Expected values were printed by
 * the C library itself (%.17g) in jpl-ephemeris 1.1.0; the wrapper must pass
 * them through unchanged. Arithmetic-only results are compared exactly; results
 * that go through libm transcendental functions allow 1e-14 relative.
 */
final class CoreApiExtensionsTest extends TestCase
{
    private JmeEphFFI $jme;

    private FFI $ffi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jme = new JmeEphFFI;
        $this->ffi = $this->jme->getFFI();
        $this->jme->jme_set_astro_models('DEFAULT', 0);
        $this->jme->jme_set_tid_acc(JmeEphFFI::JME_TIME_TIDAL_AUTOMATIC);
    }

    public function testTimeScaleConvertAndName(): void
    {
        [$o1, $o2, $err] = [$this->dbl(), $this->dbl(), $this->err()];
        $rc = $this->jme->jme_time_scale_convert(2451545.0, 0.0, JmeEphFFI::JME_TIME_SCALE_TT, JmeEphFFI::JME_TIME_SCALE_TAI, FFI::addr($o1), FFI::addr($o2), $err);
        $this->assertSame(0, $rc);
        $this->assertSame(2451545.0, $o1->cdata);
        $this->assertSame(-0.00037249999999999995, $o2->cdata);
        $this->jme->jme_time_scale_convert(2457754.5, 0.25, JmeEphFFI::JME_TIME_SCALE_UTC, JmeEphFFI::JME_TIME_SCALE_TT, FFI::addr($o1), FFI::addr($o2), $err);
        $this->assertSame(2457754.5, $o1->cdata);
        $this->assertSame(0.25080074074074071, $o2->cdata);
        $name = $this->jme->jme_time_scale_name(JmeEphFFI::JME_TIME_SCALE_TDB);
        $this->assertSame('TDB', is_string($name) ? $name : FFI::string($name));
    }

    public function testTableInfo(): void
    {
        [$a, $b, $c, $err] = [$this->dbl(), $this->dbl(), $this->dbl(), $this->err()];
        $this->assertSame(0, $this->jme->jme_leap_second_table_info(FFI::addr($a), FFI::addr($b), $err));
        $this->assertSame([2461587.5, 37.0], [$a->cdata, $b->cdata]);
        $this->assertSame(0, $this->jme->jme_delta_t_iers_table_info(FFI::addr($a), FFI::addr($b), FFI::addr($c), $err));
        $this->assertSame([2441684.5, 2461321.5, 2461682.5], [$a->cdata, $b->cdata, $c->cdata]);
    }

    public function testAyanamsaEx2AndSiderealTimeEx(): void
    {
        [$ay, $rate, $err] = [$this->dbl(), $this->dbl(), $this->err()];
        $this->jme->jme_set_sidereal_mode(JmeEphFFI::JME_SIDEREAL_LAHIRI, 0.0, 0.0);
        $this->assertSame(0, $this->jme->jme_get_ayanamsa_ex2(2451545.0, JmeEphFFI::JME_SIDEREAL_LAHIRI, JmeEphFFI::JME_AYANAMSA_TRUE, FFI::addr($ay), FFI::addr($rate), $err));
        $this->assertClose(23.853224722663143, $ay->cdata);
        $this->assertClose(3.8246992136024994e-05, $rate->cdata);
        $this->jme->jme_get_ayanamsa_ex2_ut(2451545.0, JmeEphFFI::JME_SIDEREAL_LAHIRI, JmeEphFFI::JME_AYANAMSA_MEAN, FFI::addr($ay), FFI::addr($rate), $err);
        $this->assertClose(23.857092357910052, $ay->cdata);
        $hours = $this->dbl();
        $this->assertSame(0, $this->jme->jme_sidereal_time_ex(2451545.0, 1, FFI::addr($hours), $err));
        $this->assertClose(18.69713803335517, $hours->cdata);
    }

    public function testHousesGauquelin(): void
    {
        $cusps = $this->ffi->new('double[37]');
        $ascmc = $this->ffi->new('double[10]');
        $err = $this->err();
        $this->assertSame(0, $this->jme->jme_houses_gauquelin(2451545.0, 0, 28.6, 77.2, $cusps, $ascmc, $err));
        $this->assertClose(100.17642650805641, $cusps[1]);
        $this->assertClose(280.17642650805641, $cusps[19]);
        $this->assertClose(107.65087247267206, $cusps[36]);
        $this->assertClose(100.17642650805641, $ascmc[0]);
        $this->assertSame(0, $this->jme->jme_houses_gauquelin_armc(100.0, 28.6, 23.44, $cusps, $ascmc, $err));
        $this->assertClose(99.18946523149512, $cusps[10]);
        $this->assertClose(197.96278849881179, $cusps[36]);
    }

    public function testEngineResolutionAndCenterPolicy(): void
    {
        $eid = $this->ffi->new('int');
        $sub = $this->ffi->new('int');
        $err = $this->err();
        $this->assertSame(0, $this->jme->jme_calc_resolve_engine(2451545.0, JmeEphFFI::JME_BODY_MARS, FFI::addr($eid), FFI::addr($sub), $err));
        $this->assertSame([JmeEphFFI::JME_ENGINE_ID_MOSHIER, 0], [$eid->cdata, $sub->cdata]);
        $this->assertSame(-1, $this->jme->jme_set_jpl_center_policy(7, $err));
        $this->assertNotSame('', FFI::string($err));
        $this->assertSame(JmeEphFFI::JME_JPL_CENTER_STRICT, $this->jme->jme_get_jpl_center_policy());
    }

    public function testCheckedSettersAndLaskar1986(): void
    {
        $err = $this->err();
        $this->assertSame(-1, $this->jme->jme_set_astro_models_checked('NOPE_TOKEN', $err));
        $this->assertSame(0, $this->jme->jme_set_astro_models_checked('PREC=LASKAR1986', $err));
        $m = $this->ffi->new('double[9]');
        $this->assertSame(0, $this->jme->jme_get_precession_matrix(2451545.0, 2451545.0 + 36525.0, JmeEphFFI::JME_MODEL_PREC_LASKAR_1986, $m));
        $this->assertClose(0.99970264828061839, $m[0]);
        $this->assertClose(-0.022366275629188623, $m[1]);
        $this->assertSame(-1, $this->jme->jme_set_topo_checked(0.0, 95.0, 0.0, $err));
        $this->assertSame(-1, $this->jme->jme_set_sidereal_mode_checked(99999, 0.0, 0.0, $err));
    }

    public function testEopAndPolarMotion(): void
    {
        [$a, $b, $err] = [$this->dbl(), $this->dbl(), $this->err()];
        $this->jme->jme_eop_clear();
        $this->assertSame(-1, $this->jme->jme_eop_coverage(FFI::addr($a), FFI::addr($b), $err));
        $this->assertSame(0, $this->jme->jme_eop_load_file(__DIR__ . '/fixtures/eop_finals2000A_excerpt.txt', $err));
        $this->assertSame(0, $this->jme->jme_eop_coverage(FFI::addr($a), FFI::addr($b), $err));
        $this->assertSame([58845.0, 60314.0], [$a->cdata, $b->cdata]);
        $pred = $this->ffi->new('int');
        $this->assertSame(0, $this->jme->jme_eop_polar_motion(58849.25, FFI::addr($a), FFI::addr($b), FFI::addr($pred), $err));
        $this->assertSame([0.076103695312499997, 0.28242803124999993, 0], [$a->cdata, $b->cdata, $pred->cdata]);
        $this->assertSame(-1, $this->jme->jme_eop_polar_motion(59000.0, FFI::addr($a), FFI::addr($b), FFI::addr($pred), $err));
        $this->jme->jme_set_polar_motion(1);
        $this->assertSame(1, $this->jme->jme_get_polar_motion());
        $this->jme->jme_set_polar_motion(0);
        $this->assertSame(0, $this->jme->jme_get_polar_motion());
        $this->jme->jme_eop_clear();
        $this->assertSame(-1, $this->jme->jme_eop_coverage(FFI::addr($a), FFI::addr($b), $err));
    }

    public function testDeltaTModelsAndDe441Error(): void
    {
        $err = $this->err();
        $this->jme->jme_set_tid_acc(JmeEphFFI::JME_TIME_TIDAL_DE441);
        $this->assertNan($this->jme->jme_delta_t_ex(2000000.0, JmeEphFFI::JME_MODEL_DELTAT_ESPENAK_MEEUS_2006, $err));
        $this->assertStringContainsString('DE441', FFI::string($err));
        $this->jme->jme_set_tid_acc(JmeEphFFI::JME_TIME_TIDAL_AUTOMATIC);
        $this->assertClose(3268.5267123287672, $this->jme->jme_delta_t_ex(2000000.0, JmeEphFFI::JME_MODEL_DELTAT_MS2004_TABLE1_LINEAR, $err));
        $this->assertClose(3229.007157575752, $this->jme->jme_delta_t_ex(2000000.0, JmeEphFFI::JME_MODEL_DELTAT_STEPHENSON_ETC_2021, $err));
    }

    private function dbl(): CData
    {
        return $this->ffi->new('double');
    }

    private function err(): CData
    {
        return $this->ffi->new('char[256]');
    }

    private function assertClose(float $expected, mixed $actual): void
    {
        $this->assertIsFloat($actual);
        $this->assertEqualsWithDelta($expected, $actual, abs($expected) * 1e-14);
    }
}
