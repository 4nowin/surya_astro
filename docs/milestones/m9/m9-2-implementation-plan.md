# Astro Setu — M9.2 Detailed Kundli Report
## Document 4: Phased Implementation Plan & Verification Strategy (Version 3.2)

**Document ID:** `ASTRO-M9.2-IMPL-PLAN`  
**Version:** 3.2.0 (FINAL — VERSION 3.2.0 — FROZEN FOR IMPLEMENTATION)  
**Status:** Approved Phased Implementation Roadmap  
**Author:** DeepMind / Antigravity Pair Programming Engine  
**Baseline Environment:** Laravel 13.x / PHP 8.4+ / Hostinger Shared Hosting / Native `libjme.so` (via `NativeEphemerisResolver`)

---

## 1. Two-Stage Validation Quality Framework

To guarantee mathematical integrity and user trust, Phase A implementation enforces two sequential validation gates:

```
+===================================================================================================+
| IMPLEMENTATION QUALITY & RELEASE GATES                                                            |
+===================================================================================================+
|                                                                                                   |
|   [ GATE A: Mathematical & Algorithmic Validation ]                                               |
|   - Independent benchmark fixtures (Swiss Ephemeris / BV Raman notable horoscopes).               |
|   - Continuous arc unwrapping, stationary point, and extrema boundary tests for D-1/D-9 Lagna.    |
|   - Interval boundary evaluation across full birth uncertainty window for all 7 Panchanga limbs.  |
|   - Calculated Dasha balance intervals derived from Moon arc bounds (no hardcoded tolerances).    |
|   - Pratyantardasha period-boundary uncertainty evaluated against duration (<= 25% threshold).     |
|   - Sade Sati bisection root-refinement verification.                                             |
|   - Zero critical calculation defects or unhandled boundary singularities.                        |
|                                                                                                   |
|                                         |                                                         |
|                                         v (Mandatory prerequisite for Gate B)                     |
|                                                                                                   |
|   [ GATE B: Product & PDF Integration Gate ]                                                      |
|   - ONLY calculation groups certified under Gate A may appear as validated content in paid PDFs.  |
|   - Calculation groups failing Gate A or lacking completed test suites are suppressed or use an   |
|     approved qualitative fallback (e.g. current transit sign instead of unvalidated timeline).    |
|   - Implementation completion alone does NOT equal product release authorization.                 |
+===================================================================================================+
```

---

## 2. Phase A Work Package Sequencing (A1 to A5)

Phase A is strictly organized into five sequential work packages:

### Work Package A1: Mathematical Foundation & Interval Stability
- **Interval Stability Core:** Implements continuous angular unwrapping ($\lambda_{\text{unwrapped}}(t)$) and stationary point detection ($d\lambda/dt = 0$) across $T \in [t_{\text{start}}, t_{\text{end}}]$.
- **D-1 & D-9 Ascendant Stability:** Evaluates Ascendant across $T$; renders D-1 Lagna only if invariant; if multi-candidate, permits qualified presentation only if all candidate charts are calculated, otherwise suppresses. Renders D-9 Lagna only if invariant across $T$; certifies invariant planetary D-9 placements.
- **Birth Panchanga Limbs:** Evaluates Tithi, Paksha, Vaara (with sunrise boundary qualification for unknown time), Nakshatra, Pada, Yoga, and Karana across $T$.
- **Vimshottari Dasha Uncertainty:** Converts Moon longitude into unwrapped relative arc within Nakshatra; propagates minimum and maximum balance bounds into Mahadasha, Antardasha, and Pratyantardasha date intervals. Implements the $W_{\text{period}} \le 0.25 \times D_{\text{Prat}}$ reliability threshold.

