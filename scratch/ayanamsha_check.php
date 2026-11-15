<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$lib = App\Services\Astrology\NativeEphemerisResolver::resolve();
$jme = new JmeEph\FFI\JmeEphFFI($lib);

// Check ayanamsha immediately upon FFI instantiation
$rawAyanamshaJ2000 = $jme->jme_get_ayanamsa_ut(2451545.0);
echo "1. Raw unconfigured JmeEphFFI instance J2000: " . number_format($rawAyanamshaJ2000, 10) . " deg\n";

// Configure True Citra Lahiri
JayeshMepani\PanchangCore\Astronomy\JmeRuntime::setTrueCitra($jme);
$citraAyanamshaJ2000 = $jme->jme_get_ayanamsa_ut(2451545.0);
echo "2. After JmeRuntime::setTrueCitra() J2000:     " . number_format($citraAyanamshaJ2000, 10) . " deg\n";

// Now check Sun longitude at J2000 (2451545.0) and at benchmark date JD 2460252.0 (2023-11-03)
$ffi = $jme->getFFI();
$buf = $ffi->new('double[6]');
$err = $ffi->new('char[256]');

// J2000 Tropical Sun (JME_CALC_SPEED)
$jme->jme_calc_ut(2451545.0, JmeEph\FFI\JmeEphFFI::JME_BODY_SUN, JmeEph\FFI\JmeEphFFI::JME_CALC_SPEED, $buf, $err);
echo "3. Tropical Sun longitude at J2000 (JD 2451545.0): " . number_format($buf[0], 6) . " deg\n";

// J2000 Sidereal Sun (Tropical - True Citra Ayanamsha)
$siderealSunJ2000 = fmod($buf[0] - $citraAyanamshaJ2000 + 360.0, 360.0);
echo "4. Sidereal Sun longitude at J2000 (JD 2451545.0): " . number_format($siderealSunJ2000, 6) . " deg\n";

// Benchmark date JD 2460252.0 (2023-11-03)
$jme->jme_calc_ut(2460252.0, JmeEph\FFI\JmeEphFFI::JME_BODY_SUN, JmeEph\FFI\JmeEphFFI::JME_CALC_SPEED, $buf, $err);
echo "5. Tropical Sun longitude at JD 2460252.0 (2023-11-03): " . number_format($buf[0], 6) . " deg\n";

$ayan2023 = $jme->jme_get_ayanamsa_ut(2460252.0);
echo "6. True Citra Ayanamsha at JD 2460252.0 (2023-11-03): " . number_format($ayan2023, 10) . " deg\n";

$siderealSun2023 = fmod($buf[0] - $ayan2023 + 360.0, 360.0);
echo "7. Sidereal Sun longitude at JD 2460252.0 (2023-11-03): " . number_format($siderealSun2023, 6) . " deg\n";
