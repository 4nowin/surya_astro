# Astro Setu — M9.2 Detailed Kundli Report
## Document 3: Technical Architecture & System Design (Version 3.2)

**Document ID:** `ASTRO-M9.2-TECH-ARCH`  
**Version:** 3.2.0 (FINAL — VERSION 3.2.0 — FROZEN FOR IMPLEMENTATION)  
**Status:** Approved Technical Architecture  
**Author:** DeepMind / Antigravity Pair Programming Engine  
**Baseline Environment:** Laravel 13.x / PHP 8.4+ / Hostinger Shared Hosting / Native `libjme.so` (via `NativeEphemerisResolver`)

---

## 1. Architectural Strategy & Environmental Reality

Generating an in-depth bilingual PDF report with mathematical boundary checks, classical yoga evaluations, and ephemeris-based transits introduces specific infrastructure requirements on Astro Setu's actual production environment:

1. **Hostinger Shared Hosting Web SAPI Constraint:**
   - In shared hosting environments (Apache/LiteSpeed Web SAPI), PHP FFI (`ffi.enable=true`) is restricted or completely disabled for inbound web requests.
   - Any ephemeris computation requiring native Moshier C binaries (`libjme.so` on Linux glibc 2.28; `jme.dll` on Windows) must execute inside a **detached CLI worker process** (`CalculationWorkerLauncher` / `CalculationJobManager`), where PHP CLI operates with unrestricted FFI access.
   - Generic queue daemons (`php artisan queue:work`) cannot remain persistently active on Hostinger shared hosting due to cPanel process killing policies and memory watchdogs. Astro Setu must strictly reuse its **existing, proven detached CLI worker architecture**.
2. **Resource Consumption & Performance Benchmarking:**
   - Dompdf rendering with embedded TrueType Devanagari fonts is CPU and RAM intensive.
   - **Targets to Measure and Benchmark (Not Presumed Facts):**
     - Target peak memory budget: $\le 95\text{ MB}$ (to be empirically measured via `memory_get_peak_usage(true)` across staging test suites).
     - Target end-to-end generation duration: $\le 5.0\text{ seconds}$ on benchmark server hardware.
   - Executing this synchronously inside the HTTP web request risks gateway timeouts (HTTP 504) and web server worker pool exhaustion.
3. **Idempotent Commerce & Immutable Ledger:**
   - The user's wallet balance resides directly on `users.wallet_balance`.
   - Purchase of the report must be strictly idempotent, guarded by unique idempotency keys, database row locking (`lockForUpdate()`), and dedicated compensating ledger records.
   - Failure in PDF generation must never leave the user billed without an automated, idempotent refund path.

---

## 2. Reusable Existing Services vs. Proposed New Components

### 2.1 Reusable Existing Architecture
The Astro Setu backend already contains foundational components that will be reused directly without re-engineering:
- **`App\Services\Astrology\NativeEphemerisResolver`:** Authoritative native library resolver that loads `libjme.so` on Linux glibc 2.28 and `jme.dll` on Windows.
- **`App\Services\Astrology\KundliService`:** Core sidereal planetary coordinates, Lahiri ayanamsha, retrogression, combustion, and house mappings.
- **`App\Services\Astrology\DashaService`:** 120-year Vimshottari Mahadasha and Antardasha calculations.
- **`App\Services\Astrology\Calculation\CalculationJobManager`:** Authoritative manager for leased background calculation jobs (`astrology_calculation_jobs` table).
- **`App\Services\Astrology\Calculation\CalculationWorkerLauncher`:** Detached process spawner using `proc_open` to execute CLI workers under unrestricted FFI.

### 2.2 Proposed Phase A Service Architecture