### Work Package A2: Classical Calculations & Yogas
- **Parashari Graha Drishti:** Implements pure 7-body aspect matrix (universal 7th; Mars 4th/8th, Jupiter 5th/9th, Saturn 3rd/10th). Excludes lunar nodes.
- **Panch Mahapurusha Yogas:** Ruchaka, Bhadra, Hamsa, Malavya, Sasa evaluated strictly from Lagna Kendra with own/exaltation signs (BPHS Ch. 75).
- **Gaja Kesari Yoga:** Evaluates Moon-Jupiter Kendra relationship; separates formation from dignity.
- **Budhaditya Yoga:** Reports structural formation in same Rashi, angular separation, and ephemeris combustion without arbitrary quality cutoffs.
- **Manglik Dosha & Bhangas:** Evaluates from Lagna; implements 5 verified classical Bhangas; applies complete 12-sign Jupiter functional-lordship rules (Aries, Cancer, Leo, Scorpio, Sagittarius, Pisces eligible; Taurus, Gemini, Virgo, Libra, Capricorn, Aquarius excluded); classifies *Chandra-Mangala* and age $> 28$ as traditional mitigations.

### Work Package A3: Conditional Transit Calculations
- **Saturn Sade Sati & Dhaiya:** Implements daily sampling and bisection root-refinement via native `libjme.so` to determine exact ingress/exit timestamps and retrograde multi-ingress phases.
- **Gate Release Safeguard:** If transit validation fixtures do not achieve Gate A certification prior to Phase A release, the system deploys the approved **qualitative current-phase fallback** without delaying the core report launch.

### Work Package A4: Commerce, Ledger & Worker Infrastructure
- **Financial Ledger Migration:** Creates `report_purchases` with `related_purchase_id UNIQUE` constraint to guarantee at most one compensating refund per debit at the database level.
- **Report Delivery Migration:** Creates `kundli_reports` with `report_purchase_id UNIQUE` and `ON DELETE SET NULL` on `user_id`.
- **Atomic Wallet Commerce:** Implements `DB::transaction()` with `users.wallet_balance` locked via `lockForUpdate()`, enforcing unique `idempotency_key`.
- **Worker Lease Renewal & Ownership Protection:** Adds `renewLease()` heartbeat to `CalculationJobManager`; implements worker ownership fencing, temporary attempt-specific PDF paths (`storage/app/private/reports/tmp/`), and atomic final publication.
- **Secure Delivery:** Authenticated temporary signed download routes (15–30 min TTL) with Sanctum ownership verification.

### Work Package A5: PDF Design & Product Integration
- **Bilingual Dictionaries:** 100% deterministic rule-based matrices (`en` and `hi`) with non-fatalistic framing and non-medical Nadi disclaimers.
- **Dynamic Dompdf Template:** Content-driven natural pagination (~6 to ~12 pages); table-based layouts; embedded `Noto Sans Devanagari` regular and bold fonts.
- **Vector SVG Charts:** Crisp North Indian diamond charts for D-1, Chandra, and D-9 charts.
- **Contextual Astrologer Referral:** Seamless entry point to Astro Setu verified astrologers.

---

## 3. Explicit List of Deferred Features

To maintain mathematical honesty and prevent unverified output, the following features are **explicitly deferred**:

| Deferred Feature | Target Phase | Technical & Classical Rationale |
| :--- | :--- | :--- |
| **Shadbala (6-fold Strength)** | **Phase B** | Requires 6 complex astronomical sub-models (Sthana, Dig, Kala, Chesta, Naisargika, Drik) and verified virupa benchmarks. |
| **Ashtakavarga (SAV & BAV)** | **Phase B** | Requires 8 binary contribution tables and cell-by-cell cross-testing against verified published reference charts. |
| **Kalsarpa Dosha** | **Phase C / Deferred** | Lacks consensus in foundational Parashari texts (BPHS); conflicting nodal motion and cancellation definitions. |
| **Higher Divisional Vargas (D-2 to D-12)** | **Phase C** | Secondary vargas deferred until D-9 Navamsha stability mechanics are proven in production. |
| **Combinatorial Raj & Dhana Yogas** | **Phase B** | Multi-lord Kendra-Trikona permutations require an exhaustive combinatorial rulebook to avoid false positives. |
| **Gemstone Prescriptions** | **Excluded** | High liability risk; should never be automated algorithmically without personal consultation. |

---

## 4. Testing, Validation & Performance Strategy

