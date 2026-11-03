# Astro Setu — M9.2 Detailed Kundli Report
## Document 1: Calculation Engine Audit & Gap Assessment (Version 3.2)

**Document ID:** `ASTRO-M9.2-CALC-AUDIT`  
**Version:** 3.2.0 (FINAL — VERSION 3.2.0 — FROZEN FOR IMPLEMENTATION)  
**Status:** Approved Technical Specification & Engine Audit  
**Author:** DeepMind / Antigravity Pair Programming Engine  
**Baseline Environment:** Laravel 13.x / PHP 8.4+ / Hostinger Shared Hosting / Native `libjme.so` (via `NativeEphemerisResolver`)

---

## 1. Executive Summary & Audit Baseline

This document defines the mathematical, astronomical, and algorithmic requirements for the Astro Setu calculation engine for M9.2 (Detailed Kundli Report).

### Environmental Baseline Verification
Unlike generic Laravel configurations, the Astro Setu codebase operates under specific infrastructure realities:
- **Framework & Runtime:** Laravel 13.x running on PHP 8.4+ (CLI / Web SAPI).
- **Native Ephemeris Engine:** Moshier analytical ephemeris implemented as a native C shared library (`libjme.so` on Linux glibc 2.28; `jme.dll` on Windows), managed exclusively via `App\Services\Astrology\NativeEphemerisResolver`.
- **FFI Execution Model:** Hostinger shared hosting restricts Web SAPI PHP FFI. All native ephemeris calls for background or heavy workloads must execute via the existing detached CLI worker architecture (`CalculationWorkerLauncher` / `CalculationJobManager`).
- **Data Persistence:** User wallet balances are stored directly on the `users` table (`wallet_balance`), with feature-specific ledger records (e.g. `chat_payments`, `orders`, `payments`). There is no pre-existing generic `wallet_transactions` table.

---

## 2. Two-Gate Quality & Validation Framework

To ensure that unverified, buggy, or mathematically unstable calculations never reach paying users, all astrological algorithms must satisfy two explicit, sequential validation gates:

```
+===================================================================================================+
| TWO-STAGE QUALITY VALIDATION GATES                                                                |
+===================================================================================================+
|                                                                                                   |
|   [ GATE A: Mathematical & Algorithmic Validation ]                                               |
|   - Independent reference fixtures (Swiss Ephemeris / BV Raman benchmark charts).                 |
|   - Continuous arc unwrapping, stationary point, and extrema boundary test suites.                |
|   - Actual calculated uncertainty intervals for time-dependent parameters (no hardcoded dates).   |
|   - Zero critical calculation defects or unhandled boundary singularities.                        |
|                                                                                                   |
|                                         |                                                         |
|                                         v (Mandatory prerequisite for Gate B)                     |
|                                                                                                   |
|   [ GATE B: Product & PDF Integration Gate ]                                                      |
|   - ONLY calculation groups certified under Gate A may appear as validated content in paid PDFs.  |
|   - Calculation groups failing Gate A or lacking completed fixtures must be suppressed or use an  |
|     explicitly approved qualitative fallback (e.g. current transit sign for Sade Sati).           |
|   - Implementation completion alone does NOT equal product release authorization.                 |
+===================================================================================================+
```

---

## 3. Comprehensive Interval Stability Policy

Every time-sensitive astrological parameter is evaluated across the recorded birth-time uncertainty interval $T \in [t_{\text{start}}, t_{\text{end}}]$. No noon-time assumption is ever introduced.

### 3.1 Precision Tiers and Uncertainty Intervals
1. **`verified`:** Recorded birth time has documented clock/hospital precision:  
   $T = [t_{\text{birth}} - 5\text{ min}, t_{\text{birth}} + 5\text{ min}]$ ($10\text{ minutes}$ total span).
   > [!IMPORTANT]
   > A verified birth time of $\pm 5\text{ minutes}$ is **NOT treated as automatic mathematical certainty**. Over 10 minutes, the D-1 Ascendant traverses $\approx 2.5^\circ$ of arc, and the D-9 Navamsha Ascendant traverses $\approx 22.5^\circ$ of arc (more than half a Navamsha). If the recorded time falls near a sign or Navamsha boundary, the Ascendant changes even within a "verified" tier.
