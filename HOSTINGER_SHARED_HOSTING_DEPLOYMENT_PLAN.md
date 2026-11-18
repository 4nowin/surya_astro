# Hostinger Shared Hosting Production Deployment Plan (Phase 3A.6)

**Target Production System:** Astro Setu & Pandit Setu Shared Backend  
**Production URL:** `https://astrosetu.cubebitz.com`  
**Host Architecture:** Hostinger Shared Cloud/Business Hosting (hPanel, NOT VPS)  
**Execution Type:** Production Deployment Preparation Guide & Procedure  
**Last Updated:** November 17, 2026  

---

## 1. Hosting Architecture & Environmental Constraints

Because the production backend runs on **Hostinger Shared Hosting** (managed through hPanel) rather than a Dedicated Server or VPS with root access, all deployment operations must adhere to the following shared environment realities:

| Parameter | Shared Hosting Constraint | Mitigation / Enforcement |
|---|---|---|
| **Privileges** | No `root`, no `sudo`, no custom system daemon management. | All actions executed under SSH user `uXXXXXXX` or via hPanel Web UI. |
| **Process Supervision** | No `supervisord`, `systemd` unit files, or persistent daemon processes. | Scheduled tasks handled strictly by the Laravel Scheduler triggered by hPanel Cron. |
| **Web Server / User** | LiteSpeed / Apache with PHP-FPM running under the tenant user ID. | No `chown -R www-data:www-data`. File permissions use tenant user ownership (`uXXXXXXX:uXXXXXXX`). |
| **Cron Scheduling** | System cron daemon is accessed via hPanel Cron Jobs manager. | Single master scheduler command configured to run `php artisan schedule:run` every minute. |
| **Directory Isolation** | Web root is typically `public_html/` or `domains/astrosetu.cubebitz.com/public_html`. | Repository root placed outside or mapped cleanly with `public/` as document root. |
| **PHP CLI Binary** | System default `php` may differ from web PHP version. | Always invoke explicit PHP 8.2+ CLI path (e.g. `/usr/bin/php8.2` or `/opt/alt/php82/usr/bin/php`). |

---

## 2. Pre-Deployment Preparation & Safety Backups

Before uploading files or running any database commands, take full backups of the production database and existing deployment:

### Step 2.1 — Full MySQL Database Backup
1. **Via hPanel phpMyAdmin:**
   - Log into hPanel -> **Databases** -> **phpMyAdmin**.
   - Select the production database (e.g. `uXXXXXXX_astro_setu`).
   - Click **Export** -> **Custom** -> Check **Save output to a file** (gzipped) -> Click **Go**.
2. **Via SSH CLI:**
   ```bash
   mysqldump -u uXXXXXXX_astro -p uXXXXXXX_astro_setu | gzip > ~/backups/pre_phase3a_backup_$(date +%Y%m%d_%H%M%S).sql.gz
   ```

### Step 2.2 — Backup Production Configuration & Storage
```bash
cp /home/uXXXXXXX/domains/astrosetu.cubebitz.com/.env ~/backups/.env.production.bak
tar -czf ~/backups/storage_app_backup_$(date +%Y%m%d).tar.gz /home/uXXXXXXX/domains/astrosetu.cubebitz.com/storage/app/
```

---

## 3. Deployment Directory Structure & PHP Configuration

### 3.1 Recommended Directory Layout on Hostinger

```text
/home/uXXXXXXX/
├── backups/
├── domains/
│   └── astrosetu.cubebitz.com/
│       ├── app/
│       ├── bootstrap/
│       │   └── cache/             <-- Writable (0775)
│       ├── config/
│       ├── database/
│       │   └── migrations/
│       ├── public/                <-- Document Root in hPanel
│       │   ├── index.php
│       │   ├── .htaccess
│       │   └── build/
│       ├── resources/
│       │   └── views/
│       │       └── admin/
│       │           └── monetization/  <-- New Admin Blade Views
│       ├── routes/
│       │   ├── api.php
│       │   └── web.php
│       ├── storage/               <-- Writable (0775)
│       │   ├── app/
│       │   │   ├── private/
│       │   │   │   └── service_orders/ <-- Secure Private Attachments
│       │   │   └── public/
│       │   ├── framework/
│       │   │   ├── cache/
│       │   │   ├── sessions/
│       │   │   └── views/
│       │   └── logs/
│       ├── .env                   <-- Sensitive Config (0600)
│       ├── artisan
│       └── composer.json
```