### 4.1 Gate A Mathematical Cross-Validation Fixtures
1. **Benchmark Horoscope Suite:**
   - Minimum 10 standard test charts from established astronomical almanacs (including charts from BV Raman's *Notable Horoscopes*).
   - Planetary longitude tolerance: $\le 0^\circ 00' 05''$ of arc relative to Swiss Ephemeris / Moshier analytical coordinates.
2. **Interval Stability Tests:**
   - Automated tests across monotonic, retrograde, and stationary planetary tracks to verify that boundary crossings are correctly identified.
   - Verify that an `unknown` birth time (24-hour window) certifies Saturn as invariant while flagging the Moon as unstable when a sign or nakshatra boundary is crossed.
   - Verify that a `verified` birth time ($\pm 5\text{ min}$) flags D-1 or D-9 Ascendant as unstable when close to a boundary line.
3. **Yoga & Bhanga Combinations:**
   - Automated assertions confirming that Mars in Aries in House 1 is recognized as Ruchaka Yoga (formed) while Manglik Dosha is evaluated as Cancelled (*Bhanga*).
   - Assert that Mars in Cancer in House 1 or 4 is **NOT** evaluated as cancelled.
   - Assert that Jupiter's aspect cancels Manglik Dosha only for its functional benefic Lagnas (Aries, Cancer, Leo, Scorpio, Sagittarius, Pisces) and is excluded for Libra, Taurus, Gemini, Virgo, Capricorn, Aquarius.

### 4.2 Performance Benchmarking Targets
Performance metrics will be **empirically measured and benchmarked** directly on the staging server:
- **Peak Memory Benchmark Target:** $\le 95\text{ MB}$ per PDF generation process (measured via `memory_get_peak_usage(true)`).
- **Execution Duration Benchmark Target:** $\le 5.0\text{ seconds}$ from CLI launch to file write completion.
- **Typography Quality:** Visual regression testing across 50 Hindi sample outputs to confirm zero clipped matras or disjointed ligatures.

---

## 5. Commercial & Operational Decisions Requiring Approval

Before beginning technical implementation, the following product and business decisions require explicit stakeholder sign-off:

1. **Pilot Price Point Validation:**
   - Proposed retail price is **₹149** for digital report delivery. Confirm whether this price point is approved for launch or if introductory promo tiers are needed.
2. **PDF Disk Retention Policy:**
   - The proposed policy retains the generated PDF on disk for **90 days** (with on-the-fly regeneration available from stored snapshot data up to **365 days**). Confirm if this storage policy meets business expectations.
3. **Regeneration Window:**
   - Confirm whether the 365-day free regeneration eligibility window is approved.
4. **Payment Method Prioritization for Phase A:**
   - Phase A currently specifies deducting from the existing user **wallet balance** (`users.wallet_balance`). Confirm whether direct payment gateway checkout (e.g. Razorpay modal directly for report purchase) is required for Phase A or if wallet deduction is sufficient.
5. **Dated Transit Table vs. Qualitative Sade Sati Fallback:**
   - Confirm whether the detailed dated transit timeline should be included in Phase A only if 100% validated under Gate A, or if a qualitative current-phase indicator is acceptable as an interim fallback.

---

## 6. Concrete Acceptance Criteria for Phase A Implementation

When Phase A implementation commences, it will be validated against the following criteria:

- [ ] **Gate A Mathematical Certification:** All implemented algorithms (Work Packages A1 and A2) pass automated regression test suites before release to Gate B.
- [ ] **Gate B Product Compliance:** The paid PDF renders only Gate A certified calculations; unvalidated calculations are suppressed or display approved qualitative fallbacks.
- [ ] **Worker Lease Heartbeat & Fencing:** `CalculationJobManager::renewLease()` is implemented and called periodically; workers write to attempt-specific temporary files and abort publication if the lease is lost.
- [ ] **Database Constraint Integrity:** `report_purchases` enforces `related_purchase_id UNIQUE` at the schema level, mathematically preventing multiple refunds against any single debit record.
- [ ] **Content-Driven Natural Pagination:** Outputs professional print-ready A4 PDFs (~6 to ~12 pages) based strictly on available validated sections without forced page quotas or artificial filler.
- [ ] **Detached Worker Execution:** Fully decoupled background generation using `CalculationWorkerLauncher` and `CalculationJobManager` with native `libjme.so` under unrestricted FFI.
- [ ] **Secure Delivery:** Generates authenticated temporary signed download URLs (15–30 min TTL) with user ownership validation.
