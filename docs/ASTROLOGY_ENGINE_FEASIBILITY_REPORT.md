# Astro Setu — Vedic Astrology Calculation Engine Feasibility & Architecture Report

**Document ID:** `ASTROLOGY_ENGINE_FEASIBILITY_REPORT.md`  
**Date:** November 1, 2026  
**Subject:** Technical Feasibility, Licensing Analysis, Engine Benchmarking, and System Architecture for an Independent Vedic Astrology Calculation Engine  
**Project Locations:**
- **Frontend (Flutter):** `C:\Users\rsrav\StudioProjects\astro_setu`
- **Backend (Laravel):** `C:\wamp64\www\astro_setu`

---

## 1. Executive Summary

Astro Setu is planning to transition from static, manually authored horoscope content to an **independent, proprietary Vedic astrology calculation engine**. This report evaluates the technical, computational, and legal feasibility of building or embedding an astronomical calculation engine directly into the Laravel backend, ensuring compatibility with the current shared hosting environment while architecting a seamless migration path to a dedicated cloud server.

### Key Strategic Findings
1. **The Shared Hosting Blocker (FFI & Native Binaries):**
   - High-precision packages such as `jayeshmepani/panchang-core` and `jayeshmepani/jpl-moshier-ephemeris-php` require PHP's Foreign Function Interface (`ext-ffi`) with `ffi.enable = true`. On commercial shared hosting (cPanel/CloudLinux/CageFS), `ext-ffi` is **universally disabled** for security reasons (it grants raw C memory access, defeating process isolation).
   - Calling precompiled native C binaries (e.g., Swiss Ephemeris `swetest`) via `shell_exec()` or `proc_open()` is similarly blocked because shared hosting security policies routinely disable shell execution functions and kill long-running binary child processes.
2. **The Licensing Minefield (AGPL vs. Commercial vs. MIT):**
   - **Swiss Ephemeris (`swisseph`)** is dual-licensed under **GNU AGPL-3.0** and a proprietary **Commercial License**. Under AGPL §13, providing network/API access to Swiss Ephemeris legally compels the organization to open-source the entire proprietary Laravel backend. To use Swiss Ephemeris in closed-source commercial software, Astro Setu must purchase a commercial license from Astrodienst AG (**700 CHF / ~\$800 USD**).
   - `kunjara/jyotish` is licensed under **GPL-2.0+** and relies on `swisseph`, making it legally infeasible for closed-source commercial applications.
   - `jayeshmepani/jpl-moshier-ephemeris-php` utilizes MIT and CeCILL-B (via CALCEPH), which permits proprietary commercial use without licensing fees, but is blocked on shared hosting due to FFI.
3. **The Recommended Phased Path:**
   - **Phase 1 (Immediate / Shared Hosting Compatible):** Implement a modular, pure-PHP astronomical and Vedic calculation engine (`App\Services\Astrology\Engine\...`) utilizing analytical planetary theories (**VSOP87D** and **ELP-2000/82** / Jean Meeus algorithms). This operates with **zero C dependencies, zero FFI, zero shell execution, and zero recurring fees**, and complies 100% with MIT/permissive commercial licensing.
   - **Phase 2 (Cloud Expansion):** Design the calculation engine behind a clean Laravel Contract interface (`AstrologyCalculationInterface`). When the project migrates to a cloud VPS (Docker / Linux), the backend driver can be switched with a single environment variable (`ASTROLOGY_DRIVER=swisseph` or `ASTROLOGY_DRIVER=vedastro`) to utilize Swiss Ephemeris or an isolated high-throughput microservice without touching a single line of business logic or Flutter frontend code.

---

## 2. Existing Laravel Backend and Hosting Environment Audit

### 2.1 Laravel Version & Environment Constraints
- **Framework Version:** Laravel `13.38.0` (`v13.38.0` in `composer.lock`).
- **PHP Version Compatibility:** Configured in `composer.json` as `^8.3`, with local development on PHP `8.4.26` (WAMP64) and platform targets up to PHP 8.4.
- **Web Server Architecture:** Running Apache/FastCGI in production at `astrosetu.cubebitz.com`, utilizing shared/cPanel hosting characteristics with `public_html` directory routing.