### 3.2 PHP Configuration (hPanel -> Advanced -> PHP Configuration)
Ensure the following PHP directives are set for the domain:
- **PHP Version:** PHP 8.2 or 8.3
- **memory_limit:** `256M` (or `512M`)
- **upload_max_filesize:** `25M` (to accommodate high-resolution Kundli charts, PDF reports, and Vastu floorplans)
- **post_max_size:** `30M`
- **max_execution_time:** `120` seconds
- **Extensions enabled:** `pdo_mysql`, `bcmath`, `curl`, `mbstring`, `openssl`, `tokenizer`, `xml`, `zip`, `fileinfo`.

---

## 4. Migration Execution & Rollback Strategy

Phase 3A introduces 5 additive, safe migrations designed with rollback capabilities:

### 4.1 Migration Order & Inventory

| Sequence | Migration File | Target Table(s) | Primary Purpose |
|---|---|---|---|
| **1** | `2026_11_17_112937_add_cancellation_review_status_to_appointments_table.php` | `appointments` | Adds `cancellation_review_status` column and index for late cancellation tracking. |
| **2** | `2026_11_18_000001_add_service_monetization_fields_to_core_tables.php` | `professional_services`, `appointments`, `user_wallet_transactions` | Adds transparent pricing fields (`base_price`, `platform_markup`, `customer_price`), category, SLA days. |
| **3** | `2026_11_18_000002_create_professional_service_orders_table.php` | `professional_service_orders` | Creates core deliverable orders table with `client_nonce` unique constraint, status lifecycle, SLA tracking. |
| **4** | `2026_11_18_000003_add_client_nonce_to_appointments_table.php` | `appointments` | Adds `client_nonce` with database-level unique constraint (`appointments_client_nonce_unique`). |
| **5** | `2026_11_18_000004_enhance_service_monetization_idempotency_and_admin_audit.php` | `professional_service_orders`, `appointments`, `professional_transactions` | Adds admin adjudication audit fields (`resolved_by_admin_id`, `resolved_at`, `resolution_notes`), composite indexes. |

### 4.2 Migration Execution Command
Run via SSH in the application directory:
```bash
/usr/bin/php artisan migrate --force
```

### 4.3 Rollback Plan (If Needed)
If a rollback is required, run:
```bash
# Rollback only the Phase 3A batch
/usr/bin/php artisan migrate:rollback --step=5
```
Or restore from the pre-deployment MySQL database backup created in Step 2.1:
```bash
gunzip < ~/backups/pre_phase3a_backup_YYYYMMDD_HHMMSS.sql.gz | mysql -u uXXXXXXX_astro -p uXXXXXXX_astro_setu
```

---

## 5. File Permissions & Security Hardening

In Hostinger shared hosting, running `chown -R www-data:www-data` will cause permission lockouts. Apply tenant-safe permissions:

```bash
cd /home/uXXXXXXX/domains/astrosetu.cubebitz.com

# Standard directory and file permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Storage and cache writable by web server (runs as tenant user)
chmod -R 775 storage bootstrap/cache

# Restrict sensitive environment configuration
chmod 600 .env

# Verify private deliverable attachments directory
mkdir -p storage/app/private/service_orders
chmod -R 770 storage/app/private
```

> [!CAUTION]
> **Private Deliverable Attachments Security:**
> Never execute `php artisan storage:link` targeting `storage/app/private`. Deliverable report files and user birth charts are served strictly through `ServiceOrderAttachmentController` (`GET /api/service-orders/{id}/attachments/{filename}`) which verifies JWT/Sanctum ownership before streaming the file via `Storage::disk('private')->download()`. Direct HTTP access to attachments must return HTTP 403 / 404.

---

## 6. Hostinger Scheduler & Cron Configuration

### 6.1 hPanel Cron Job Configuration
In hPanel -> **Advanced** -> **Cron Jobs**:
- **Cron Type:** Custom
- **Command:**
  ```bash
  /usr/bin/php /home/uXXXXXXX/domains/astrosetu.cubebitz.com/artisan schedule:run >> /dev/null 2>&1
  ```
- **Interval:** Every Minute (`* * * * *`)

### 6.2 Active Scheduled Tasks in Laravel Kernel
The single system cron triggers `App\Console\Kernel`, which executes:
1. `astro:calculate-worker --max-jobs=20 --max-time=50` (Every Minute, `withoutOverlapping`)
2. `monetization:expire-appointments` (Every 5 Minutes, `withoutOverlapping`)
   - Finds pending appointments exceeding the 24-hour acceptance window (`acceptance_deadline_at <= now()`).
   - Atomically updates status to `expired`.
   - Issues an automatic full wallet refund to the customer.
   - Updates escrow transaction to `refunded`.
   - Prevents duplicate refunds idempotently.

