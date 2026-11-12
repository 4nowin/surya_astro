# Risks, Constraints & Engineering Recommendations

**Document Version:** 1.0  
**Phase:** Audit & Risk Assessment (AUDIT-ONLY)

---

## 1. Shared Hosting Constraints (Hostinger Linux)

### 1.1 The Absence of Persistent Queue Workers
- **Reality:**
  - Hostinger shared hosting environments provide standard Apache/LiteSpeed web servers with PHP-FPM / FastCGI.
  - **No Supervisor or Systemd daemons** can be configured by standard cPanel/hPanel users to run `php artisan queue:work` continuously.
  - The application's `.env` explicitly configures `QUEUE_CONNECTION=sync`.
- **Risk:**
  - If backend code queues the Ably broadcast or FCM notification on an asynchronous queue, **the job will never run** unless a worker is actively polling.
  - If backend code dispatches external HTTP requests synchronously to Ably and FCM during the user's `sendMessage` HTTP request, network latency or timeouts from external APIs could delay the HTTP response to Flutter.
- **Engineering Recommendation:**
  1. Keep queue connection as `sync` for chat operations.
  2. Implement strict, short network timeouts on external HTTP calls:
     - Ably REST publish: **max 2.5 seconds timeout**.
     - FCM HTTP v1 dispatch: **max 2.5 seconds timeout**.
  3. Execute the MySQL database transaction **first**, and perform external broadcasts **in a `try-catch` block after the database commit**:
     ```php
     DB::transaction(function () use ($chatData, &$chat) {
         $chat = Chat::create($chatData);
     });

     // Broadcasts occur outside transaction so DB lock is immediately released
     try {
         $broadcaster->publish($chat);
     } catch (\Throwable $e) {
         Log::warning('Ably broadcast failed, relying on catch-up: ' . $e->getMessage());
     }
     ```
  4. If Ably publishing fails, the message is **never lost** because it is already committed to MySQL. The client will retrieve it via the fallback catch-up query when syncing history.

---

## 2. Ably Free-Tier Limits & Capacity Analysis

### 2.1 Free Tier Parameters
- **Concurrent Connections:** 200 peak concurrent connections.
- **Monthly Messages:** 6,000,000 messages / month.
- **Concurrent Channels:** Up to 256 active channels simultaneously.
- **Message Size Limit:** 64 KB per message (chat text messages are < 2 KB).

### 2.2 Operational Cost & Capacity Evaluation
- **Current App Usage Pattern:**
  - Chat consultations in Astro Setu are 1-on-1 sessions between 1 user and 1 astrologer.
  - During a live session:
    - 2 WebSocket connections (1 user + 1 astrologer).
    - 1 active channel (`chat:session:{id}`).
  - With a 200-connection limit, the Ably free tier comfortably supports **up to 100 simultaneous active 1-on-1 consultations** at any single second.
  - 6,000,000 messages/month translates to ~200,000 messages/day. In a typical 10-minute consultation of 30 messages, the free tier accommodates **200,000 completed consultations per month**.

### 2.3 Optimization Rules to Prevent Tier Exhaustion
1. **No Idle Connections:**
   - Clients must **only connect to Ably when entering `ChatScreen`**.
   - When the user navigates away or the chat terminates, the app must immediately disconnect the Ably client and release the channel.
   - Do **NOT** keep a standing Ably connection active on the home screen or in background services.
2. **Strictly Disable Presence & Typing:**
   - Presence events (enter, leave, update) generate billable Ably messages.
   - Typing indicators generate high-frequency billable messages.
   - By eliminating presence and typing, message volume is reduced by **over 65%**.

---

## 3. Reliability, Idempotency & Message Loss Prevention

### 3.1 Preventing Duplicate Messages (Network Retries)
- **Problem:**
  - A user on mobile cellular network taps send; Laravel receives and persists the message, but the cellular tower drops before the HTTP 201 response reaches Flutter.
  - Flutter auto-retries or the user taps send again, resulting in duplicate database rows.
- **Solution:**
  - Astro Setu generates a unique `client_nonce` (UUID v4) for each message bubble before sending.
  - Laravel checks `Chat::where('chat_session_id', $sessionId)->where('client_nonce', $nonce)->first()`.
  - If found, Laravel skips insertion and returns the existing message record with HTTP 200.

### 3.2 Offline Catch-Up & Reconnection Synchronization
- **Problem:**
  - An astrologer sends a message while the user's phone temporarily passes through a tunnel or disconnects from Wi-Fi.
  - Ably WebSocket reconnects once network is restored, but the user may have missed the real-time broadcast.
- **Solution:**
  - The client tracks `latestReceivedMessageId` in memory.
  - Upon network reconnection (`connectivity_plus` or Ably `ConnectionState.connected`), Flutter fires a background catch-up query:
    `GET /api/v1/chat/sessions/{id}/messages?after_id={latestReceivedMessageId}`
  - Any messages missed during the disconnected interval are seamlessly merged into the list.

---

## 4. Push Notification Optimization & Deduplication

### 4.1 Avoiding Redundant In-App FCM Notifications
- **Problem:**
  - When both user and astrologer are actively looking at the `ChatScreen`, delivering an FCM system-tray push notification creates an irritating sound and vibration while they are already reading the live message.
- **Solution:**
  - The Flutter application already manages foreground notifications via `_onForegroundMessage` in `FirebaseMessagingService`.
  - Add an in-memory session check: if `Get.currentRoute == '/ChatScreen'` and `message.data['session_id'] == activeChatSessionId`, **suppress the local notification popup**.
  - Background and terminated states will continue to display system tray notifications as expected.

---

## 5. Security & Authorization Audit Summary

### 5.1 Crucial Security Enhancements Required
1. **Secure the Unprotected Notification Endpoint:**
   - Currently, `POST /send-to-astrologer` is completely public in `routes/api.php` without authentication middleware.
   - In the target architecture, this endpoint is **retired entirely**; push notification triggering is moved inside the authenticated `ChatApiController@sendMessage` pipeline.
2. **Enforce Chat Session Ownership:**
   - Both user and astrologer must be verified against `chat_sessions.user_id` and `chat_sessions.astrologer_id` on all endpoints.
3. **Validate Active Billing Status:**
   - `sendMessage` should verify that `chat_sessions.ended_at` is NULL before allowing new messages to be stored or broadcast.

---

## 6. Implementation Readiness Checklist

| Item | Status | Action Required |
|---|---|---|
| Laravel Database Schema | Ready | Add `client_nonce` and composite index to `chats` table |
| Laravel FCM Service | Ready | Reuse existing `FirebaseNotificationService` (FCM HTTP v1) |
| Ably Account & API Key | Pending User Action | Provision Ably account and provide API key in `.env` |
| Flutter Ably Package | Pending Approval | Add `ably_flutter: ^1.2.33` to `pubspec.yaml` |
| Flutter Routing Fix | Ready for Implementation | Add `chat_message` case in `FirebaseMessagingService` |
| Pandit Setu Compatibility | Preserved | Architecture specifies matching REST API contracts |