### 2.2 CLI PHP vs. Web PHP Discrepancies
A critical operational pitfall is the assumption that CLI PHP and Web PHP share the same runtime capabilities:
| Feature / Setting | CLI PHP (Local / Terminal) | Web PHP (Shared Hosting Apache / LiteSpeed / PHP-FPM) |
|---|---|---|
| **PHP FFI (`ext-ffi`)** | Can be enabled via CLI flag or local `php.ini`. Default: `preload`. | **Permanently disabled or blocked.** Web servers reject `FFI::cdef()` unless `ffi.enable=true` is set globally in main `php.ini`, which shared hosts refuse to allow. |
| **Execution Timeout** | `max_execution_time = 0` (unlimited). | Hard-capped at **30–60 seconds** by Apache `Timeout` and CloudLinux LVE limits. |
| **Memory Limit** | Typically `512M` or `-1` (unlimited). | Hard-capped at **128M–256M**. |
| **Process Spawning (`exec`, `proc_open`)** | Available for shell scripts. | Listed in `disable_functions` on shared hosting to block shell escape exploits. |
| **OPcache Preloading** | Not active for one-off CLI calls. | Requires server root access to configure `opcache.preload`. Shared hosting users cannot configure preloading. |

### 2.3 PHP Extensions & System Restrictions
- **PHP FFI Feasibility:** **Zero.** FFI bypasses PHP's memory management (`zend_mm`), ignores `open_basedir`, and allows arbitrary memory read/write. Shared hosting providers running CloudLinux/CageFS will not enable FFI for shared tenants.
- **Native Binaries (`.so`, `.dll`, `.exe`):** Shared hosting does not permit running custom background daemons or compiling C binaries. Even if binaries are uploaded via FTP, executing them via `exec()` or `system()` triggers security alerts or fails due to missing dynamic linkers (e.g., specific `glibc` versions).
- **Cron Jobs & Scheduled Tasks:** Currently, `app/Console/Kernel.php` has **no scheduled tasks**. Shared hosting allows standard cron execution (typically calling `php -d register_argc_argv=On /path/to/artisan schedule:run > /dev/null 2>&1`), but execution frequency is throttled (minimum 5 to 15-minute intervals) and CPU burst thresholds apply.
- **Database Engine:** MySQL 8.x / MariaDB with active InnoDB tables. Spatial/ephemeris tables must fit within standard database size quotas.

---

## 3. Deep Investigation of Open-Source Calculation Engines

### 3.1 `jayeshmepani/panchang-core`
- **Author & Ecosystem:** Jayesh Mepani (Laravel/PHP package).
- **Core Dependency:** Directly requires `jayeshmepani/jpl-moshier-ephemeris-php` (`^2.0`) and `nesbot/carbon`.
- **Calculation Coverage:**
  - Complete Vedic Panchang: Tithi, Nakshatra, Yoga, Karana, Vara.
  - Auspicious/Inauspicious Muhurtas: Rahu Kalam, Yamaghanda, Gulika, Abhijit, Choghadiya, Hora, Lagna tables.
  - Solar/Lunar eclipse visibility and festival date resolution.
- **Accuracy:** Very high. Reads NASA JPL SPK binary kernels (e.g., DE440/DE441 `.bsp` files) via CALCEPH.
- **Maintenance & Modernity:** Modern PHP 8.3+ codebase, updated in 2024–2025.
- **Technical Verdict for Astro Setu:** **Incompatible with current hosting.** Because it strictly requires `ext-ffi` and `ffi.enable = 1`, installing this package on shared hosting causes fatal runtime errors (`Fatal error: Uncaught Error: Class "FFI" not found` or `FFI is disabled for security reasons`).