```
+----------------------------------------------------------------------------------------------------+
| PROPOSED SERVICE ARCHITECTURE (PHASE A)                                                            |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|    [ DetailedKundliReportService ] (Master Orchestrator)                                           |
|                  |                                                                                 |
|                  +---> [ KundliService ] (Natal Base: Positions, Dignity, Houses)                  |
|                  |                                                                                 |
|                  +---> [ VargaCalculationService ] (D-9 Navamsha with Continuous Arc Boundary Check)|
|                  |                                                                                 |
|                  +---> [ PlanetaryAspectService ] (Pure Parashari 7-Graha Drishti Matrix)          |
|                  |                                                                                 |
|                  +---> [ YogaDoshaService ] (Panch Mahapurusha, Gaja Kesari, Budhaditya, Manglik)  |
|                  |                                                                                 |
|                  +---> [ ExtendedDashaService ] (3rd-level Pratyantardasha Micro-Timing)           |
|                  |                                                                                 |
|                  +---> [ TransitAnalysisService ] (Sade Sati Bisection Ingress/Exit Intervals)     |
|                  |                                                                                 |
|                  +---> [ DetailedInterpretationService ] (100% Deterministic Bilingual Matrices)   |
|                                                                                                    |
|    [ PremiumKundliPdfService ]                                                                     |
|                  |                                                                                 |
|                  +---> [ SvgVargaRenderer ] (Vector SVGs for D-1, Chandra, and D-9 Charts)        |
|                  |                                                                                 |
|                  +---> [ Dompdf Wrapper ] (Noto Sans Devanagari font injection & A4 layout)        |
+----------------------------------------------------------------------------------------------------+
```

---

## 3. Worker Lease Renewal, Ownership Fencing & Safe PDF Publication

### 3.1 Verified Reality of Existing CalculationJobManager
Inspection of the actual codebase (`app/Services/Astrology/Calculation/CalculationJobManager.php`) reveals:
1. `claimNextJob(string $workerId, int $leaseSeconds = 60)` atomically claims a job and sets `lease_expires_at = now()->addSeconds($leaseSeconds)`.
2. **Current Limitation:** There is currently **NO lease renewal mechanism, heartbeat, or process-liveness check** in `CalculationJobManager`.
3. If an operation—such as Dompdf compiling a multi-page document with embedded fonts—takes longer than the lease window, `$existingJob->isStale()` evaluates to `true`.
4. A sweeper or subsequent request could then re-queue the job, causing a second worker to pick it up and process concurrently. Checkpoint-only renewal is insufficient if an individual sub-step blocks for longer than the lease.

### 3.2 Safe Implementable Design Specification
To ensure worker safety without race conditions or corrupted output, Phase A specifies an ownership fencing and publication protocol:

1. **Worker Identity & Heartbeat:**
   - On launch, each CLI worker process generates a cryptographically unique `$workerId = bin2hex(random_bytes(16))`.
   - Inspection of actual PHP CLI and Hostinger execution capabilities during implementation will determine the heartbeat mechanism (e.g. granular sub-task lease extensions, tick-based heartbeats, or an auxiliary process monitor).
   - `CalculationJobManager` provides an explicit lease renewal method:
     ```php
     public function renewLease(AstrologyCalculationJob $job, string $workerId, int $additionalSeconds = 60): bool
     {
         $affected = AstrologyCalculationJob::where('id', $job->id)
             ->where('status', AstrologyCalculationJob::STATUS_PROCESSING)
             ->where('worker_id', $workerId)
             ->update([
                 'lease_expires_at' => now()->addSeconds($additionalSeconds),
             ]);

         return $affected > 0;
     }
     ```
2. **Ownership Fencing & Response to Lost Lease:**
   - If `renewLease()` returns `false` (indicating that the lease expired and another worker reclaimed the job), the current worker **must immediately abort execution and stop publication**. It must never attempt to write the final PDF or update the report status.
3. **Unique Temporary PDF Output Paths:**
   - Workers write intermediate output to an isolated, attempt-specific temporary path:
     `storage/app/private/reports/tmp/{uuid}_{workerId}_{attempt}.pdf`.
   - Two workers processing different attempts will **never write to the same temporary file**.