2. **`approximate`:** Birth time is known within a stated range (e.g. morning, afternoon, $\pm 1$ hr, $\pm 2$ hr):  
   $T = [t_{\text{start}}, t_{\text{end}}]$ as defined by the user.
3. **`unknown`:** Exact time of day is completely missing:  
   $T = [00:00:00, 23:59:59]$ local civil time on the birth date in the native's birth timezone.

---

### 3.2 D-1 and D-9 Ascendant Stability & Multi-Candidate Reporting

The Ascendant ($Lagna$) and all derived house placements must be evaluated across $T$:

1. **D-1 Rashi Ascendant:**
   - Compute sidereal $\lambda_{\text{Asc}}(t)$ across $T \in [t_{\text{start}}, t_{\text{end}}]$.
   - Let $S_{\text{D1}}(t) = \lfloor \lambda_{\text{Asc}}(t) / 30^\circ \rfloor + 1$.
   - **Invariance Rule:** If $S_{\text{D1}}(t)$ is strictly identical $\forall t \in T$, normal D-1 house-based reporting is certified as **Stable & Reportable**.
   - **Multi-Candidate Handling:** If the Ascendant changes across $T$, crossing one or more $30^\circ$ boundaries:
     - House-dependent interpretations and Lagna-dependent Yogas must **not** be presented as certain.
     - A qualified multi-candidate presentation is permitted **only** when every candidate chart and its dependent calculations have actually been calculated and clearly labeled (e.g. Candidate 1: Aries Lagna for 06:00–06:45; Candidate 2: Taurus Lagna for 06:45–08:00).
     - Otherwise, unstable house-dependent content is **Suppressed**, and the report relies upon Chandra Kundli.
     - A generic boundary caveat must never override mathematical stability requirements.
