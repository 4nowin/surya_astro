# JPL Moshier Ephemeris PHP FFI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jayeshmepani/jpl-moshier-ephemeris-php.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/jpl-moshier-ephemeris-php)
[![Total Downloads](https://img.shields.io/packagist/dt/jayeshmepani/jpl-moshier-ephemeris-php.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/jpl-moshier-ephemeris-php)
[![PHP Version Require](https://img.shields.io/packagist/php-v/jayeshmepani/jpl-moshier-ephemeris-php?style=flat-square)](https://packagist.org/packages/jayeshmepani/jpl-moshier-ephemeris-php)
[![License: MIT AND CeCILL-B](https://img.shields.io/badge/license-MIT%20AND%20CeCILL--B-blue.svg?style=flat-square)](#license)

PHP 8.3+ FFI wrapper for the independent JPL Moshier Ephemeris C library.

This package wraps the project-owned `jme_*` C API from the JPL Moshier Ephemeris native library.

PHP setup, the Laravel provider, and the shared function list are in the native library's single reference: <https://jayeshmepani.github.io/jpl-ephemeris/#lang-php>.

## Contract

- Primary public functions: `jme_*`
- Primary public constants: `JME_*`
- Current wrapper target: all 225 public `jme_*` functions tracked by the native API inventory
- The PHP wrapper exposes 478 public `JME_*` constants, plus the two header include guards `JME_JME_H` and `JME_EXTENDED_H`, and preserves existing compatibility aliases where present
- Native backend: `libjme.so`, `libjme.dylib`, or `jme.dll`
- No hidden rounding, output reshaping, or dropped status/error buffers
- Incomplete native behavior should return `JME_ERR` from the C library rather than pretending to provide production ephemeris output

## JME 2.0.0 upgrade note

Extended-body identifiers now use JME's negative identifier range, separate from the core body identifiers. Applications that stored the old numeric values must migrate to the corresponding `JME_BODY_*` constants. Function signatures are unchanged.

## Engine Selection

The low-level `JmeEphFFI` class exposes every native function directly. The optional Laravel-style `JmeService::calc()` convenience method sets `ENGINE=...` through `jme_set_astro_models()` and then calls `jme_calc()`:

- `AUTO`: let the native C core choose its standard JPL/fallback policy
- `JPL`: require JPL kernel-backed behavior
- `MOSHIER`: force Moshier analytical behavior
- `VSOP_ELP_MEEUS`: force VSOP87/ELP2000/Meeus analytical behavior

Use direct `jme_jpl_*` calls for raw JPL/CALCEPH kernel access. The PHP layer does not normalize or reinterpret native outputs.

## Requirements

- PHP `^8.3`
- PHP FFI extension (`ext-ffi`)
- A compiled JME shared library
- The matching CALCEPH runtime library when using the JPL/CALCEPH backend

By default, the wrapper loads the bundled platform library from this package, for example:

```text
vendor/jayeshmepani/jpl-moshier-ephemeris-php/libs/linux-x64/libjme.so
```

Override it with:

```bash
export JME_LIBRARY_PATH=/path/to/libjme.so
```

## Runtime Libraries and Kernels

Composer installs prebuilt runtime archives from this repository's GitHub releases when a local library is not already present. Each runtime archive contains:

- JME: `jme.dll`, `libjme.so`, or `libjme.dylib`
- CALCEPH: the platform runtime library required by JPL kernel mode

JPL `.bsp` kernels are not shipped in this package. Download them separately from the native JME kernel release:

```text
https://github.com/jayeshmepani/jpl-ephemeris/releases/tag/jpl-kernels
```

Supported kernel choices:

- `de440s.bsp` - small
- `de440.bsp` - medium
- `de441.bsp` - large, published as split release parts because the full file exceeds GitHub's single asset size limit

## Quick Start

```php
use FFI;
use JmeEph\FFI\JmeEphFFI;

$jme = new JmeEphFFI();

$jd = $jme->jme_julian_day(
    2000,
    1,
    1,
    12.0,
    JmeEphFFI::JME_CALENDAR_GREGORIAN
);

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
    echo "Sun longitude: {$xx[0]}\n";
} else {
    echo 'JME error: ' . FFI::string($error) . "\n";
}
```

## Test

```bash
composer install
composer verify:surface
composer test
```

The test suite verifies the PHP wrapper against the JME-native contract, including function inventory coverage, constant inventory coverage, key `JME_*` values, and convenience calculation paths. Set `JME_SOURCE_PATH` if the native source tree is not at the default local path.

`composer verify:surface` performs an exact low-level surface audit of the generated wrapper:

- `225` tracked `jme_*` declarations compared against the native header prototypes
- `478` public `JME_*` constants, plus the two header include guards, compared against native header values
- numeric constants cross-checked through a compiled C probe instead of trusting PHP generation alone

## Credits

This package is a binding for [JPL Moshier Ephemeris (JME)](https://github.com/jayeshmepani/jpl-ephemeris) by Jayesh Mepani (Jayesh Patel). JME builds on:

- **JPL DE440 / DE441** ephemerides (NASA/JPL; Park et al. 2021). Kernels are downloaded separately and are redistributed unmodified per the NAIF rules.
- **CALCEPH 5.0.1** (Copyright CNRS, Observatoire de Paris, Observatoire de la Côte d'Azur), used under the CeCILL-B license. Prebuilt CALCEPH runtime libraries are bundled unmodified; license text in [`LICENSES/CeCILL-B.txt`](LICENSES/CeCILL-B.txt); provided without warranty (CeCILL-B Articles 8 and 9); source code at https://calceph.imcce.fr/.
- **Stephen L. Moshier**: plan404 and Astronomical Almanac 5.6 analytical theories (Moshier engine). MIT License by permission of the author (12 October 2026; [record](https://github.com/jayeshmepani/jpl-ephemeris/blob/main/docs/permissions/MOSHIER_PERMISSION.md)). Portions derived from the work of Stephen L. Moshier, incorporated herein by permission of the author.
- **ELP 2000-82B** by M. Chapront-Touzé and J. Chapront (CDS VI/79), evaluated by JME's own code generated from the unmodified CDS files.
- **VSOP87** by P. Bretagnon and G. Francou (CDS VI/81); C code from Greg A. Miller's VSOP87-Multilang (public domain dedication).
- **Jean Meeus**, *Astronomical Algorithms* (formulas cited); **F. Espenak and J. Meeus** (NASA) ΔT polynomials; **ERFA** (BSD 3-Clause, NumFOCUS Foundation) IAU 2000B nutation; **Yale Bright Star Catalogue** (Hoffleit and Warren).

Full credits, terms, and required notice texts: [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

## License

**Package license: `MIT AND CeCILL-B`.**

- The original code in this repository (the PHP FFI wrapper, tests and tooling) is licensed under the [MIT License](LICENSE), Copyright (c) 2026 Jayesh Mepani (Jayesh Patel). The `LICENSE` file contains only the MIT text for that code.
- The bundled prebuilt CALCEPH 5.0.1 runtime libraries are licensed under CeCILL-B; the full text is in [`LICENSES/CeCILL-B.txt`](LICENSES/CeCILL-B.txt).
- The bundled JME runtime libraries contain the Moshier material (plan404, AA 5.6), which is distributed under the MIT License by permission of Stephen L. Moshier, 12 October 2026 ([permission record](https://github.com/jayeshmepani/jpl-ephemeris/blob/main/docs/permissions/MOSHIER_PERMISSION.md)). Portions derived from the work of Stephen L. Moshier, incorporated herein by permission of the author.

Third-party components keep their own terms; see [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
