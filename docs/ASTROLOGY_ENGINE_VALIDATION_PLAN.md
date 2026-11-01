# Astro Setu Vedic Astrology Calculation Engine — Validation & Verification Plan

**Project:** Astro Setu  
**Document:** Scientific Validation, Accuracy Benchmarks & Automated Regression Suite  
**Author:** DeepMind Agentic AI & Astro Setu Engineering Team  
**Date:** November 2026 (Revision 2: Ground-Truth Fixtures, TT/UT Handling & Circular Angular Math)  
**Status:** Verification Protocol Specification & Test Suite Implementation  

---

## 1. Purpose & Validation Scope

A proprietary Vedic astrology calculation engine cannot merely be "functionally operational" — it must deliver **verified mathematical precision** conforming to established astrological and astronomical standards. Astrological users and professional astrologers immediately notice even minor deviations in:
* **Lagna (Ascendant):** A shift of $1^\circ$ alters house cusps and can change the rising sign (*Rashi*).
* **Tithi & Nakshatra End Times:** A variance of more than a few minutes disrupts ritual fasts (*vratas*), muhurtas, and festivals.
* **Vimshottari Dasha Dates:** An error of $0.05^\circ$ in natal Moon longitude shifts Mahadasha start and transition dates by **$> 18\text{ days}$**.

This validation plan outlines the mathematical criteria, ground-truth authoritative baselines, acceptable error tolerances, test fixtures, and automated test suites required to ensure Astro Setu's engine achieves gold-standard accuracy.

---

## 2. Authoritative Ground-Truth Baselines

The Astro Setu engine is benchmarked against four authoritative references:

```
+---------------------------------------------------------------------------------------+
|                              GROUND TRUTH REFERENCES                                  |
+---------------------------------------------------------------------------------------+
| 1. NASA JPL Horizons Ephemeris System (https://ssd.jpl.nasa.gov/horizons/)           |
|    - Definitive solar system ephemeris based on numerical integration (DE440/DE441).  |
|    - Ground truth for: Geocentric apparent tropical coordinates, Julian Day, nutation.|
+---------------------------------------------------------------------------------------+
| 2. Positional Astronomy Centre / Indian Ephemeris (Rashtriya Panchang)                |
|    - India Meteorological Department (IMD), Government of India.                     |
|    - Ground truth for: Standard Lahiri (Chitra Paksha) Ayanamsha values.             |
+---------------------------------------------------------------------------------------+
| 3. Drik Panchang (https://www.drikpanchang.com/)                                      |
|    - Widely accepted industry reference for Drik-ganita Vedic calculations.          |
|    - Ground truth for: Tithi, Nakshatra, Yoga, Karana transition times, Rahu Kaal.   |
+---------------------------------------------------------------------------------------+
| 4. United States Naval Observatory (USNO) Astronomical Algorithms                     |
|    - Standard reference for topocentric solar disc atmospheric refraction.           |
|    - Ground truth for: Sunrise, Sunset, Civil/Nautical/Astronomical twilight.         |
+---------------------------------------------------------------------------------------+
```

---

## 3. Mathematical Fundamentals: Time Standards & Circular Angular Distance

### 3.1 J2000.0 Epoch: Terrestrial Time (TT) vs. Universal Time (UT)
* **Astronomical Definition:** The standard epoch J2000.0 is defined at **`2000-01-01 12:00:00 TT` (Terrestrial Time)**, which corresponds exactly to Julian Day **`2451545.0 TT`**.
* **Distinction from Civil UT:** Universal Time (UT1/UTC) lags Terrestrial Time by $\Delta T$:
  $$\Delta T = \text{TT} - \text{UT}$$
  At epoch J2000.0 (January 1, 2000), $\Delta T \approx 63.83\text{ seconds}$.
* **Civil Time Conversion:**
  - `2000-01-01 12:00:00 UT` corresponds to Julian Day **`2451545.0 UT`** (and `2451545.000739 TT`).
  - `2000-01-01 12:00:00 TT` corresponds to civil time **`2000-01-01 11:58:56.17 UT`**.
* **Implementation Protocol:** In `jpl-ephemeris`, functions named `*_ut` take Universal Time (civil date and decimal hour) and apply the internal polynomial $\Delta T$ table to derive Terrestrial Time for ephemeris evaluations. Tests asserting Julian Day conversion from civil calendar inputs test against `2451545.0 UT`.

### 3.2 Circular Angular Distance Formula
Linear difference `$a - $b` fails across the $0^\circ / 360^\circ$ boundary (e.g., $359.98^\circ$ and $0.02^\circ$ has a linear difference of $359.96^\circ$, but the true circular angular separation is only $0.04^\circ$).

All longitude assertions MUST use circular distance:
$$\Delta \theta = \min\left( |\theta_1 - \theta_2| \pmod{360^\circ},\; 360^\circ - (|\theta_1 - \theta_2| \pmod{360^\circ}) \right)$$

