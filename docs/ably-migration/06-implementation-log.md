# 06 – Implementation Log: Ably Realtime & MySQL Migration

## Executive Summary
This document provides a comprehensive chronological record of all architectural and codebase changes implemented during the migration from Firebase Firestore to Ably Realtime + Laravel/MySQL across the three connected projects:
1. **Laravel Backend:** `C:\wamp64\www\astro_setu`
2. **Astro Setu (User Flutter App):** `C:\Users\rsrav\StudioProjects\astro_setu`
3. **Pandit Setu (Astrologer Flutter App):** `C:\Users\rsrav\StudioProjects\astrologer_chat_app`

---

## 1. Phase A: Laravel Backend Implementation

### 1.1 Dependency Installation & Environment Configuration
- Installed official Ably PHP SDK: `ably/ably-php:^1.1.12` and its required parser `rybakit/msgpack:v0.11.3` via Composer.
- Verified PHP 8.4 and Laravel 11 compatibility.
- Added `ABLY_KEY=` configuration to `.env.example`, local `.env`, `config/services.php`, and verified `config/broadcasting.php`.
- The Ably API secret is strictly confined to the Laravel server backend and is never sent to or embedded in mobile clients.

### 1.2 Database Schema & Migrations
- **Migration 1:** `database/migrations/2026_11_12_000001_add_client_nonce_to_chats_table.php`
  - Added `client_nonce` (`VARCHAR(64) NULL`) to `chats` table.
  - Added composite unique index: `unique(['chat_session_id', 'client_nonce'])` to guarantee strict message idempotency against duplicate submissions.
  - Added composite index: `index(['chat_session_id', 'id'])` for sub-millisecond cursor pagination and catch-up queries.
- **Migration 2:** `database/migrations/2026_11_12_000002_create_chat_broadcast_outbox_table.php`
  - Created `chat_broadcast_outbox` table with columns: `id`, `chat_id`, `chat_session_id`, `channel`, `event`, `payload` (JSON), `attempts`, `last_error`, `status` (`pending`, `failed`, `delivered`), `created_at`, `updated_at`.
- Updated `app/Models/Chat.php` `$fillable` array to include `client_nonce`.
- Created `app/Models/ChatBroadcastOutbox.php` Eloquent model.

### 1.3 Core Services Implemented
- **`app/Services/Chat/AblyAuthService.php`**:
  - Validates authenticated user or astrologer admin ownership of requested `chat_session_id`.
  - Ensures session is `active` and not terminated (`ended_at IS NULL`).
  - Generates signed, short-lived Ably `TokenRequest` with:
    - Channel scope: `chat:session:{chat_session_id}`
    - Capability: `["subscribe"]` strictly (no client publish capabilities).
    - TTL: 3600 seconds (1 hour).
- **`app/Services/Chat/ChatBroadcaster.php`**:
  - Server-side REST client publishing.
  - Formats canonical message payload (`id`, `chat_session_id`, `client_nonce`, `message`, `sender_id`, `sender_type`, `user_id`, `astrologer_id`, `sent_at`, etc.).
  - Publishes `message.created` to `chat:session:{chat_session_id}` with a 2.5-second timeout guard.
  - If publish fails or times out, records payload into `chat_broadcast_outbox` automatically without rolling back MySQL message persistence.
- **`app/Services/Chat/ChatNotificationService.php`**:
  - Formats and dispatches FCM HTTP v1 notifications using existing `FirebaseNotificationService`.
  - Sends to astrologer admin (`admins.fcm_token`) when user messages, and to user devices (`user_devices` + `users.fcm_token`) when astrologer messages.
  - Attaches standard routing payload: `type: 'chat_message'`, `session_id`, `user_id`, `astrologer_id`, `title`, `body`.
- **`app/Console/Commands/RetryFailedChatBroadcastsCommand.php`**:
  - Artisan command: `php artisan chat:retry-broadcasts`.
  - Retries pending outbox messages up to 5 attempts with exponential backoff and marks delivered.
- **`app/Http/Controllers/Api/ChatApiController.php`**:
  - Unified controller serving both User and Astrologer roles:
    - `createAblyToken(Request $request)`
    - `sendMessage(Request $request)`
    - `getMessages(Request $request, $sessionId)`
    - `endSession(Request $request)`

### 1.4 Routing Updates
- Registered routes in `routes/api.php` under both user `/{lang?}` group and astrologer `/astrologer` group:
  - `POST /chat/ably-token`
  - `POST /chat/message/send`
  - `GET /chat/sessions/{sessionId}/messages`
  - `POST /chat/session/end`
- Updated `ChatController@endChatSession` to set `ended_at = now()`.

---

## 2. Phase B: Astro Setu (User App) Implementation

### 2.1 Dependencies
- Added `ably_flutter: ^1.2.44` and `uuid: ^4.6.0` to `pubspec.yaml`.

