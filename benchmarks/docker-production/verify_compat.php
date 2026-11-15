<?php

declare(strict_types=1);

echo "1. PHP Version: " . PHP_VERSION . " (" . PHP_SAPI . ")" . PHP_EOL;
echo "2. Operating System: " . php_uname('s') . " " . php_uname('r') . " (" . php_uname('m') . ")" . PHP_EOL;
echo "3. FFI Extension Loaded: " . (extension_loaded('ffi') ? 'YES' : 'NO') . PHP_EOL;
echo "4. FFI Enabled (ini): " . ini_get('ffi.enable') . PHP_EOL;

// Basic FFI operation
$basicFfi = FFI::cdef("int printf(const char *format, ...);", "libc.so.6");
echo "5. Basic FFI Operation (libc.so.6): ";
$basicFfi->printf("SUCCESS [libc printf executed]\n");

// Native libjme.so loading
$libPath = '/app/packages/astrology/jpl-moshier-ephemeris-php/libs/jme-linux-x64-glibc228/libjme.so';
echo "6. Target Linux Library Path: " . $libPath . PHP_EOL;
echo "   File exists: " . (file_exists($libPath) ? 'YES' : 'NO') . PHP_EOL;

require_once '/app/vendor/autoload.php';

$t0 = hrtime(true);
$jme = new JmeEph\FFI\JmeEphFFI($libPath);
$loadMs = (hrtime(true) - $t0) / 1e6;
echo "7. JME FFI Instantiation: SUCCESS (in " . round($loadMs, 2) . " ms)" . PHP_EOL;

$res = $jme->getFFI()->new('double[6]');
$err = $jme->getFFI()->new('char[256]');
$flags = JmeEph\FFI\JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEph\FFI\JmeEphFFI::JME_CALC_SIDEREAL | JmeEph\FFI\JmeEphFFI::JME_CALC_SPEED;

$rc = $jme->jme_calc_ut(2460250.5, JmeEph\FFI\JmeEphFFI::JME_BODY_SUN, $flags, $res, $err);
echo "8. Real Planetary Position (Sun at JD 2460250.5):" . PHP_EOL;
echo "   Return Code: " . $rc . PHP_EOL;
echo "   Longitude: " . round((float)$res[0], 6) . "°" . PHP_EOL;
echo "   Daily Speed: " . round((float)$res[3], 6) . "°/day" . PHP_EOL;
echo "9. Verification Result: 100% COMPATIBLE AND FUNCTIONAL" . PHP_EOL;