### 3.2 `jayeshmepani/jpl-moshier-ephemeris-php`
- **Author & Architecture:** Jayesh Mepani. A PHP 8.3+ FFI wrapper that binds to compiled C libraries: `libjme` (Steve Moshier ephemeris) and `libcalceph` (IMCCE CALCEPH library).
- **Dependencies:** Requires `ext-ffi` and bundles platform dynamic libraries (`.so`, `.dylib`, `.dll`).
- **Calculation Coverage:** Planetary coordinates, geocentric/heliocentric longitudes, planetary radii, nutation, and sidereal time.
- **Licensing:** Dual MIT / CeCILL-B (due to CALCEPH). Legally permissible for commercial use.
- **Technical Verdict for Astro Setu:** **Incompatible with current hosting.** Perfect for an isolated cloud VPS or Docker container, but unusable on shared hosting.

### 3.3 `kunjara/jyotish`
- **Author & Ecosystem:** Sergey Kozko / Andrey Teterin.
- **Core Architecture:** A PHP framework implementing classical Parashari principles (Kundali, Varga charts D1–D60, Ashtakavarga, Shadbala, Vimshottari/Ashtottari Dasha, Muhurta).
- **Underlying Engine:** Does not calculate astronomical planetary coordinates internally. It delegates ephemeris calculations to **Swiss Ephemeris** (`sweph`) via external CLI execution (`swetest`) or custom C PHP extensions.
- **Maintenance Status:** **Abandoned / Stale.** Last updated 6–8 years ago for PHP 5.6/7.0. It contains un-typed properties, deprecated constructors, and PHP 8.2+ incompatible dynamic property declarations that throw fatal errors in modern PHP 8.3/8.4 environments.
- **Licensing:** **GPL-2.0+ (Viral Copyleft).**
- **Technical Verdict for Astro Setu:** **Rejected.** Both technically broken on PHP 8.4 and legally hazardous for commercial proprietary software.

### 3.4 `VedAstro`
- **Author & Architecture:** VedAstro Open-Source Project.
- **Language & Stack:** Core engine written in **C# / .NET 8**, deployed as serverless functions on Microsoft Azure.
- **Calculation Scope:** Extremely broad (>500 metrics): D1 through D60 divisional charts, 47 Ayanamsa systems, transit hits, Ashtakavarga, and 16-factor Kuta matching.
- **Underlying Engine:** Uses Swiss Ephemeris (`sweph`) compiled for .NET.
- **Maintenance Status:** Extremely active, production-grade, with modern REST API and Model Context Protocol (MCP) support.
- **Licensing:** MIT License for the wrapper/codebase.
- **Technical Verdict for Astro Setu:**
  - Cannot run locally inside PHP on shared hosting (.NET runtime cannot be deployed on cPanel).
  - Can be used in Phase 2 as an independent external microservice on a low-cost VPS ($4–\$6/month) or container.
  - Relying on VedAstro's public free cloud API is not recommended for production due to third-party rate limits, latency (300–800ms per request), and potential service interruptions.

### 3.5 Swiss Ephemeris (`swisseph` / Astrodienst)
- **Author & Industry Standing:** Astrodienst AG (Dieter Koch and Alois Treindl). The gold standard used by Astro.com, Jagannatha Hora, Astrotalk, and Drik Panchang.
- **Precision:** Sub-arcsecond accuracy derived from NASA JPL DE431/DE441 ephemerides.
- **Licensing & Commercial Pricing:**
  - **GNU AGPL-3.0:** Free for open-source projects. However, AGPL §13 states that if the software is accessed over a network, the full consumer application source code must be published under AGPL.
  - **Commercial Professional License:** **700.00 CHF (~$800 USD / ~₹67,000 INR)** one-time fee for a 99-year royalty-free license for proprietary software.
- **Technical Verdict for Astro Setu:** The ultimate destination for precision in Phase 2 (on cloud hosting), but blocked on shared hosting due to binary execution limits, and requires budget allocation for the 700 CHF commercial license.

---

## 4. Licensing Investigation & Commercial Compliance