---

## 7. Optimization & Caches

After files are deployed and migrations are applied, refresh all compiled caches:

```bash
/usr/bin/php artisan optimize:clear

# Recompile production caches
/usr/bin/php artisan config:cache
/usr/bin/php artisan route:cache
/usr/bin/php artisan view:cache
/usr/bin/php artisan event:cache
```

---

## 8. Admin Web Dashboard Verification

1. Log into the administrative dashboard at `https://astrosetu.cubebitz.com/admin/login` using a Super Admin account.
2. In the navigation sidebar, verify the new **Service Monetization** entry:
   - Route: `https://astrosetu.cubebitz.com/admin/monetization`
   - Active review badge showing pending count.
3. Verify access to all 4 tabs:
   - **Disputed Orders:** View disputed deliverable reports, customer requirements, deliverable text, dispute reason.
   - **Late Cancellations (<12h):** View cancellation timing metrics, cutoff calculations, customer reason.
   - **No-Show Reports:** View reporter identity (customer vs astrologer), report notes.
   - **Resolution History:** View completed adjudications with admin audit trail.
4. Verify non-Super Admin astrologers receive `HTTP 403 Forbidden` if attempting to access `/admin/monetization`.

---

## 9. Production Smoke-Test Checklist (Safe & Isolated)

Perform the following smoke tests using **isolated internal test accounts** (e.g. `test_user@astrosetu.com` and `test_astrologer@astrosetu.com`):

- [ ] **1. Public Services Discovery:**
  - `GET /api/en/services/categories` returns all 6 categories (Janam Kundli, Consultation, Vastu, Muhurat, Puja, Specialized).
  - `GET /api/en/astrologers/{id}/services` returns active services with transparent 20% platform markup breakdown.
- [ ] **2. Idempotent Deliverable Checkout:**
  - User submits `POST /api/services/{id}/order-deliverable` with `client_nonce: "smoke-test-nonce-01"`.
  - Customer wallet is debited exactly once (base price + 20% markup).
  - Immediate replay with same `client_nonce` returns `HTTP 200 OK` (`idempotent: true`) with ZERO duplicate debit.
- [ ] **3. Astrologer Order Fulfilment (Pandit Setu):**
  - Astrologer logs into Pandit Setu and views order in Orders list.
  - Astrologer starts progress (`in_progress`) and submits deliverable report text.
  - Escrow remains securely locked (`pending`) awaiting client confirmation.
- [ ] **4. Client Confirmation & Escrow Release (Astro Setu):**
  - Customer confirms delivery via `POST /api/user/service-orders/{id}/confirm-completed`.
  - Order moves to `completed`.
  - Escrow transaction moves to `completed` and astrologer receives 100% of their base price.
- [ ] **5. Idempotent Appointment Booking:**
  - User submits `POST /api/services/{id}/book-appointment`.
  - Wallet debited; appointment created in `pending_confirmation` with `acceptance_deadline_at = now() + 24 hours`.
- [ ] **6. Automatic 24-Hour Expiry Command Verification:**
  - Execute `php artisan monetization:expire-appointments`.
  - Confirm execution outputs scan summary and processes overdue appointments.
- [ ] **7. Admin Adjudication Form:**
  - Super Admin opens `/admin/monetization/orders/{id}` or `/admin/monetization/appointments/{id}`.
  - Verify financial breakdown, evidence, and settlement buttons (Refund vs Payout).

---

## 10. Rollback & Emergency Escalation Protocol

If any critical issue arises during deployment:

1. **Activate Maintenance Mode:**
   ```bash
   /usr/bin/php artisan down --secret="astro-setu-bypass-2026"
   ```
2. **Revert Git Deployment / Code:**
   ```bash
   git checkout <PREVIOUS_COMMIT_TAG>
   /usr/bin/php artisan optimize:clear
   /usr/bin/php artisan config:cache
   /usr/bin/php artisan route:cache
   ```
3. **Database Restore (if schema corruption occurred):**
   ```bash
   gunzip < ~/backups/pre_phase3a_backup_YYYYMMDD_HHMMSS.sql.gz | mysql -u uXXXXXXX_astro -p uXXXXXXX_astro_setu
   ```
4. **Deactivate Maintenance Mode:**
   ```bash
   /usr/bin/php artisan up
   ```
