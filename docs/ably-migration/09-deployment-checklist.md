# 09 – Production Deployment & Cutover Checklist

## Overview
This checklist provides step-by-step instructions for deploying the Ably Realtime and MySQL chat system to production (Hostinger shared hosting environment for Laravel, Google Play / App Store for Astro Setu & Pandit Setu).

---

## 1. Environment & Configuration Prerequisites

### 1.1 Laravel Production Server (.env)
Set the following keys in your production `.env` file on Hostinger:

```dotenv
# Ably Configuration
ABLY_KEY=your_production_ably_api_key_here

# Confirm Queue Driver (Shared hosting uses sync)
QUEUE_CONNECTION=sync

# Existing Firebase Credentials (retained for FCM HTTP v1)
FIREBASE_CREDENTIALS_PATH=firebase/astro-setu-firebase-adminsdk.json
```

> **Security Note:** Verify that `ABLY_KEY` is never committed to Git and that `config/services.php` only reads it through `env('ABLY_KEY')`.

---

## 2. Database Migration Steps

Run the following commands on the production server (via SSH or cPanel Terminal):

```bash
# 1. Back up database before migrating
# (Recommended: export full MySQL dump via phpMyAdmin or mysqldump)

# 2. Run new migrations
php artisan migrate --force

# 3. Clear configuration and route caches
php artisan config:cache
php artisan route:cache
```

The migrations executed:
1. `2026_11_12_000001_add_client_nonce_to_chats_table.php` (adds `client_nonce`, unique index `(chat_session_id, client_nonce)`, and composite index `(chat_session_id, id)`).
2. `2026_11_12_000002_create_chat_broadcast_outbox_table.php` (creates outbox for failed Ably publishes).

---

## 3. Hostinger Scheduled Cron Job Setup

Since Hostinger shared hosting does not run long-lived background queue daemons (`php artisan queue:work`), the application utilizes `sync` queue mode with a lightweight cron job to retry any transiently failed Ably broadcasts.

In Hostinger cPanel / hPanel -> **Cron Jobs**:
- **Schedule:** Every 5 minutes (`*/5 * * * *`)
- **Command:**
  ```bash
  /usr/bin/php /home/u123456789/public_html/artisan chat:retry-broadcasts >> /dev/null 2>&1
  ```
  *(Replace `/home/u123456789/public_html/` with your actual server root).*

---

## 4. Mobile Apps Release & Cutover Sequence

Both apps must transition to Ably to communicate with each other.

### Step 1: Deploy Laravel Backend First
- Deploy the updated Laravel codebase and run migrations.
- The new API endpoints (`/chat/ably-token`, `/chat/message/send`, `/chat/sessions/{sessionId}/messages`) will be active.
- Existing sessions remain valid in MySQL.

### Step 2: Build & Release Astro Setu (Flutter User App)
- Build release APK / App Bundle:
  ```bash
  flutter build appbundle --release
  ```
- Upload to Google Play Console / Apple TestFlight.

### Step 3: Build & Release Pandit Setu (Flutter Astrologer App)
- Build release APK / App Bundle:
  ```bash
  flutter build appbundle --release
  ```
- Upload to internal track or distribution channel.

---

## 5. Post-Deployment Verification Checklist

1. **Ably Authentication:**
   - Log in as user in Astro Setu -> open active chat.
   - Verify `POST /api/{lang}/chat/ably-token` returns `200 OK` with valid signed `TokenRequest`.
2. **Real-time Messaging:**
   - Send message from Astro Setu -> verify it appears in Pandit Setu in < 500ms.
   - Send reply from Pandit Setu -> verify it appears in Astro Setu in < 500ms.
3. **MySQL Persistence:**
   - Query `chats` table in MySQL -> confirm both messages are present with `chat_session_id`, `client_nonce`, and timestamps.
4. **FCM Push Notifications:**
   - Background Pandit Setu -> send message from Astro Setu -> confirm push notification arrives on astrologer device.
   - Background Astro Setu -> send reply from Pandit Setu -> confirm push notification arrives on user device.
   - Open chat screen -> send message -> confirm foreground notification is suppressed on the open device.
5. **Reconnection & Catch-Up:**
   - Turn on airplane mode on one device -> send 2 messages from the other -> disable airplane mode -> verify missed messages appear automatically.
6. **Failed Publish Outbox:**
   - Check `chat_broadcast_outbox` table in MySQL -> ensure `status = 'delivered'` and retry command executes cleanly.

---

## 6. Rollback Plan

If unexpected issues occur during deployment:

1. **Backend Rollback:**
   - Revert Git commit on server.
   - (Optional) Roll back migrations:
     ```bash
     php artisan migrate:rollback --step=2
     ```
   - Clear caches: `php artisan config:cache && php artisan route:cache`.
2. **Client Rollback:**
   - Roll back to previous app release in Google Play Console / TestFlight.