| Software / Library | Primary License | Viral Copyleft? | Can Be Used in Closed-Source Commercial App? | Legal Requirements & Restrictions |
|---|---|---|---|---|
| **Swiss Ephemeris (Astrodienst)** | Dual: **GNU AGPL-3.0** OR **Commercial** | **YES (under AGPL)** | **NO under AGPL.**<br>**YES under Commercial License.** | Under AGPL, you MUST open-source the entire Astro Setu backend if accessible over a network. To keep Astro Setu proprietary, you must purchase a commercial license for **700 CHF**. |
| **`kunjara/jyotish`** | **GNU GPL-2.0+** | **YES** | **NO** | Incorporating this package into Laravel forces the entire Laravel backend to be licensed under GPL-2.0. |
| **`jayeshmepani/panchang-core`** | **MIT** | **NO** | **YES** | Permissive. Requires preserving copyright and license notice. |
| **`jayeshmepani/jpl-moshier...`** | **MIT / CeCILL-B** | **NO** | **YES** | Permissive. CeCILL-B is a French BSD/MIT-equivalent license governing the bundled CALCEPH library. Commercial use permitted. |
| **`VedAstro` (.NET Core)** | **MIT** | **NO** | **YES** | Permissive. Code can be modified and hosted in commercial environments. (Note: Ensure compliance with underlying Swiss Ephemeris license if self-hosting). |
| **Steve Moshier Ephemeris (C)** | **Permissive Copyright** | **NO** | **YES** | Free commercial use and redistribution permitted with attribution. |
| **VSOP87 Theory (IMCCE / Meeus)** | **Public Domain / Open** | **NO** | **YES** | Free mathematical analytical theory; unrestricted commercial usage. |
| **`andrmoel/astronomy-bundle`** | **MIT** | **NO** | **YES** | 100% permissive for proprietary commercial SaaS and mobile backends. |

### Summary of Licensing Constraints:
1. **Never import GPL or AGPL libraries into the Laravel codebase.** This eliminates `kunjara/jyotish` and any direct un-licensed Swiss Ephemeris wrapper.
2. **Permissive libraries (MIT, BSD, CeCILL-B, Public Domain)** can be safely compiled into our proprietary commercial product without royalties, source-code disclosure, or legal risk.

---

## 5. Practical Initial Calculation Scope (MVP vs. Deferred)

