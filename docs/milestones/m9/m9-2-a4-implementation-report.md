# Astro Setu — M9.2 Detailed Kundli Report
## Work Package A4 Implementation & Verification Report

**Document ID:** `ASTRO-M9.2-A4-REPORT`  
**Milestone:** M9.2 — Detailed Kundli Report  
**Work Package:** A4 — Commerce, Ledger & Worker Infrastructure  
**Specification Baseline:** Frozen M9.2 Version 3.2.0  
**Status:** Complete & Verified  
**Date:** 2026-11-05  

---

## 1. Executive Summary

Work Package A4 delivers the complete commerce, financial ledger, background worker reliability, and secure delivery infrastructure for Astro Setu's Detailed Kundli Report, in strict compliance with the frozen M9.2 Version 3.2.0 specifications (`ASTRO-M9.2-TECH-ARCH` and `ASTRO-M9.2-PLAN`).

All components adhere to the production realities of Hostinger shared hosting:
1. **Financial Ledger & Wallet Invariants:** Preserved single-source-of-truth wallet balance on `users.wallet_balance` without introducing arbitrary generic transaction tables. Guaranteed at most one compensating refund per debit via database-level `related_purchase_id UNIQUE` constraint.
2. **Worker Lease Heartbeat & Fencing:** Implemented `CalculationJobManager::renewLease()`, ownership fencing on job mutations and completions, attempt-isolated temporary PDF generation in `storage/app/private/reports/tmp/`, and transactional atomic publication.
3. **Detached CLI Worker Architecture:** Fully decoupled execution via `CalculationWorkerLauncher` and `AstrologyCalculateWorkerCommand`, guaranteeing native Moshier ephemeris execution under unrestricted CLI FFI without blocking web requests.
4. **Secure Sanctum Delivery:** Authenticated temporary signed download URLs (20-minute TTL) with strict user ownership validation and private storage streaming (`storage/app/private/reports/{year}/{month}/{uuid}.pdf`).

---

## 2. Existing Infrastructure Reused

In accordance with architectural guidelines, existing foundational services were reused without duplicate abstractions:
- **`App\Models\User`:** Directly utilized `users.wallet_balance` with `lockForUpdate()` row serialization.
- **`App\Services\Astrology\Calculation\CalculationJobManager`:** Reused as the core background job manager for `'detailed_kundli_report'` jobs, extended with `renewLease()` and ownership verification.
- **`App\Services\Astrology\Calculation\CalculationWorkerLauncher`:** Reused for detached background process launching (`astro:calculate-worker`), supporting both local development and Hostinger production.
- **`App\Services\Astrology\Calculation\CalculationHandlerRegistry`:** Reused for registering `DetailedKundliReportCalculationHandler`.
- **`App\Models\UserAstrologyProfile`:** Reused for resolving birth data, coordinates, and precision windows.

---

## 3. Financial Ledger Migrations & Database Constraints

Two new database migrations were created and executed successfully:

### 3.1 `report_purchases` Table (`2026_11_05_000001_create_report_purchases_table.php`)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `uuid`: CHAR(36) NOT NULL UNIQUE
- `user_id`: BIGINT UNSIGNED NULL (FK to `users(id)` ON DELETE SET NULL)
- `idempotency_key`: VARCHAR(64) NOT NULL UNIQUE
- `transaction_type`: ENUM('debit', 'compensating_refund') NOT NULL
- `amount`: DECIMAL(8, 2) NOT NULL (always positive absolute value)
- `currency`: VARCHAR(3) NOT NULL DEFAULT 'INR'
- `related_purchase_id`: BIGINT UNSIGNED NULL UNIQUE (FK to `report_purchases(id)` ON DELETE RESTRICT)
- `status`: ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'completed'
- `timestamps`: `created_at`, `updated_at`

**Key Invariant Enforced:**  
The database-level `UNIQUE KEY (related_purchase_id)` mathematically guarantees that a debit record can appear as the target of a compensating refund at most once. In MySQL/InnoDB and SQLite, unique indexes permit multiple `NULL` values (for debit rows) while rejecting duplicate non-null foreign keys, ensuring impossible double-refunds at the schema layer.

