# Astro Setu — M9.2 Detailed Kundli Report
## Document 2: Detailed Kundli Report Specification & Layout (Version 3.2)

**Document ID:** `ASTRO-M9.2-REPORT-SPEC`  
**Version:** 3.2.0 (FINAL — VERSION 3.2.0 — FROZEN FOR IMPLEMENTATION)  
**Status:** Approved Product & Editorial Specification  
**Author:** DeepMind / Antigravity Pair Programming Engine  
**Target Output:** Print-Ready Bilingual A4 PDF (Dompdf Engine)  
**Proposed Retail Price:** ₹149 (Digital Delivery - Pending Stakeholder Approval)

---

## 1. Product Concept & Editorial Principles

The **Astro Setu Detailed Kundli Report** is an in-depth, personalized digital life dossier engineered to bridge classical Parashari principles with modern psychological clarity and ethical guidance.

### Core Product Principles
1. **Content-Driven Natural Pagination (No Rigid Page Quotas):**
   - The report is strictly **content-driven**. Previous drafts prescribed rigid page-per-section rules; this frozen specification eliminates forced page quotas.
   - Sections flow naturally with clean, professional A4 pagination. Page estimates (approx. 6 to 12 pages) serve as design guidelines rather than contractual page budgets.
   - Only sections that pass **Gate A (Mathematical Validation)** and interval stability checks are rendered. Zero artificial filler, blank trailing pages, or repetitive generic paragraphs.
2. **Interval Stability Integration:**
   - Calculations dynamically adapt to the native's recorded birth uncertainty window $T \in [t_{\text{start}}, t_{\text{end}}]$.
   - A `verified` birth time ($\pm 5\text{ min}$) is not treated as automatic certainty; D-1 and D-9 Ascendants are rendered only if mathematically invariant across the full 10-minute span.
3. **Deterministic & Classical:** 100% rule-based astrological synthesis based on classical texts (*Brihat Parashara Hora Shastra*, *Phaladeepika*, *Muhurta Chintamani*). Zero stochastic LLM generation; zero paid external AI API dependencies.
4. **Non-Fatalistic Governance:** Astrological factors are framed as developmental archetypes, karmic lessons, and opportunities for ethical self-discipline (*Purushartha*).
5. **Full Bilingual Parity:** Flawless parallel text in **English** and **Hindi**, rendered with embedded Google Noto Sans Devanagari typography.

---

## 2. Dynamic Feature & Section Availability Matrix

The detailed report dynamically adapts its layout based on whether calculated parameters are **Stable & Reportable**, **Reportable with Qualification**, or **Unstable & Suppressed** across the native's recorded birth uncertainty window $T \in [t_{\text{start}}, t_{\text{end}}]$:

| Report Section / Calculation | Verified Time ($\pm 5$ min) | Approximate Time ($\pm 1$–2 hr) | Unknown Time (Full Local Civil Day) |
| :--- | :--- | :--- | :--- |
| **1. Cover & Native Coordinates** | Stable & Reportable | Stable with interval note | Stable with date-only note |
| **2. Birth Panchanga Limbs** | Stable (Qualified if boundary crossed in window)| Qualified if transition occurs in window | **QUALIFIED** (Vaara qualified across sunrise; Moon limbs qualified across boundaries) |
| **3. Avakahada Chakra** | Stable & Reportable | Stable (Suppressed if Moon crosses sign/nakshatra) | Stable only for invariant limbs; shifting limbs suppressed |
| **4. D-1 Lagna Kundli & Bhavas** | Stable (Suppressed if Lagna crosses boundary) | Candidate charts if all calculated; else **SUPPRESSED** | **SUPPRESSED** (Replaced by Chandra Kundli) |
| **5. Chandra Kundli (Moon Chart)** | Stable & Reportable | Stable (Suppressed if Moon sign crosses boundary)| Stable (Suppressed if Moon sign crosses boundary) |
| **6. D-9 Navamsha Chart & Placements**| Invariant planets rendered; D-9 Lagna rendered only if invariant | Invariant planets rendered; D-9 Lagna suppressed | Invariant planets rendered; D-9 Lagna suppressed |
| **7. Parashari Graha Drishti Matrix**| Full 7-planet matrix | Full matrix | Planetary aspects rendered; House aspects suppressed |
| **8. Janma Nakshatra Deep Dive** | Stable & Reportable | Stable (Suppressed if boundary crossed) | Stable (Suppressed if boundary crossed) |
| **9. Vimshottari Mahadasha Timeline**| Calculated interval bounds ($[Date_{\min}, Date_{\max}]$)| Calculated interval bounds ($[Date_{\min}, Date_{\max}]$)| Calculated interval bounds ($[Date_{\min}, Date_{\max}]$) |
| **10. Pratyantardasha (24 Months)** | Rendered only if period uncertainty $\le 25\%$ of duration| **SUPPRESSED** (variance exceeds reliability threshold)| **SUPPRESSED** |
| **11. Classical Planetary Yogas** | Full suite (Lagna + Moon) | Moon yogas stable; Lagna yogas qualified | Moon yogas stable; Lagna yogas suppressed |
| **12. Manglik Dosha Audit** | Full (Lagna + Moon) | Candidate charts if calculated; else Moon-Manglik | Lagna Manglik suppressed; Moon Manglik evaluated |
| **13. Saturn Sade Sati & Dhaiya** | Stable (Suppressed if Moon sign unstable) | Stable (Suppressed if Moon sign unstable) | Stable (Suppressed if Moon sign unstable) |
| **14. Classical Vedic Remedies** | Stable & Reportable | Stable & Reportable | Stable & Reportable |
| **15. Astrologer Referral & Disclaimer**| Stable & Reportable | Stable & Reportable | Stable & Reportable |
| **APPROXIMATE PAGE ESTIMATE** | **~10 – 12 Pages** | **~8 – 10 Pages** | **~6 – 8 Pages** |