To deliver immediate value to users and unlock features like **Kundli, Dosha, and Panchang** without over-engineering or exceeding server memory limits, the calculation engine should be phased:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        PHASE 1: INITIAL CORE ENGINE                    │
│                 (Pure PHP • Zero FFI • Shared Hosting Ready)           │
├───────────────────────────────────┬────────────────────────────────────┤
│ Astronomical Core                 │ Vedic Astrological Engine          │
│ • Universal Time (UTC) & JD       │ • Lahiri Ayanamsha (Chitra Paksha) │
│ • Apparent Sun & Moon Longitudes  │ • Nirayana (Sidereal) Longitudes   │
│ • 5 True Planets (Mars to Saturn) │ • Ascendant (Lagna) Computation    │
│ • Lunar Nodes (Rahu & Ketu)       │ • Rashi & House Placements (D1)    │
│ • Nutation & True Obliquity (ε)   │ • Nakshatra (1-27) & Pada (1-4)    │
│ • Local Sidereal Time (LMST)      │ • 5 Panchang Angas                 │
│                                   │ • Basic Vimshottari Dasha          │
│                                   │ • Real-Time Planetary Transits     │
└───────────────────────────────────┴────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        PHASE 2: DEFERRED ADVANCED ENGINE               │
│                    (Cloud Migration • Extended Features)               │
├───────────────────────────────────┬────────────────────────────────────┤
│ Advanced Divisional Charts        │ Astrological Strengths & Systems   │
│ • Navamsha (D9), Dashamsha (D10)  │ • Shadbala (6-fold strength)       │
│ • Full Shodashvarga (D1 to D60)   │ • Ashtakavarga matrices            │
│ • Bhava Chalit (Sripati / Placidus)│ • Ashtakoot 36-Gun Milan matching  │
│ • Solar & Lunar Eclipse timings   │ • Mangal Dosha & Kalsarpa Dosha    │
│ • Muhurta Selection Engine        │ • Jaimini Chara Dasha              │
└───────────────────────────────────┴────────────────────────────────────┘
```

### 5.1 Initial Feasible Scope (Phase 1 Detailed Specifications)
1. **Julian Day & Ephemeris Time:** Calculate Julian Day (JD) and Barycentric Dynamical Time ($T$) from birth date, time, and timezone.
2. **Planetary Longitudes (Sayana / Tropical):**
   - Sun and Moon: High-precision periodic perturbation models (Meeus/Chapront).
   - Mercury, Venus, Mars, Jupiter, Saturn: VSOP87D analytical series (truncated to 0.001° precision to preserve sub-millisecond execution time).
   - Rahu and Ketu: True and Mean Lunar Nodes calculated from Brown's Lunar Theory. Ketu is exactly $\text{Rahu} + 180^\circ$.
3. **Lahiri Ayanamsha (Chitra Paksha):**
   - The Indian Government Calendar Reform Committee standard (Chitra at $180^\circ$ ecliptic longitude).
   - Base value at J2000.0: $23^\circ 51' 25.53''$.
   - Precession computation: $Ayanamsha(t) = 23.856994^\circ + 0.01396^\circ \times T$.
   - Sidereal Longitude: $\lambda_{sidereal} = (\lambda_{tropical} - Ayanamsha) \pmod{360^\circ}$.
4. **Ascendant (Lagna):**
   - Calculate Greenwich Mean Sidereal Time (GMST), add geographic longitude to find Local Sidereal Time (LMST / $\theta$).
   - Compute true obliquity of the ecliptic ($\epsilon$) with nutation in obliquity ($\Delta \epsilon$).
   - $\tan(\lambda_{asc}) = \frac{\cos(\theta)}{-\sin(\theta) \cos(\epsilon) - \tan(\phi) \sin(\epsilon)}$, resolving quadrant ambiguity using `atan2`.
5. **Rashi & House Placement (D1 Birth Chart):**
   - Rashi (Sign 1–12): $\lfloor \lambda_{sidereal} / 30^\circ \rfloor + 1$.
   - Whole Sign House System (standard in Vedic astrology): House 1 is the entire sign of the Ascendant.
6. **Nakshatra and Pada:**
   - 27 Nakshatras spanning $13^\circ 20'$ ($800'$) each: $\text{Index} = \lfloor \lambda_{sidereal} / 13.3333^\circ \rfloor + 1$.
   - 4 Padas per Nakshatra spanning $3^\circ 20'$ ($200'$) each: $\text{Pada} = \lfloor (\lambda_{sidereal} \pmod{13.3333^\circ}) / 3.3333^\circ \rfloor + 1$.
7. **The 5 Panchang Angas:**
   - **Tithi:** $(\lambda_{Moon} - \lambda_{Sun}) \pmod{360^\circ} / 12^\circ + 1$ (1–15 Shukla Paksha, 16–30 Krishna Paksha).
   - **Vara:** Day of the week directly derived from $(JD + 1.5) \pmod 7$.
   - **Nakshatra:** Derived from Moon's sidereal longitude.
   - **Yoga:** $(\lambda_{Moon} + \lambda_{Sun}) \pmod{360^\circ} / (360^\circ / 27) + 1$ (1 to 27 Yogas: Vishkambha to Vaidhriti).
   - **Karana:** Half of a Tithi ($6^\circ$ arc differential, 60 Karanas in a lunar month).
8. **Vimshottari Dasha (120-Year Cycle):**
   - 9 Mahadashas: Ketu (7y), Venus (20y), Sun (6y), Moon (10y), Mars (7y), Rahu (18y), Jupiter (16y), Saturn (19y), Mercury (17y).
   - Opening Dasha Balance at birth:
     $$\text{Fraction traversed} = \frac{\lambda_{Moon} \pmod{13^\circ 20'}}{13^\circ 20'}$$
     $$\text{Balance years} = (1 - \text{Fraction traversed}) \times \text{Mahadasha Total Years}$$
   - Antardasha (Sub-periods): $\text{Duration} = \frac{\text{Maha Years} \times \text{Antar Years}}{120}$.
9. **Planetary Transits (Gochar):** Calculate current real-time planetary longitudes and determine which natal houses they occupy relative to the birth Moon and Lagna.

### 5.2 Calculations to Defer to Phase 2
- **Shodashvarga (D2 to D60):** Specifically D9 (Navamsha) and D10 (Dashamsha). While mathematical derivation from D1 longitudes is straightforward, UI visualization and chart interpretation should be deferred until D1 is rock-solid.
- **Ashtakoot 36-Gun Milan & Kundli Matching:** Requires complex multi-rule evaluation (Nadi, Bhakoot, Gana, Yoni, Tara, Vasya, Varna, Dina) and dosha exceptions.
- **Shadbala (Planetary Strengths):** Involves 6 composite sub-strengths (Sthanabala, Digbala, Kaalabala, Cheshtabala, Naisargikabala, Drikbala) requiring extensive matrix computations.
- **Solar & Lunar Eclipse Path Computations:** Requires Besselian elements and complex shadow projections.

---

## 6. Validation & Testing Strategy

To guarantee that calculations match industry standards without manual cross-checking, we must establish a **ground-truth validation harness**.

```
┌────────────────────────────────────────────────────────┐
│           Astro Setu Validation Harness               │
└────────────────────────────────────────────────────────┘
                           │
       ┌───────────────────┴───────────────────┐
       ▼                                       ▼