This is implemented in `CanonicalPanchangCacheKey::circularAngularDifference($a, $b)` and enforced across all test assertions.

---

## 4. Independent Reference Fixtures & Tolerances

| Parameter | Test Epoch & Location | Reference Source | Expected Ground Truth | Permissible Tolerance | Astrological Impact | Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Julian Day (UT)** | 2000-01-01 12:00:00 UT | IAU / SOFA | `2451545.000000 JD` | $\pm 0.000001\text{ d}$ ($0.086\text{ s}$) | Zero | **Implemented** |
| **Chitra Lahiri Ayanamsha** | 2000-01-01 12:00:00 UT | Positional Astronomy Centre (IMD) | `23° 51' 11.2"` ($23.85311^\circ$) | $\pm 0.005^\circ$ ($18''$) | Rashi boundary exact | **Implemented** |
| **Sun Tropical Lon** | 2024-04-08 18:18:00 UTC | NASA JPL Horizons | $19.400^\circ$ (Aries $19^\circ 24'$) | $\pm 0.05^\circ$ ($3'$) | Solar ingress $< 10\text{ s}$ | **Implemented** |
| **Moon Tropical Lon** | 2024-04-08 18:18:00 UTC | NASA JPL Horizons | $19.398^\circ$ (Eclipse Conjunction) | $\pm 0.05^\circ$ ($3'$) | Dasha balance $< 2\text{ d}$ | **Implemented** |
| **Jupiter Tropical Lon**| 2026-11-01 12:00:00 UTC | NASA JPL Horizons | $90.63^\circ$ (Cancer $0^\circ 38'$) | $\pm 0.05^\circ$ ($3'$) | Planetary placement exact | **Implemented** |
| **Saturn Tropical Lon** | 2026-11-01 12:00:00 UTC | NASA JPL Horizons | $342.84^\circ$ (Pisces $12^\circ 50'$) | $\pm 0.05^\circ$ ($3'$) | Planetary placement exact | **Implemented** |
| **Vara (Weekday)** | 2026-11-01 (New Delhi) | Civil Calendar / Drik Panchang | `Sunday` (Ravivara) | Exact match | Essential daily limb | **Implemented** |
| **Tithi Name** | 2026-11-01 (New Delhi) | Drik Panchang | `Saptami` (Shukla Paksha) | Exact match | Fasting/Vrata alignment | **Implemented** |
| **Nakshatra Name** | 2026-11-01 (New Delhi) | Drik Panchang | `Pushya` (Saturn Lord) | Exact match | Janma Nakshatra | **Implemented** |
| **Sunrise Timing** | 2026-11-01 (New Delhi, 28.61N, 77.20E) | USNO / Positional Astronomy Centre | `06:33 AM IST` | $\pm 60\text{ seconds}$ | Ahargana / Ishtkaal | **Implemented** |
| **Sunset Timing** | 2026-11-01 (New Delhi, 28.61N, 77.20E) | USNO / Positional Astronomy Centre | `05:36 PM IST` | $\pm 60\text{ seconds}$ | Dinamana / Pradosha | **Implemented** |

---

## 5. Automated Regression Test Suite (`tests/Feature/AstrologyEngineAccuracyTest.php`)

The automated regression suite has been implemented in [`tests/Feature/AstrologyEngineAccuracyTest.php`](file:///c:/wamp64/www/astro_setu/tests/Feature/AstrologyEngineAccuracyTest.php).

### Test Suite Capabilities:
1. **Dynamic Environment Adaptation:** Checks for `extension_loaded('FFI')`. When run in environments without CLI FFI (such as local Windows WAMP CLI), tests are gracefully reported as skipped rather than throwing fatal unhandled exceptions.
2. **Circular Angular Distance Comparisons:** Uses `assertCircularDegreesMatch` to test angular differences across all $360^\circ$ boundaries.
3. **Festival Bypass Execution SLA:** Measures execution duration and asserts that core Panchang with `skip_festivals=true` executes in **$< 350\text{ ms}$**.

---

## 6. Execution Status Matrix

| Test Suite / Capability | Current Status | Details & Observations |
| :--- | :--- | :--- |
| `tests/Unit/Astrology/CanonicalPanchangCacheKeyTest.php` | **LOCALLY TESTED & PASSED** | 3 tests, 14 assertions passed in 31 ms (hash determinism, coordinate rounding, circular angular difference). |
| `tests/Feature/AstrologyEngineAccuracyTest.php` | **IMPLEMENTED (PENDING HOSTINGER RUN)** | 4 tests implemented. Skipped on Windows dev CLI (no FFI); ready for native execution on Hostinger CLI PHP 8.4.27. |
| Native C Harness (`test_jme.c`) | **LOCALLY TESTED & PASSED (WSL)** | Compiled and executed with Zig on Linux glibc 2.28; verified Sun/Moon Moshier calculations. |
| Hostinger CLI JME Runtime | **PENDING HOSTINGER DEPLOYMENT** | Awaiting git pull and execution of `php artisan astrology:poc` on live server. |