### 2.2 Models & API Routes
- Updated `lib/data/api/api_routes.dart` with `ablyToken`, `sendMessage`, `endSession`, and `sessionMessages(sessionId)`.
- Refactored `lib/models/chat_message.dart`:
  - Removed Firestore `Timestamp` dependency.
  - Added `id`, `chatSessionId`, `clientNonce`, `sentAt`, `status` (`sending`, `sent`, `failed`).
  - Added `copyWith`, `toJson`, and backward-compatible `fromJson`.

### 2.3 Services & Controllers
- Created `lib/services/ably_chat_service.dart`:
  - Implements `authCallback` requesting short-lived token from Laravel `POST /chat/ably-token`.
  - Subscribes exclusively to `chat:session:{id}` for `message.created` events.
  - Handles reconnection state transitions, emitting `onReconnected` for missed message catch-up.
  - Provides clean channel detach and realtime client disposal.
- Updated `lib/services/chat_service.dart`:
  - Added `sendMessage` and `fetchMessages` with pagination/catchup parameters (`before_id`, `after_id`, `limit`).
- Refactored `lib/controllers/chat_controller.dart`:
  - Removed all Firestore imports, collection queries, and snapshot subscriptions.
  - Implemented initial load from Laravel API.
  - Connected Ably channel subscription.
  - Implemented optimistic message submission with UUID `clientNonce`.
  - Implemented de-duplication by `clientNonce` and server `id`.
  - Implemented scroll pagination with `before_id` for older messages.
  - Implemented reconnection catch-up using `after_id`.
  - Maintained `static String? activeSessionId` for foreground notification suppression.
- Updated `lib/views/screens/chat/chat_screen.dart`:
  - Added scroll listener for loading older messages.
- Updated `lib/services/firebase_messaging_service.dart`:
  - Added `chat_message` deep linking to `ChatScreen`.
  - Suppressed foreground notification banner when the active session matches incoming notification payload.

---

## 3. Phase C: Pandit Setu (Astrologer App) Implementation

### 3.1 Dependencies
- Added `ably_flutter: ^1.2.44` and `uuid: ^4.6.0` to `pubspec.yaml`.

### 3.2 Models & API Routes
- Updated `lib/app/data/api/api_routes.dart` with `ablyToken`, `sendMessage`, `endSession`, and `sessionMessages(sessionId)`.
- Refactored `lib/app/data/models/chat_message.dart`:
  - Removed Firestore `Timestamp` dependency.
  - Added `id`, `chatSessionId`, `clientNonce`, `senderType`, `status`, `copyWith`, `toJson`, `fromJson`.
  - Preserved `isFromAstrologer` and `isFromUser` helpers for existing UI layout.

### 3.3 Services & Controllers
- Created `lib/app/data/services/ably_chat_service.dart`:
  - Configured Ably `authCallback` posting to `astrologer/chat/ably-token`.
  - Subscribes to `chat:session:{id}` on `message.created`.
  - Monitors connection state and triggers catch-up on reconnect.
- Updated `lib/app/data/services/chat_service.dart`:
  - Added `sendMessage`, `fetchMessages`, and `endSession`.
- Refactored `lib/app/controllers/chat_controller.dart`:
  - Removed all Firestore snapshot listeners, document writes, and queries.
  - Implemented initial fetch from Laravel, Ably subscription, optimistic sending with UUID nonces, and catch-up on reconnect.
  - Linked scroll controller to fetch older messages via `before_id`.
  - Registered `activeSessionId` for active chat suppression.
- Updated `lib/app/views/chat/chat_screen.dart`:
  - Cleanly resolved `isMe` using `message.isFromAstrologer`.
  - Preserved 100% of UI styling, colors, gradients, and layout.
- Refactored `lib/app/controllers/dashboard_controller.dart`:
  - Replaced Firestore query on collection `'chats'` with `CallApi().getData(ApiRoutes.astrologerChatSessions)`.
  - Added pull-to-refresh via `RefreshIndicator` in `lib/app/views/chat/chat_sessions_screen.dart`.
- Updated `lib/app/data/services/flutter_notification.dart`:
  - Suppressed foreground local notifications when `ChatController.activeSessionId` matches the notification's `session_id`.
  - Removed redundant Firestore token write.

---

## 4. Phase D: Firestore Deprecation & Cleanup

### 4.1 Laravel
- Removed obsolete Firestore document creation and checking logic from `ChatController@startSession`.
- Removed `Kreait\Firebase\Factory` import from `ChatController.php`.
- Zero Firestore read/write operations remain in the Laravel backend.

### 4.2 Astro Setu
- Deleted unused `lib/services/chat_firebase_service.dart`.
- Removed `cloud_firestore` and unused `firebase_auth` from `pubspec.yaml` via `flutter pub remove`.
- `firebase_core` and `firebase_messaging` retained for FCM push notifications.

### 4.3 Pandit Setu
- Removed Firestore message listener and writer from `chat_controller.dart` and `chat_service.dart`.
- Replaced Firestore session listener in `dashboard_controller.dart` with Laravel API call.
- Removed Firestore FCM token sync in `flutter_notification.dart`.