---

## 3. Detailed Specification of Report Sections

### Section 1: Cover Page & Native Coordinates
- **Header:** Astro Setu sacred geometry motif and official report insignia.
- **Native Details:** Full Name, Date of Birth, Local Time of Birth (or interval indicator), Place of Birth (City, State, Country, Lat/Long, Timezone).
- **Astrological Coordinates:** Sidereal Ayanamsha (True Chitra Paksha / Lahiri), Ephemeris Source (Moshier Analytical Ephemeris via native `libjme.so`).
- **Precision Badge:** Distinct visual badge displaying `Verified Time (Hospital/Clock)`, `Approximate Window (±X hr)`, or `Date Only (Time Unknown)`.
- **Integrity Notice:** Explains that report calculations dynamically adapt to the precision tier to preserve mathematical honesty.

### Section 2: Panchanga at Birth & Avakahada Chakra
- **Vedic Panchanga Parameters:** Tithi, Paksha, Vaara (Solar weekday), Nakshatra & Pada, Yoga, Karana, Sunrise/Sunset — evaluated via interval boundary tracking.
  - **Unknown-Time Vaara Rule:** For an `unknown` birth time, the local civil day spans sunrise. Times before sunrise belong to the preceding solar day; times after sunrise belong to the current civil day. Vaara is explicitly qualified with the sunrise boundary.
- **Avakahada Chakra:** Varna, Vashya, Yoni, Gana, Nadi, Tatva, Paya.
- **Editorial Safeguard:** Avakahada Nadi is defined as an ancient energetic constitution and temperament classification. It is **explicitly disclaimed as NOT a medical, physiological, or biological diagnosis**.

### Section 3: Lagna Kundli (D-1) & Planetary Detail Table
- **Vector SVG Diagram:** Crisp North Indian diamond chart displaying the 12 houses, Ascendant degree, and planetary glyphs with retrogression `[R]` and combustion `*` indicators.
  - *Multi-Candidate Stability Requirement:* If the D-1 Ascendant crosses one or more sign boundaries across interval $T$, house-dependent interpretations and Lagna-dependent yogas must **not** be presented as certain. A qualified multi-candidate presentation is permitted **only** when every candidate chart and its dependent calculations have actually been calculated and clearly labeled. Otherwise, unstable house-dependent content is suppressed, and the report relies upon Chandra Kundli.
- **Planetary Position Table (Columns):**
  1. Planet (*Graha*)
  2. Absolute Sidereal Longitude ($\Lambda$)
  3. Sign (*Rashi*)
  4. Degree, Minute, Second
  5. Motion (*Gati*): Direct / Retrograde
  6. Combustion (*Asta*): Yes / No
  7. Classical Dignity: Exalted, Debilitated, Own Sign, Great Friend, Neutral, Enemy
  8. Nakshatra & Pada
  9. House Placement (*Bhava*)
  10. House Lordship (e.g. 5th & 10th Lord)