┌──────────────────────────────┐    ┌──────────────────────────────┐
│  Benchmark Reference Engines │    │    Automated Test Dataset    │
│  • Swiss Ephemeris swetest   │    │    • 100 Geographic Points   │
│  • Jagannatha Hora (PVR Rao) │    │    • 5 Historical Epochs     │
│  • NASA JPL Horizons Web API │    │    • Edge Timezone / DST     │
│  • Drik Panchang Reference   │    │    • High-Latitude Coordinates│
└──────────────────────────────┘    └──────────────────────────────┘
                           │                   │
                           └─────────┬─────────┘
                                     ▼
┌────────────────────────────────────────────────────────┐
│              Automated Regression Suite                │
│             (PHPUnit in tests/Astrology)               │
├────────────────────────────────────────────────────────┤
│ Acceptable Tolerance Thresholds:                       │
│ • Planetary Longitudes:  < 0.01° (within ~36 arcsec)   │
│ • Ascendant (Lagna):     < 0.05° (within ~3 arcmin)    │
│ • Nakshatra / Pada:      100% exact boundary match     │
│ • Tithi / Yoga / Karana: 100% exact integer match      │
│ • Dasha Transition Date: < 24-48 hours over 120 years  │
└────────────────────────────────────────────────────────┘
```

### 6.1 Reference Benchmarks
1. **Astrodienst `swetest` CLI:** Generate reference tables for planetary longitudes and Lagna using flag `-sid1` (Lahiri Ayanamsha) and `-fPlbs`.
2. **NASA JPL Horizons API:** Validate raw tropical ecliptic coordinates of the Sun, Moon, and 5 planets before Ayanamsha subtraction.
3. **Drik Panchang Reference:** Benchmark Tithi, Yoga, Nakshatra, and Karana start/end timings.

### 6.2 Diverse Test Matrix (Corner Cases)
The test suite must cover:
- **Historical Dates & Leap Years:** 1900-02-28, 2000-02-29 (century leap year), 1947-08-15 (Indian Independence), 2024-02-29, 2026-11-01 (Current).
- **Timezone Offsets & Day Boundaries:** Midnight births (00:00:01 and 23:59:59), standard Indian Standard Time (+05:30), negative timezones (US Pacific -08:00, -07:00 DST), and daylight saving transition hours.
- **Geographic Extremes:**
  - Standard Indian Subcontinent: Ujjain ($23.17^\circ\text{ N}, 75.78^\circ\text{ E}$ - traditional prime meridian of Hindu astrology), New Delhi, Varanasi, Chennai.
  - Equator: Quito, Kenya, Singapore ($1.35^\circ\text{ N}$).
  - High Latitudes: London ($51.5^\circ\text{ N}$), Oslo ($59.9^\circ\text{ N}$), Tromsø ($69.6^\circ\text{ N}$ - tests Lagna calculation when signs rise obliquely or polar phenomena occur).
  - Southern Hemisphere: Sydney ($-33.86^\circ\text{ S}$), Buenos Aires.
- **Zodiac Boundary Crossings:** Planetary positions located at $29^\circ 59' 59''$ (Gandanta zones between Pisces/Aries, Cancer/Leo, Scorpio/Sagittarius) to verify zero sign misclassification.

---

## 7. Comparative Candidate Analysis

| Evaluation Criteria | `jayeshmepani/panchang-core` | `kunjara/jyotish` | `VedAstro` (.NET Microservice) | Astrodienst Swiss Ephemeris (`swisseph`) | **Pure-PHP Engine (Proposed Astro Setu Phase 1)** |
|---|---|---|---|---|---|
| **Hosting Compatibility (Shared Hosting)** | ❌ **Incompatible** (Requires FFI & binary loading) | ❌ **Incompatible** (Requires CLI `exec` / custom C ext) | ❌ **Incompatible** (Requires .NET 8 runtime) | ❌ **Incompatible** (Requires binary execution / C ext) |  **100% Compatible** (Standard PHP 8.3/8.4, zero FFI/binaries) |
| **Licensing Commercial Suitability** |  **Safe** (MIT / CeCILL-B) | ❌ **Violates Closed-Source** (GPL-2.0+ copyleft) |  **Safe** (MIT Wrapper) | ⚠️ **Requires 700 CHF License** (AGPL-3.0 copyleft otherwise) |  **100% Safe** (MIT / Permissive algorithms) |
| **Recurring / Upfront Cost** | $0 | $0 | $0 (Self-hosted) or API usage fees | **700 CHF (~$800 USD)** one-time commercial fee | **$0** (Zero upfront, zero recurring) |
| **Calculation Accuracy** | Exceptional (JPL Kernels) | High (Swiss Ephemeris) | Exceptional (Swiss Ephemeris) | Gold Standard (Sub-arcsecond) | **High (<0.01° error)**, perfectly adequate for all Vedic charts |
| **PHP 8.3 / 8.4 Support** |  Yes | ❌ Abandoned / Fatal errors | N/A (Runs in C# .NET) | Requires custom build/wrapper |  **Native PHP 8.3 / 8.4** |
| **Ease of Future Cloud Migration** | High | Low | High (Docker container) | High (Native binary / FFI on VPS) |  **Instantaneous** (Driver interface allows zero-code swap) |

---

## 8. Recommended Architectural Design for Astro Setu

To reconcile the competing priorities of **zero recurring costs**, **immediate shared-hosting execution**, **commercial licensing compliance**, and **future cloud expansion**, we recommend an **Abstracted Driver Architecture** inside the Laravel backend.

```
                                  [Flutter Mobile App]
                                           │
                                    (REST API Calls)
                                           │
                                           ▼
                       [Laravel Controllers & API Endpoints]
                                           │
                                           ▼
                     [AstrologyManager (Service Container)]
                                           │
                                (Reads ASTROLOGY_DRIVER)
                                           │
                     ┌─────────────────────┴─────────────────────┐
                     ▼                                           ▼
       ┌───────────────────────────┐               ┌───────────────────────────┐
       │   PurePhpAstrologyDriver  │               │    CloudServiceDriver     │
       │        (PHASE 1)          │               │        (PHASE 2)          │
       ├───────────────────────────┤               ├───────────────────────────┤
       │ • 100% Pure PHP 8.3/8.4   │               │ • Docker / Cloud VPS      │
       │ • Zero C / FFI / exec     │               │ • Swiss Ephemeris / .NET  │
       │ • VSOP87D / ELP-2000      │               │ • Sub-arcsecond Precision │
       │ • Runs on Shared Hosting  │               │ • High-Concurrency Queue │
       │ • MIT / $0 License Cost   │               │ • Commercial License Paid │
       └───────────────────────────┘               └───────────────────────────┘