2. **D-9 Navamsha Ascendant:**
   - Compute $S_{\text{D9, Asc}}(t) = (\lfloor \lambda_{\text{Asc}}(t) / 3^\circ 20' \rfloor \pmod{12}) + 1$.
   - **Invariance Rule:** Certified invariant only if $\lfloor \lambda_{\text{Asc}}(t) / 3^\circ 20' \rfloor$ is strictly constant $\forall t \in T$.
   - If invariant: D-9 Ascendant is rendered in the Navamsha chart.
   - If variant: D-9 Ascendant glyph and Navamsha house numbering are **Suppressed**. The D-9 chart is rendered solely as an unrotated sign distribution of certified invariant planets.

---

### 3.3 Birth Panchanga Interval Evaluation

Each Panchanga limb is evaluated across the full interval $T \in [t_{\text{start}}, t_{\text{end}}]$ using continuous angular unwrapping to detect intermediate transitions. **No Sun-related or civil value is assumed automatically invariant**:

1. **Sunrise and Sunset:**
   - Calculated for the birth geographic coordinates, civil date, and timezone.
   - For `unknown` birth time, daily sunrise and sunset boundaries are computed to establish the local solar day.
2. **Vaara (Solar Weekday):**
   - Determined strictly by the local sunrise-to-sunrise convention.
   - **Unknown-Time Handling:** For an `unknown` birth time, the local civil day ($00:00:00$ to $23:59:59$) inherently spans local sunrise. Times before sunrise belong to the previous solar day; times after sunrise belong to the current civil day. Therefore, **Vaara is NOT automatically stable for unknown birth time** and must be qualified with the exact sunrise transition timestamp.
3. **Tithi (Lunar Day):**
   - Elongation angle $\theta_{\text{Tithi}}(t) = (\lambda_{\text{Moon}}(t) - \lambda_{\text{Sun}}(t)) \pmod{360^\circ}$.
   - Tithi index $K(t) = \lfloor \theta_{\text{Tithi}}(t) / 12^\circ \rfloor + 1 \in \{1, \dots, 30\}$.
   - Invariant only if $K(t)$ is constant $\forall t \in T$. If a transition occurs in $T$, all candidate Tithis are displayed with exact transition timestamps.
4. **Paksha (Lunar Fortnight):**
   - Derived directly from Tithi: Shukla ($K \le 15$), Krishna ($K > 15$). Invariant if Tithi does not cross Full/New Moon boundary.
5. **Nakshatra (Lunar Mansion):**
   - Nakshatra index $N(t) = \lfloor \lambda_{\text{Moon}}(t) / 13^\circ 20' \rfloor + 1 \in \{1, \dots, 27\}$.
   - Invariant only if $N(t)$ is constant $\forall t \in T$. If boundary crossed, candidate Nakshatras and transition times are qualified.
6. **Yoga (Solilunar Sum):**
   - Solilunar sum $\theta_{\text{Yoga}}(t) = (\lambda_{\text{Sun}}(t) + \lambda_{\text{Moon}}(t)) \pmod{360^\circ}$.
   - Yoga index $Y(t) = \lfloor \theta_{\text{Yoga}}(t) / 13^\circ 20' \rfloor + 1 \in \{1, \dots, 27\}$.
   - Invariant only if $Y(t)$ is constant $\forall t \in T$.
7. **Karana (Half-Tithi):**
   - Karana index $C(t) = \lfloor \theta_{\text{Tithi}}(t) / 6^\circ \rfloor + 1 \in \{1, \dots, 60\}$.
   - Invariant only if $C(t)$ is constant $\forall t \in T$.

---

### 3.4 Vimshottari Dasha Uncertainty Calculation

To prevent misleading timelines, the calculation engine explicitly converts Moon longitude into continuous unwrapped coordinates and computes exact date-boundary uncertainties:

1. **Moon Longitude Unwrapping & Nakshatra Invariance:**
   - Moon longitude $\lambda_{\text{Moon}}(t)$ is unwrapped across $T$ into $\lambda_{\text{unwrapped}}(t)$.
   - The engine verifies whether the Moon's Nakshatra is invariant. If the Moon crosses a Nakshatra boundary in $T$, multiple Dasha sequences exist; in such cases, the sequence is qualified or suppressed.
2. **Relative Arc Position & Balance Interval:**
   - Let $N_{\text{start}} = \lfloor \lambda_{\text{unwrapped}}(t) / 13^\circ 20' \rfloor \times 13^\circ 20'$.
   - The relative elapsed arc is:
     $$\Delta \lambda(t) = \lambda_{\text{unwrapped}}(t) - N_{\text{start}} \in [0^\circ, 13^\circ 20')$$
   - Minimum and maximum elapsed arcs over $T$ are:
     $$\Delta \lambda_{\min} = \min_{t \in T} \Delta \lambda(t), \quad \Delta \lambda_{\max} = \max_{t \in T} \Delta \lambda(t)$$
   - Remaining Mahadasha balance at birth spans an exact interval $[D_{\text{balance},\min}, D_{\text{balance},\max}]$:
     $$D_{\text{balance},\min} = \frac{13^\circ 20' - \Delta \lambda_{\max}}{13^\circ 20'} \times D_{\text{Maha}}(\text{Lord})$$
     $$D_{\text{balance},\max} = \frac{13^\circ 20' - \Delta \lambda_{\min}}{13^\circ 20'} \times D_{\text{Maha}}(\text{Lord})$$
3. **Date Uncertainty Propagation:**
   - Every subsequent Mahadasha, Antardasha, and Pratyantardasha start and end date is computed as an explicit interval $[Date_{\text{earliest}}, Date_{\text{latest}}]$.
4. **Pratyantardasha Period-Boundary Uncertainty Measurement:**
   - For any target Pratyantardasha period:
     $$\text{Start-Boundary Uncertainty } W_{\text{start}} = Date_{\text{latest}}(\text{PratStart}) - Date_{\text{earliest}}(\text{PratStart})$$
     $$\text{End-Boundary Uncertainty } W_{\text{end}} = Date_{\text{latest}}(\text{PratEnd}) - Date_{\text{earliest}}(\text{PratEnd})$$
     $$\text{Period-Boundary Uncertainty } W_{\text{period}} = \max(W_{\text{start}}, W_{\text{end}})$$
   - Nominal duration of the period is:
     $$D_{\text{Prat}} = Date_{\text{nominal}}(\text{PratEnd}) - Date_{\text{nominal}}(\text{PratStart})$$
   - **25% Reliability Rule:** If $W_{\text{period}} > 0.25 \times D_{\text{Prat}}$, the Pratyantardasha micro-period is classified as **Unreliable and Suppressed** from the paid report.

---

## 4. D-9 Continuous Arc Boundary Detection Algorithm

1. Continuous unwrapped coordinate space $\lambda_{\text{unwrapped}}(t)$ is evaluated over $T$.
2. Stationary points ($d\lambda/dt = 0$) are identified where a planet reverses direction (Direct $\leftrightarrow$ Retrograde).
3. The interval $[t_{\text{start}}, t_{\text{end}}]$ is partitioned at every stationary point into strictly monotonic segments.
4. Global extrema $\lambda_{\min}$ and $\lambda_{\max}$ are determined.
5. Invariance is verified if and only if:
   $$\left\lfloor \frac{\lambda_{\min}}{3^\circ 20'} \right\rfloor \equiv \left\lfloor \frac{\lambda_{\max}}{3^\circ 20'} \right\rfloor$$
6. If the floor values differ, the planetary D-9 placement is flagged as **Variant** and suppressed from the chart.

---

## 5. Classical Yogas & Complete Functional-Lordship Rulebook

### 5.1 Panch Mahapurusha Yogas
- **Classical Sources:** BPHS, Ch. 75, Verses 1–12; *Phaladeepika*, Ch. 6, Verses 1–4.
- **Rule:** Mars (Ruchaka), Mercury (Bhadra), Jupiter (Hamsa), Venus (Malavya), or Saturn (Sasa) in **Own Sign or Exaltation Sign**, placed in a **Kendra (1, 4, 7, 10) from Lagna**.
- **Separation:** Formation is binary (True/False). Combustion and afflictions alter external manifestation quality, but do not erase structural formation.

### 5.2 Gaja Kesari Yoga
- **Classical Sources:** BPHS, Ch. 36, Verses 3–4; *Phaladeepika*, Ch. 6, Verses 7–8.
- **Rule:** Jupiter in a **Kendra (1, 4, 7, 10) from natal Moon**.
- **Separation:** Formation evaluated independently from dignity. Debilitation in Capricorn or solar combustion are documented as qualitative impediments without arbitrary cancellation thresholds.

### 5.3 Budhaditya Yoga
- **Classical Sources:** *Saravali*, Ch. 35; *Brihat Jataka*, Ch. 14.
- **Rule:** Sun and Mercury in the **same sidereal Rashi**.
- **No Arbitrary Thresholds:** Reports structural formation, calculated angular separation in degrees/minutes, and Mercury's ephemeris combustion status (`is_combust`) without inventing artificial quality cutoffs.

### 5.4 Complete Jupiter Functional-Lordship Classification (All 12 Ascendants)

Jupiter rules **Sagittarius** and **Pisces**. In Parashari doctrine, lordship of Trikonas (1, 5, 9) confers functional beneficence; lordship of Dusthanas (3, 6, 8, 11, 12) confers functional maleficence; lordship of Kendras (4, 7, 10) incurs Kendradhipati Dosha for natural benefics unless offset by Lagna lordship.

| Ascendant (*Lagna*) | Jupiter Owned Houses | Parashari Lordship Analysis | Functional Classification | Kuja Bhanga Eligibility |
| :--- | :--- | :--- | :--- | :--- |
| **Aries (Mesha)** | 9th & 12th | 9th is primary Moolatrikona (Bhagya Trikona); dominates 12th. | **Functional Benefic** | **Eligible** for structural cancellation |
| **Taurus (Vrishabha)** | 8th & 11th | 8th is Moolatrikona; 11th is severe Upachaya/Dusthana. | **Functional Malefic** | **Excluded** from structural cancellation |
| **Gemini (Mithuna)** | 7th & 10th | Dual Kendra ownership incurs severe Kendradhipati Dosha. | **Afflicted / Neutral-Malefic** | **Excluded** from structural cancellation |
| **Cancer (Karka)** | 6th & 9th | 9th is supreme Trikona; Jupiter exalted in Lagna. | **Functional Benefic** | **Eligible** for structural cancellation |
| **Leo (Simha)** | 5th & 8th | 5th is Moolatrikona (Trikona); intimate friend of Sun. | **Functional Benefic** | **Eligible** for structural cancellation |
| **Virgo (Kanya)** | 4th & 7th | Dual Kendra ownership incurs Kendradhipati Dosha; enemy of Mercury. | **Afflicted / Neutral-Malefic** | **Excluded** from structural cancellation |
| **Libra (Tula)** | 3rd & 6th | 3rd is Moolatrikona; 6th is evil Dusthana (*Rogasthana*). | **Potent Functional Malefic** | **Excluded** from structural cancellation |
| **Scorpio (Vrischika)** | 2nd & 5th | 5th is Trikona; 2nd is Maraka/Dhana; intimate friend of Mars. | **Functional Benefic** | **Eligible** for structural cancellation |
| **Sagittarius (Dhanu)** | 1st & 4th | 1st (Lagna Lord) cleanses Kendra blemish; paramount benefic. | **Paramount Functional Benefic** | **Eligible** for structural cancellation |
| **Capricorn (Makara)** | 12th & 3rd | 12th is Moolatrikona; 3rd is Dusthana; bitter enemy of Saturn. | **Functional Malefic** | **Excluded** from structural cancellation |
| **Aquarius (Kumbha)** | 11th & 2nd | 11th is Moolatrikona (major Upachaya/Dusthana); enemy of Saturn. | **Functional Malefic** | **Excluded** from structural cancellation |
| **Pisces (Meena)** | 1st & 10th | 1st (Lagna Lord) cleanses Kendra blemish; paramount benefic. | **Paramount Functional Benefic** | **Eligible** for structural cancellation |

---

### 5.5 Manglik Dosha (Kuja Dosha) & Structural Cancellation Rules

- **Classical Sources:** *Muhurta Chintamani*, Vivaha Prakarana, Verse 32; *Brihat Parashara Hora Shastra*; *Jataka Parijata*.
- **Primary Standard:** Evaluated from the **natal Ascendant (Lagna)** for Houses 1, 4, 7, 8, 12. Secondary evaluation from Moon is documented separately.

#### Verified Structural Cancellation Rules (*Dosha Bhanga*)
Mars placement in Houses 1, 4, 7, 8, 12 is structurally cancelled if any of the following verified conditions are met:
1. **Mars in Aries in the 1st House:** Mars in own sign in Lagna (*Muhurta Chintamani*).
2. **Mars in Scorpio in the 4th House:** Mars in own sign in 4th house (*Muhurta Chintamani*).
3. **Mars in Capricorn in the 7th or 8th House:** Mars in exaltation (*Jataka Parijata*, *Muhurta Chintamani*).
4. **Mars in Sagittarius or Pisces in the 8th House:** Mars in Jupiter's signs (*Muhurta Chintamani*).
5. **Mars in Leo in the 1st House:** Mars in friendly sign in Lagna (Yogakaraka for Leo Ascendant).
   > [!WARNING]
   > **Cancer Debilitation Removal:** Cancer is Mars's debilitation sign (*Neecha*). Mars in Cancer in 1, 4, 7, 8, 12 is **NOT** a structural cancellation; it remains structurally formed with debilitation vulnerability unless cancelled by Neecha Bhanga Raja Yoga.
6. **Mars aspected by a Functional Benefic Jupiter:** Jupiter casts full classical drishti (5th, 7th, or 9th aspect) upon Mars, where Jupiter is a **functional benefic** according to the table above (Aries, Cancer, Leo, Scorpio, Sagittarius, Pisces). For Taurus, Gemini, Virgo, Libra, Capricorn, and Aquarius Lagnas, Jupiter's aspect does **not** cancel Kuja Dosha.

#### Traditional Mitigations (Not Structural Cancellations)
- **Chandra-Mangala Conjunction:** Mars conjunct Moon forms *Chandra-Mangala Yoga*. Mitigates financial stress, but does **not** erase the structural Kuja placement.
- **Age Maturity (> 28 Years):** Behavioral mitigation based on Martian planetary maturity; not a structural chart cancellation.

---

## 6. Saturn Sade Sati & Dhaiya Ephemeris Precision

1. **Natal Moon Stability Requirement:** If the natal Moon sign crosses a boundary across $T \in [t_{\text{start}}, t_{\text{end}}]$, Sade Sati analysis is **Suppressed**.
2. **Daily Sampling & Bisection Root-Refinement:**
   - Candidate boundary crossings of Saturn are flagged via daily sampling using native `libjme.so`.
   - Ingress and exit timestamps are refined via bisection down to $\le 1\text{ minute}$ of precision.
3. **Retrograde Multi-Ingress Handling:** Multiple entries and exits due to retrogradation are grouped into coherent multi-interval phases.
4. **Gate B Release Fallback:** If the multi-year transit validation test suite has not passed Gate A, the paid PDF will render a **qualitative current-phase indicator** (evaluating Saturn's verified current transit sign relative to natal Moon) rather than an unvalidated dated timeline.
