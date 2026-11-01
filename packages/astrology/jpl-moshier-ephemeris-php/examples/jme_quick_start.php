<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use JmeEph\FFI\JmeEphFFI;

$jme = new JmeEphFFI;

$version = $jme->getFFI()->new('char[256]');
$jme->jme_version($version, 256);

echo 'JME version: ' . FFI::string($version) . PHP_EOL;

$jd = $jme->jme_julian_day(2000, 1, 1, 12.0, JmeEphFFI::JME_CALENDAR_GREGORIAN);
echo 'J2000 Julian day: ' . $jd . PHP_EOL;

$xx = $jme->getFFI()->new('double[6]');
$error = $jme->getFFI()->new('char[256]');

$result = $jme->jme_calc_ut(
    $jd,
    JmeEphFFI::JME_BODY_SUN,
    JmeEphFFI::JME_CALC_NONE,
    $xx,
    $error
);

if ($result === JmeEphFFI::JME_OK) {
    if (is_finite($xx[0])) {
        echo 'Sun longitude: ' . $xx[0] . PHP_EOL;
    } else {
        echo 'JME calculation returned a non-finite Sun longitude for this native build.' . PHP_EOL;
    }

    exit(0);
}

echo 'JME calculation unavailable: ' . FFI::string($error) . PHP_EOL;