### Section 4: Chandra Kundli & Emotional Blueprint
- **Vector SVG Diagram:** North Indian Chandra Kundli (Moon sign rotated to 1st House). Rendered whenever Moon sign is invariant across $T$.
- **Analytical Substance:**
  - Moon's sign, nakshatra, and house placement relative to other planets.
  - Emotional resilience, intuitive style, psychological processing patterns, and relational disposition.
  - Moon-Mercury and Moon-Jupiter alignments.

### Section 5: D-9 Navamsha Chart & Vargottama Analysis
- **Vector SVG Diagram:** North Indian D-9 Navamsha Chart.
  - Certified invariant planets rendered across all precision tiers.
  - D-9 Ascendant glyph rendered **ONLY if D-9 Lagna is invariant across the entire interval $T$**. If volatile, D-9 Ascendant is omitted, and the chart functions as an unrotated sign distribution.
- **Vargottama Highlights:** Identifies planets occupying identical signs in D-1 and D-9, noting their deep internal resilience and enduring character strength.
- **Inner Dharma & Purpose:** Interprets verified planetary D-9 placements as the internal spiritual and psychological foundation that matures over adulthood.

### Section 6: Parashari Graha Drishti (Aspect Matrix)
- **Aspect Grid:** Pure 7-Planet Parashari aspect matrix.
- Clearly displays standard 7th house aspects for all 7 classical planets.
- Highlights special classical aspects (*Vishesha Drishti*):
  - Mars: 4th and 8th house aspects.
  - Jupiter: 5th and 9th trine aspects.
  - Saturn: 3rd and 10th house aspects.
- Descriptive interpretation of prominent aspect pairs (e.g. Jupiter aspecting Mars = guided action; Saturn aspecting Moon = emotional discipline).
- **Purity Standard:** Lunar nodes (Rahu and Ketu) are excluded from the aspect matrix in accordance with foundational Parashari doctrine.