### 3.2 `kundli_reports` Table (`2026_11_05_000002_create_kundli_reports_table.php`)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `uuid`: CHAR(36) NOT NULL UNIQUE
- `user_id`: BIGINT UNSIGNED NULL (FK to `users(id)` ON DELETE SET NULL)
- `report_purchase_id`: BIGINT UNSIGNED NOT NULL UNIQUE (FK to `report_purchases(id)`)
- `birth_payload`: JSON NULL (anonymized upon user deletion)
- `language`: VARCHAR(5) NOT NULL DEFAULT 'en'
- `status`: ENUM('queued', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'queued'
- `file_path`: VARCHAR(255) NULL
- `file_hash`: CHAR(64) NULL (SHA-256 of published PDF)
- `file_size_bytes`: INT UNSIGNED NULL
- `page_count`: TINYINT UNSIGNED NULL
- `generation_duration_ms`: INT UNSIGNED NULL
- `error_message`: TEXT NULL
- `download_count`: INT UNSIGNED NOT NULL DEFAULT 0
- `pdf_retained_until`: TIMESTAMP NULL (90-day disk retention)
- `regeneration_eligible_until`: TIMESTAMP NULL (365-day free regeneration)

---

## 4. Atomic Wallet Commerce & Idempotency

Implemented in [`App\Services\Astrology\Reports\ReportPurchaseService`](file:///c:/wamp64/www/astro_setu/app/Services/Astrology/Reports/ReportPurchaseService.php):

1. **Transactional Serialization:** All financial operations execute inside `DB::transaction()` with `User::where('id', $userId)->lockForUpdate()->firstOrFail()`.
2. **Balance Validation & Atomic Deduction:** User balance is checked under lock (`wallet_balance >= amount`). If insufficient, throws `InsufficientWalletBalanceException` without modifying the ledger or creating report records.
3. **Strict Idempotency:** Guarded by `UNIQUE KEY (idempotency_key)`. Repeated purchase requests with an identical idempotency key return the existing purchase and report state without double-deduction.
4. **Compensating Refund Safety:** Validates debit ownership, checks existing refunds under lock, credits `users.wallet_balance`, records compensating refund in `report_purchases` (enforced by DB unique constraint), and marks uncompleted reports as `failed`.

---

## 5. Worker Lease Renewal, Ownership Fencing & Publication Safety

Implemented across [`CalculationJobManager`](file:///c:/wamp64/www/astro_setu/app/Services/Astrology/Calculation/CalculationJobManager.php) and [`ReportPublicationService`](file:///c:/wamp64/www/astro_setu/app/Services/Astrology/Reports/ReportPublicationService.php):

1. **Lease Heartbeat:** `CalculationJobManager::renewLease($job, $workerId, $additionalSeconds = 60)` atomically extends `lease_expires_at` only if `$job->worker_id === $workerId` and `lease_expires_at > now()`. Replaced or expired workers receive `false` and must abort immediately.
2. **Ownership Fencing:** `markCompleted` and `markFailed` accept an optional `$workerId` parameter. Status transitions are fenced so that a stale worker cannot overwrite a newer worker's active processing state.
3. **Attempt-Specific Temporary PDF Isolation:** Workers write intermediate output to:
   `storage/app/private/reports/tmp/{reportUuid}_{workerId}_{attempt}.pdf`
   Concurrent workers or retried attempts never write to the same temporary file.
4. **Safe Atomic Publication:**
   - Pre-publication ownership check verifies active lease.
   - Filesystem rename/move to `storage/app/private/reports/{year}/{month}/{reportUuid}.pdf` with file permissions `0600`.
   - Post-move transactional verification locks `kundli_reports` row with `lockForUpdate()`. If worker lost ownership during generation or rename, target file is immediately unlinked and publication aborts.

---

## 6. Secure Report Delivery

Implemented in [`App\Http\Controllers\Api\KundliReportDeliveryController`](file:///c:/wamp64/www/astro_setu/app/Http/Controllers/Api/KundliReportDeliveryController.php) and registered in [`routes/api.php`](file:///c:/wamp64/www/astro_setu/routes/api.php):

1. `POST /api/v1/kundli/reports/purchase`: Sanctum-authenticated purchase endpoint. Atomically deducts wallet balance, creates ledger records, and triggers detached CLI worker.
2. `GET /api/v1/kundli/reports/{reportUuid}/status`: Sanctum-authenticated status polling endpoint with user ownership verification.
3. `GET /api/v1/kundli/reports/{reportUuid}/download-url`: Sanctum-authenticated URL generator issuing temporary signed URLs with 20-minute TTL.
4. `GET /api/v1/kundli/reports/download/{reportUuid}`: Route `kundli.reports.download`. Validates HMAC signature and expiration via `$request->hasValidSignature()`. Streams private PDF attachment with `Content-Type: application/pdf` and increments `download_count`.

---

## 7. Verification Test Results

Three comprehensive test suites were created and executed with 100% pass rate:

### 7.1 A4 Test Suites
```text
   PASS  Tests\Feature\A4ReportCommerceAndLedgerTest
  ✓ successful wallet purchase deducts balance and creates records                                               2.21s  
  ✓ insufficient wallet balance rejects purchase                                                                 0.24s  
  ✓ duplicate idempotency key prevents duplicate deduction                                                       0.27s  
  ✓ successful compensating refund credits wallet                                                                0.31s  
  ✓ duplicate refund prevention                                                                                  0.31s  
  ✓ database enforces unique related purchase id                                                                 0.25s  
  ✓ database enforces unique report purchase id                                                                  0.25s  
  ✓ transaction rollback on failure                                                                              0.25s  
  ✓ user deletion sets null on purchases and reports                                                             0.31s  

   PASS  Tests\Feature\A4WorkerLeaseAndPublicationTest
  ✓ successful lease renewal extends expiration                                                                  0.29s  
  ✓ lease renewal after expiration is rejected                                                                   0.22s  
  ✓ worker cannot renew unowned lease                                                                            0.26s  
  ✓ stale worker cannot update job status                                                                        0.25s  
  ✓ attempt specific temporary file isolation                                                                    0.23s  
  ✓ successful atomic publication                                                                                0.28s  
  ✓ stale worker cannot publish report                                                                           0.30s  
  ✓ temporary file cleanup on failure                                                                            0.24s  

   PASS  Tests\Feature\A4SecureReportDeliveryTest
  ✓ authenticated owner can access status and download url                                                       0.55s  
  ✓ unauthenticated requests are rejected                                                                        0.30s  
  ✓ user cannot access another users report                                                                      0.31s  
  ✓ valid signed url downloads pdf                                                                               0.30s  
  ✓ invalid signature is rejected                                                                                0.27s  
  ✓ expired signed url is rejected                                                                               0.29s  
  ✓ missing report file handled safely                                                                           0.31s  
  ✓ purchase api endpoint triggers detached worker                                                               0.38s  

  A4 Tests Total: 25 passed, 106 assertions (10.27s)
```

### 7.2 Full Regression Test Run (A1, A2, A3)
```text
  PASS  Tests\Feature\A2IndependentBenchmarkTest (6 tests, 114 assertions)
  PASS  Tests\Feature\YogaDoshaFeatureTest (3 tests, 48 assertions)
  PASS  Tests\Feature\MathematicalFoundationStabilityTest (7 tests, 48 assertions)
  PASS  Tests\Unit\Astrology\SaturnTransitServiceTest (11 tests, 126 assertions)
  PASS  Tests\Feature\A3SaturnTransitValidationTest (6 tests, 68 assertions)

  Regression Total: 33 passed, 404 assertions (80.22s)
```

---

## 8. Files Created and Modified

### Created Files:
- `database/migrations/2026_11_05_000001_create_report_purchases_table.php`
- `database/migrations/2026_11_05_000002_create_kundli_reports_table.php`
- `app/Models/ReportPurchase.php`
- `app/Models/KundliReport.php`
- `app/Services/Astrology/Reports/ReportPurchaseService.php`
- `app/Services/Astrology/Reports/ReportPublicationService.php`
- `app/Services/Astrology/Reports/Exceptions/InsufficientWalletBalanceException.php`
- `app/Services/Astrology/Reports/Exceptions/DuplicateRefundException.php`
- `app/Services/Astrology/Reports/Exceptions/InvalidPurchaseException.php`
- `app/Services/Astrology/Calculation/Handlers/DetailedKundliReportCalculationHandler.php`
- `app/Http/Controllers/Api/KundliReportDeliveryController.php`
- `tests/Feature/A4ReportCommerceAndLedgerTest.php`
- `tests/Feature/A4WorkerLeaseAndPublicationTest.php`
- `tests/Feature/A4SecureReportDeliveryTest.php`
- `docs/milestones/m9/m9-2-a4-implementation-report.md`

### Modified Files:
- `app/Models/User.php`: Added `reportPurchases()` and `kundliReports()` relationships.
- `app/Services/Astrology/Calculation/CalculationJobManager.php`: Added `renewLease()`, `verifyOwnership()`, and ownership fencing parameters.
- `app/Console/Commands/AstrologyCalculateWorkerCommand.php`: Passed `$workerId` to `markCompleted` and `markFailed` for worker fencing.
- `app/Providers/AppServiceProvider.php`: Registered `DetailedKundliReportCalculationHandler` in `CalculationHandlerRegistry`.
- `routes/api.php`: Added purchase, status, download URL, and signed download routes.

### Invariants Maintained:
- Zero edits to the 4 frozen M9.2 specification documents.
- Zero modifications to A1, A2, or A3 calculation services or test fixtures.
- Zero modifications to the Flutter codebase.

---

## 9. Explicit A4 Acceptance Recommendation

### Final Decision: **A — A4 acceptance criteria satisfied**

**Justification:**  
All criteria specified in the frozen Version 3.2.0 technical architecture and implementation plan for Work Package A4 have been fully implemented and mathematically verified through automated test suites:
1. `report_purchases` enforces `related_purchase_id UNIQUE` at the database level.
2. `users.wallet_balance` is updated atomically under `DB::transaction()` with `lockForUpdate()`.
3. `CalculationJobManager::renewLease()` is operational and verified.
4. Workers write to attempt-specific temporary files in `storage/app/private/reports/tmp/` and atomically publish upon certified ownership under lock.
5. Detached background execution functions cleanly via `CalculationWorkerLauncher`.
6. Sanctum authentication and signed temporary URLs (20-minute TTL) are enforced for download delivery.

---

## 10. Final Verification Addendum (Evidence Closure)

### 10.1 Fresh Full PHPUnit Suite Execution Results
- **Command:** `php artisan test`
- **Working Tree:** Fully synchronized working tree with all A4 migrations, services, handlers, and tests.
- **Process Exit Code:** `0` (Success)
- **Execution Counts:**
  - **Passed:** 271 tests
  - **Failed:** 0
  - **Skipped:** 0
  - **Risky:** 0
  - **Total Assertions:** 2,801 assertions
  - **Total Duration:** 268.10 seconds

### 10.2 Evidence of Periodic Worker Heartbeat Invocation
- In [`AstrologyCalculateWorkerCommand.php`](file:///c:/wamp64/www/astro_setu/app/Console/Commands/AstrologyCalculateWorkerCommand.php), when claiming the job, the CLI worker explicitly passes `worker_id`, `job_id`, and `attempt` into the calculation payload.
- In [`DetailedKundliReportCalculationHandler::calculate()`](file:///c:/wamp64/www/astro_setu/app/Services/Astrology/Calculation/Handlers/DetailedKundliReportCalculationHandler.php), the worker actively invokes `CalculationJobManager::renewLease($job, $workerId, 90)`.
- If lease renewal fails (e.g. lease expired or job was reclaimed by a sweeper), the handler immediately aborts, deletes the intermediate temporary PDF in `storage/app/private/reports/tmp/`, and marks the report `failed` without publishing.
- Verified in test: `test_worker_heartbeat_renewal_during_calculation_and_abort_on_lease_loss`.

### 10.3 Ownership Fencing Coverage Across Lifecycle
- **Progress & State Updates:** Worker identity is verified prior to status transitions. If a stale worker calls `markCompleted()` or `markFailed()`, the update is rejected and returns `false`.
- **Pre-Publication Fencing:** Prior to moving the temporary PDF, `ReportPublicationService` asserts `verifyOwnership($job, $workerId)`.
- **Atomic Publication Under Lock:** Files are moved atomically via `rename()`, followed by transactional locking (`FOR UPDATE`) on `kundli_reports`. If lease ownership is lost before commit, the target file is unlinked.
- **Overwriting Prevention:** Proved that a stale worker cannot overwrite or delete a newer worker's published output in `test_stale_worker_cannot_overwrite_newer_workers_published_output`.

### 10.4 Signed Download Authorization Behavior
- **URL Generation Time:** Requires active Laravel Sanctum user authentication. The controller strictly verifies `$report->user_id === $user->id`. Unauthorized or unauthenticated users receive 401 or 403 and cannot obtain the signed URL.
- **Download Request Time:** Temporary signed URLs act as time-limited bearer links (20-minute TTL) per Section 5.1 of `ASTRO-M9.2-TECH-ARCH`.
- **HMAC Signature & Parameter Integrity:** The HMAC signature signs the complete route and parameters. Tampering with the report UUID (e.g. attempting to download Report B with a signed URL issued for Report A) causes signature validation to fail with HTTP 403 (`test_tampering_with_report_identifier_in_signed_url_is_rejected`).
- **Expiration:** Links accessed after 20 minutes fail signature validation with HTTP 403 (`test_expired_signed_url_is_rejected`).
- **Account Deletion:** Foreign key `ON DELETE SET NULL` anonymizes the record while keeping audit logs intact. Because `user_id = NULL`, no authenticated user can match ownership to generate new URLs.

### 10.5 Concurrency and Failure-Handling Verification
- **Concurrent Purchase Attempts:** Serialized by `DB::transaction()` and `lockForUpdate()`. Guaranteed unique debit rows and single deduction via `UNIQUE KEY (idempotency_key)`.
- **Process Spawn Failure:** If `CalculationWorkerLauncher` encounters an execution failure, the job remains in `pending` status with `lease_expires_at = null` and is not left indefinitely processing (`test_failed_worker_launch_does_not_leave_job_processing`).
- **Publication Failure Safety:** If an unreadable or zero-byte file is passed to `publishReport()`, publication is aborted and the report is never marked `completed` (`test_failure_during_publication_does_not_mark_report_completed`).