4. **Safe Final Publication Operation:**
   - Once generation is complete, the worker enters an atomic database transaction:
     ```sql
     -- Verify worker still owns the job and report is still processing
     SELECT id FROM kundli_reports WHERE id = ? AND status = 'processing' FOR UPDATE;
     ```
   - Only if ownership is certified under lock does the worker atomically move/rename the temporary file to its final destination:
     `storage/app/private/reports/{year}/{month}/{uuid}.pdf`.
   - The report row is updated to `status = 'completed'` with `file_path`, `file_hash`, and `generation_duration_ms`.
5. **Crash Recovery & Temp File Cleanup:**
   - A dead worker stops emitting heartbeats. Once `lease_expires_at` is in the past, the sweeper safely resets the job to `pending` (up to `max_attempts = 3`).
   - A scheduled cleanup task sweeps orphaned files in `storage/app/private/reports/tmp/` older than 1 hour.

---

## 4. Wallet Integration & Database-Enforced Financial Constraints

Astro Setu stores the user's current balance directly on `users.wallet_balance`. There is no pre-existing generic `wallet_transactions` table.

### 4.1 Strict Financial Invariants
1. **At Most One Compensating Refund Per Debit (Database-Enforced):**
   - The `report_purchases` table declares `related_purchase_id` as **`UNIQUE`**.
   - In MySQL/InnoDB, unique indexes permit multiple `NULL` values (for debit rows), but guarantee that any specific non-null `related_purchase_id` appears **at most once**. This mathematically prevents multiple refunds against the same debit.
2. **Refunds Reference Debits Only:**
   - A debit record has `related_purchase_id = NULL`.
   - A compensating refund has a non-null `related_purchase_id`.
   - A refund must strictly reference an original debit, never another refund.
3. **Consistent Amount Semantics:**
   - `amount` is always an absolute positive value (`DECIMAL(8, 2) NOT NULL`).
   - `transaction_type = 'debit'` indicates a deduction of ₹149 from `users.wallet_balance`.
   - `transaction_type = 'compensating_refund'` indicates a credit of ₹149 back to `users.wallet_balance`.
4. **Atomic Purchase & Wallet Mutation:**
   - Executed inside `DB::transaction()` with `users.wallet_balance` locked via `lockForUpdate()`. Balance mutation and ledger insertion commit simultaneously.

---

### 4.2 Database Constraints vs. Transactional Application Validation

| Safeguard Dimension | Database-Enforced Constraint | Transactional Application-Level Validation |
| :--- | :--- | :--- |
| **Unique Refund per Debit** | `UNIQUE KEY (related_purchase_id)` prevents second refund insert. | Validates that no pending refund is in progress prior to insert. |
| **Foreign Key Integrity** | `FOREIGN KEY (related_purchase_id) REFERENCES report_purchases(id)`.| Validates that referenced record has `transaction_type == 'debit'`. |
| **User Balance Safety** | Balance cannot be mutated without valid user row existence. | Locks user row with `lockForUpdate()`; checks `wallet_balance >= amount`. |
| **Idempotency Guard** | `UNIQUE KEY (idempotency_key)` prevents duplicate purchase rows. | Catches duplicate key exceptions and returns existing purchase state. |

---

### 4.3 Database Schema Specification

```sql
-- 1. Authoritative Financial Ledger for Reports
CREATE TABLE `report_purchases` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NULL, -- Set to NULL on user account deletion
    `idempotency_key` VARCHAR(64) NOT NULL UNIQUE,
    `transaction_type` ENUM('debit', 'compensating_refund') NOT NULL,
    `amount` DECIMAL(8, 2) NOT NULL, -- Always positive
    `currency` VARCHAR(3) NOT NULL DEFAULT 'INR',
    `related_purchase_id` BIGINT UNSIGNED NULL UNIQUE, -- UNIQUE: Mathematically prevents duplicate refunds
    `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'completed',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    INDEX `idx_user_txn` (`user_id`, `transaction_type`),
    INDEX `idx_idempotency` (`idempotency_key`),
    CONSTRAINT `fk_report_purchases_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_report_purchases_related` FOREIGN KEY (`related_purchase_id`) REFERENCES `report_purchases` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Report Delivery, Snapshot & Lifecycle State