### Section 7: Janma Nakshatra Deep Dive
- Comprehensive exposition of the birth star (Moon's Nakshatra):
  - Ruling Deity (*Devata*) and cosmic role.
  - Sacred Symbol and esoteric significance.
  - Specific Pada analysis (Dharma, Artha, Kama, Moksha).
  - Psychological strengths, potential shadow traits, and ideal vocations.

### Section 8: Vimshottari Mahadasha Master Timeline
- 120-year chronological overview of Mahadashas and Antardashas.
- Table of start dates, end dates, and planetary durations, presented as **calculated interval bounds** $[Date_{\min}, Date_{\max}]$ derived from the Moon's unwrapped arc bounds in the birth window.
- Narrative explaining the psychological theme and focus of each planetary era.

### Section 9: Current Running Dasha & Pratyantardasha (Conditional)
- Focused 24-month operational window.
- Active Mahadasha, Antardasha, and sub-sub period (**Pratyantardasha**).
- Practical guidance: "Current Focus & Opportunities" vs. "Areas Requiring Mindfulness".
- *Period Uncertainty Measurement:* Start-boundary uncertainty $W_{\text{start}}$ and end-boundary uncertainty $W_{\text{end}}$ are computed. If the greater period-boundary uncertainty $W_{\text{period}} = \max(W_{\text{start}}, W_{\text{end}}) > 0.25 \times D_{\text{Prat}}$, the micro-period timeline is classified as unreliable and suppressed.

### Section 10: Classical Planetary Yogas Analysis
- Systematic catalog of validated Yogas:
  - **Panch Mahapurusha Yogas:** Ruchaka (Mars), Bhadra (Mercury), Hamsa (Jupiter), Malavya (Venus), Sasa (Saturn) — evaluated strictly from Lagna Kendra with own/exaltation signs (BPHS Ch. 75).
  - **Gaja Kesari Yoga:** Jupiter in Kendra from Moon, evaluating dignities and impediments.
  - **Budhaditya Yoga:** Sun-Mercury conjunction in the same Rashi, reporting exact angular separation and astronomical combustion status without artificial quality cutoffs.
- Clear distinction between *Structural Formation* and *Expression Quality*.

### Section 11: Manglik Dosha Audit & Cancellation Analysis
- Evaluated from Lagna (primary) and Moon (secondary).
- Transparent check of Houses 1, 4, 7, 8, and 12.
- **Verified Classical Cancellation (*Bhanga*) Audits:** Explicitly checks verified sign-specific exceptions (Mars in Aries in 1st, Scorpio in 4th, Capricorn in 7th/8th, Sagittarius/Pisces in 8th, Leo in 1st) and functional benefic Jupiter aspects.
- **Functional Jupiter Lordship Rule:** Jupiter's aspect cancels Kuja Dosha only for its functional benefic Ascendants (**Aries, Cancer, Leo, Scorpio, Sagittarius, Pisces**). For Taurus, Gemini, Virgo, **Libra**, Capricorn, and Aquarius, Jupiter's aspect does **not** cancel Kuja Dosha.
- **Cancer Debilitation Clarity:** Explicitly states that Mars in Cancer is **NOT** a cancellation; Cancer is Mars's debilitation sign (*Neecha*), requiring conscious emotional calibration.
- **Distinction of Mitigations:** Documents *Chandra-Mangala Yoga* and age $> 28$ as traditional behavioral mitigations, not structural erasures of the dosha.
- Final clear conclusion: **Dosha Confirmed**, **Dosha Cancelled (*Bhanga*)**, or **No Dosha Present**.

### Section 12: Saturn Sade Sati & Transit Timeline
- Evaluates transit Saturn relative to the natal Moon sign.
- Displays Rising Phase (12th), Peak Phase (1st), Setting Phase (2nd), and Dhaiya periods (4th and 8th from Moon).
- *Precision Safeguard:* Suppressed if natal Moon sign is unstable across the birth-time interval.
- *Gate B Release Policy:* If the multi-year transit validation suite has passed Gate A, displays exact date intervals derived from bisection root-refinement; otherwise, provides a **qualitative current-phase indicator** as a verified fallback.

### Section 13: Classical Vedic Remedies (Upay)
- Grounded, ethical remedial framework:
  - **Vedic Mantras:** Planetary Beej mantras and classical prayers with recommended daily cadence.
  - **Charity (*Dāna*):** Practical acts of generosity aligned with planetary lessons (feeding animals, supporting underprivileged students, environmental stewardship).
  - **Mindfulness & Lifestyle:** Behavioral adjustments (e.g. practicing active listening, maintaining routine sleep habits).
  - **Gemstone Disclaimer:** Explicit notice that physical gemstones are **NOT prescribed automatically** by an algorithm. Ethical disciplines and personal awareness are highlighted as primary remedies.

### Section 14: Astrologer Referral, Precision Audit & Disclaimers
- Contextual referral to verified Astro Setu astrologers for personal consultation.
- Precision audit summary documenting which calculations were performed vs. suppressed.
- **Mandatory Ethical & Legal Disclaimer:** Astrological analysis is an interpretive science for self-discovery and personal reflection. Astro Setu strictly prohibits medical diagnosis, financial speculation, legal advice, or fatalistic guarantees.

---

## 4. PDF Typography & Visual Layout Discipline

1. **Natural Pagination & Flow:**
   - Instead of imposing rigid page breaks after every sub-element, related visual blocks flow naturally.
   - Section breaks use CSS `page-break-before: always;` only at major thematic boundaries (e.g. Cover Page, Charts, Dasha Timeline, Remedies).
2. **Table-Based Multi-Column Architecture:**
   - Multi-column tables use pure HTML `<table>` elements with fixed widths and `table-layout: fixed;` (Dompdf standard).
3. **Typography & Font Integrity:**
   - Embedded TrueType fonts: `NotoSansDevanagari-Regular.ttf` and `NotoSansDevanagari-Bold.ttf`.
   - CSS `line-height: 1.45;` applied to table cells to prevent Devanagari vowel markers (*matras*) from clipping against cell borders.

---

## 5. Commercial & Operational Status (Pending Decisions)

The following operational parameters are formally identified as **pending stakeholder approval**:
1. **₹149 Retail Price Point:** Subject to commercial validation.
2. **90-Day PDF Disk Retention:** Subject to server storage capacity validation.
3. **365-Day Regeneration Eligibility:** Subject to operational policy sign-off.
4. **Wallet-Only Payment Method for Phase A:** Subject to product approval regarding whether direct payment gateway checkout (e.g. Razorpay modal) is needed.
5. **Dated Transit Table vs. Qualitative Sade Sati Fallback:** Subject to completion and sign-off of the multi-year transit validation test fixtures under Gate A.