```

### 8.1 Implementation Strategy: The Driver Pattern
Define a strict contract interface in Laravel:
```php
namespace App\Services\Astrology\Contracts;

interface AstrologyCalculationInterface
{
    public function calculatePlanetaryPositions(Carbon $dateTime, float $latitude, float $longitude): PlanetaryCoordinates;
    public function calculateAscendant(Carbon $dateTime, float $latitude, float $longitude): AscendantData;
    public function calculatePanchang(Carbon $dateTime, float $latitude, float $longitude): PanchangData;
    public function calculateKundli(BirthDetails $birthDetails): KundliChartData;
    public function calculateVimshottariDasha(Carbon $dateTime, float $moonLongitude): DashaPeriodCollection;
}
```

- In **Phase 1 (Now):** Implement `PurePhpAstrologyDriver` inside `app/Services/Astrology/Drivers/PurePhpDriver.php`. It handles all ephemeris trigonometry, Ayanamsha subtraction, and Panchang mathematics using native PHP mathematical functions (`sin`, `cos`, `tan`, `atan2`, `fmod`). It runs seamlessly on local WAMP and live shared hosting (`cubebitz.com`).
- In **Phase 2 (Cloud VPS):** When the database and backend migrate to a cloud VPS (e.g., Ubuntu/Docker), implement `SwissEphemerisDriver` or `VedAstroMicroserviceDriver`. Switching `ASTROLOGY_DRIVER=swisseph` in `.env` automatically routes all calculation requests to the cloud engine without changing a single API route, database table, or Flutter view.

---

## 9. Blockers & Action Plan Prior to Implementation

Before beginning active implementation of the Phase 1 engine, the following blockers and prerequisites must be resolved:

| # | Blocker / Dependency | Impact | Required Action |
|---|---|---|---|
| 1 | **Shared Hosting Process & Extension Constraint** | Attempting to use C-based packages like `jayeshmepani/panchang-core` or `swisseph` will break production. | Formally approve the **Pure-PHP Phase 1 strategy** as the only viable shared-hosting architecture. |
| 2 | **Database Contract Gap (`users.chart_preference`)** | Flutter profile allows users to pick chart style (North Indian / South Indian), but the database has no column to store it. | Add a database migration in the future feature branch to add `chart_preference` enum to `users`. |
| 3 | **Timezone & Geocoding Dependency** | Planetary and Lagna calculations require accurate geographical coordinates (Latitude, Longitude) and exact UTC offsets. The current profile stores free-form strings for `place_of_birth`. | Integrate an offline city coordinate lookup table (SQLite/JSON of Indian and world cities) or a lightweight geocoding service to resolve latitude/longitude from birth place names. |
| 4 | **Verification Dataset Sign-Off** | Calculations cannot be confirmed without a standardized reference set. | Compile a reference dataset of 25 diverse chart outputs from Jagannatha Hora / Drik Panchang before writing unit tests. |

---

## 10. Conclusion & Final Recommendation

1. **Do not use `jayeshmepani/panchang-core` or `jpl-moshier-ephemeris-php` on current hosting:** While excellently engineered, their hard requirement for `ext-ffi` and compiled C binaries makes them incompatible with standard shared hosting environments.
2. **Do not use `kunjara/jyotish`:** It is unmaintained for PHP 8.4 and introduces dangerous GPL copyleft viral contamination into your commercial proprietary codebase.
3. **Do not use unlicensed Swiss Ephemeris (`swisseph`):** Using Swiss Ephemeris over a web API without purchasing the 700 CHF commercial license violates the AGPL license terms.
4. **Build the Phase 1 Pure-PHP Analytical Engine:** Implement VSOP87-derived planetary coordinates and classical Parashari algorithms natively in PHP 8.4 behind a Laravel driver interface. This delivers instant, zero-cost, accurate, and shared-hosting-compatible Vedic astrology features (Kundli, Panchang, Dasha, Transits) that will scale effortlessly into a dedicated cloud engine in Phase 2.