CREATE TABLE `kundli_reports` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NULL, -- Set to NULL on user account deletion
    `report_purchase_id` BIGINT UNSIGNED NOT NULL UNIQUE, -- 1:1 link to authoritative debit record
    
    -- Versioning & Snapshot Parameters for Exact Reproducibility
    `birth_payload` JSON NULL, -- Scrubbed upon account deletion
    `language` VARCHAR(5) NOT NULL DEFAULT 'en',
    `ayanamsa_id` VARCHAR(30) NOT NULL DEFAULT 'lahiri_true_chitra',
    `ayanamsa_version` VARCHAR(10) NOT NULL DEFAULT '1.0.0',
    `calculation_engine_version` VARCHAR(10) NOT NULL DEFAULT '1.0.0',
    `interpretation_rule_version` VARCHAR(10) NOT NULL DEFAULT '1.0.0',
    `report_template_version` VARCHAR(10) NOT NULL DEFAULT '1.0.0',
    
    -- Delivery & Lifecycle State
    `status` ENUM('queued', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'queued',
    `file_path` VARCHAR(255) NULL,
    `file_hash` CHAR(64) NULL, -- SHA-256 of generated PDF
    `file_size_bytes` INT UNSIGNED NULL,
    `page_count` TINYINT UNSIGNED NULL,
    `generation_duration_ms` INT UNSIGNED NULL,
    `error_message` TEXT NULL,
    `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
    
    -- Distinct Expiration Timestamps (Policies subject to approval)
    `pdf_retained_until` TIMESTAMP NULL, -- Disk file retention (e.g. 90 days)
    `regeneration_eligible_until` TIMESTAMP NULL, -- Free regeneration window (e.g. 365 days)
    
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    
    INDEX `idx_user_status` (`user_id`, `status`),
    INDEX `idx_uuid` (`uuid`),
    CONSTRAINT `fk_kundli_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_kundli_reports_purchase` FOREIGN KEY (`report_purchase_id`) REFERENCES `report_purchases` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Storage, Access Control & Privacy Architecture

### 5.1 Private Storage & Bearer URL Security
- **Storage Isolation:** All generated PDF reports are saved to a protected directory located **outside the web server document root**:
  `storage/app/private/reports/{year}/{month}/{uuid}.pdf`.
  File system permissions are set to `0600`.
- **Signed URL Reality:** A temporary signed URL (`URL::temporarySignedRoute()`) is a **bearer link** that can be accessed by anyone in possession of the link during its validity window. Therefore:
  1. The API endpoint issuing the signed URL **must enforce strict Sanctum user authentication and ownership checks** (`auth()->id() === $report->user_id`).
  2. The signed URL has a short validity TTL (15 to 30 minutes).
  3. Direct streaming uses `Storage::download()` with `Content-Type: application/pdf` and `Content-Disposition: attachment; filename="AstroSetu_Kundli_{Name}.pdf"`.

### 5.2 Separation of Expiration Lifecycles
The architecture strictly decouples four independent time domains:
1. **Signed Download URL TTL (15–30 Minutes):** Prevents long-term link sharing.
2. **PDF Disk Retention Period (Decision Pending - Proposed 90 Days):** Reclaims server storage via scheduled cleanup of `.pdf` binaries on disk.
3. **Regeneration Eligibility Window (Decision Pending - Proposed 365 Days):** If a user requests a download after disk file cleanup, the PDF is regenerated on the fly from the stored snapshot without recharging.
4. **Permanent Financial Ledger Record:** The purchase entry in `report_purchases` is retained in accordance with applicable Indian accounting and tax compliance standards.

### 5.3 Account Deletion & Data Scrubbing
- **Policy Statement:** The proposed account-deletion behavior requires formal privacy/legal review before deployment.
- When an account deletion is requested:
  1. The foreign key sets `user_id = NULL` on both `report_purchases` and `kundli_reports`.
  2. The `birth_payload` JSON field is scrubbed/anonymized (removing personal names, coordinates, and exact birth timestamps).
  3. The PDF binary on disk is deleted immediately.
  4. The financial record in `report_purchases` remains intact to preserve statutory accounting audit trails.
