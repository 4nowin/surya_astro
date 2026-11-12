# Step-by-Step Migration Plan: Firebase Firestore to Ably

**Document Version:** 1.0  
**Phase:** Planning & Specification (AUDIT-ONLY, NO CHANGES IMPLEMENTED)

---

## 1. Migration Overview & Guiding Constraints

This migration executes a phased transition of chat messaging from Firestore to Ably Realtime while ensuring:
1. **Zero downtime** and no data loss.
2. **Strict separation of concerns:** UI redesign of Pandit Setu is deferred; backend contracts will be created so Pandit Setu can adopt the exact same API cleanly.
3. **No assumptions of background queue workers:** Designed to function reliably within Hostinger shared hosting constraints (`QUEUE_CONNECTION=sync`).
4. **Preservation of Firebase Auth & FCM:** No disruptions to user login or push notification delivery.

---

## 2. Phase-by-Phase Execution Roadmap

```
+-----------------------------------------------------------------------------------+
| PHASE 1: BACKEND IMPLEMENTATION (Laravel + Ably SDK)                              |
|   1. Install ably/ably-php package                                                |
|   2. Add ABLY_KEY to config/broadcasting.php & .env                               |
|   3. Create AblyAuthService for secure TokenRequest minting                       |
|   4. Implement ChatApiController (token, send, history)                           |
|   5. Wire routes in routes/api.php with Sanctum auth                              |
|   6. Write comprehensive feature tests for the new endpoints                      |
+-----------------------------------------------------------------------------------+
                                          |
                                          v
+-----------------------------------------------------------------------------------+
| PHASE 2: ASTRO SETU FLUTTER IMPLEMENTATION                                        |
|   1. Add ably_flutter package to pubspec.yaml                                     |
|   2. Create AblyService (authCallback with TokenRequest, channel subscription)    |
|   3. Refactor ChatController to use Ably stream + Laravel API                     |
|   4. Update ChatScreen UI to handle paginated history scrolling                   |
|   5. Fix notification routing for chat_message in FirebaseMessagingService        |
|   6. Verify end-to-end user chat experience                                       |
+-----------------------------------------------------------------------------------+
                                          |
                                          v
+-----------------------------------------------------------------------------------+
| PHASE 3: PANDIT SETU (ASTROLOGER APP) ALIGNMENT                                   |
|   1. Register Astrologer-specific chat routes in Laravel (if not shared)          |
|   2. Integrate Ably subscriber into astrologer client                             |
|   3. Route astrologer message sending through Laravel message API                 |
+-----------------------------------------------------------------------------------+
                                          |
                                          v
+-----------------------------------------------------------------------------------+
| PHASE 4: FIRESTORE DEPRECATION & CLEANUP                                          |
|   1. Remove Firestore doc creation from ChatController@startSession               |
|   2. Remove cloud_firestore and unused firebase_auth from Flutter pubspec         |
|   3. Remove google/cloud-firestore from Laravel composer.json                     |
|   4. Archive legacy FirebaseChatController and unused firebaserules               |
+-----------------------------------------------------------------------------------+
```

---

## 3. Required Laravel Backend Changes

### 3.1 Dependencies & Configuration
1. **Install Ably PHP SDK:**
   ```bash
   composer require ably/ably-php:^1.1
   ```
2. **Environment & Broadcasting Config:**
   - Add to `.env`:
     ```env
     ABLY_KEY=your-ably-api-key:your-ably-secret
     ```
   - Update [`config/broadcasting.php`](file:///C:/wamp64/www/astro_setu/config/broadcasting.php):
     ```php
     'ably' => [
         'driver' => 'ably',
         'key' => env('ABLY_KEY'),
     ],
     ```

### 3.2 Database Schema Adjustment
The `chats` table already has the required columns: `chat_session_id`, `user_id`, `admin_id`, `astrologer_id`, `message`, `sender`, and `sent_at`.
To guarantee delivery idempotency and fast reverse-chronological pagination:
- **New Migration:** Add `client_nonce` column and database indexes:
  ```php
  Schema::table('chats', function (Blueprint $table) {
      $table->string('client_nonce', 64)->nullable()->after('sender')->index();
      $table->index(['chat_session_id', 'id']);
  });
  ```

### 3.3 New Backend Services & Controllers
1. **`App\Services\Chat\AblyAuthService`:**
   - Accepts authenticated user and `chat_session_id`.
   - Validates user participation in the session.
   - Generates signed `Ably\Models\TokenRequest` with:
     - Capability: `['chat:session:' . $sessionId => ['subscribe']]`
     - TTL: `3600` (1 hour)
     - ClientId: `'user_' . $userId` (or `'astrologer_' . $astrologerId`)
2. **`App\Services\Chat\ChatBroadcaster`:**
   - Wraps `Ably\AblyRest` to publish `message.created` to channel `chat:session:{id}`.
   - Configured with a 3-second HTTP timeout to prevent blocking on slow external networks.
3. **`App\Http\Controllers\Api\ChatApiController`:**
   - `createAblyToken(Request $request)`:
     - Validates session access -> calls `AblyAuthService::createTokenRequest()`.
   - `sendMessage(Request $request)`:
     - Validates session is active and user is participant.
     - Performs idempotency check using `client_nonce`.
     - Saves message to MySQL `chats` table.
     - Publishes to Ably channel `chat:session:{id}`.
     - Triggers FCM notification to recipient via `FirebaseNotificationService`.
     - Returns HTTP 201 with stored message.
   - `getMessages(Request $request, $sessionId)`:
     - Validates session ownership.
     - Queries `Chat::where('chat_session_id', $sessionId)`.
     - Supports cursor pagination: `before_id` (scrolling up for older messages) and `after_id` (offline catch-up).
     - Limits results to 25 items per request.
4. **Fix Existing Session Controller:**
   - In [`ChatController@endChatSession`](file:///C:/wamp64/www/astro_setu/app/Http/Controllers/Api/ChatController.php#L190):
     Uncomment `$session->update(['ended_at' => now()]);`.

### 3.4 API Route Definitions (`routes/api.php`)
```php
Route::middleware(['auth:sanctum'])->prefix('{lang?}')->group(function () {
    Route::post('/chat/ably-token', [ChatApiController::class, 'createAblyToken']);
    Route::post('/chat/message/send', [ChatApiController::class, 'sendMessage']);
    Route::get('/chat/sessions/{sessionId}/messages', [ChatApiController::class, 'getMessages']);
});
```

---

## 4. Required Astro Setu Flutter Changes

### 4.1 Dependency Updates (`pubspec.yaml`)
- **Add:**
  ```yaml
  ably_flutter: ^1.2.33
  uuid: ^4.5.1
  ```
- **Remove (in Phase 4):**
  ```yaml
  cloud_firestore: ^5.6.8  # Removed
  firebase_auth: ^5.5.4    # Removed (dead dependency)
  ```

### 4.2 New Ably Service (`lib/services/ably_chat_service.dart`)
- Implements `authCallback` pattern:
  - When Ably SDK requests a token, calls Laravel API `POST /api/{lang}/chat/ably-token` with the active session ID.
  - Ably SDK automatically refreshes tokens prior to expiration.
- Manages channel lifecycle:
  - Connects to channel `chat:session:{chatSessionId}`.
  - Subscribes to event `message.created`.
  - Exposes an event stream for `ChatController`.
  - Cleans up and detaches channel on session exit.

### 4.3 Refactor `ChatController` (`lib/controllers/chat_controller.dart`)
1. **Remove Firestore Imports & Streams:**
   - Delete `_firebaseService.sendMessage(...)`.
   - Delete `FirebaseFirestore.instance.collection('chats')...` stream subscription.
2. **Implement Dual-Phase Message Loading:**
   - Initial load: calls `GET /api/{lang}/chat/sessions/{id}/messages` to populate the existing message list.
   - Real-time updates: listens to `AblyChatService.onMessageReceived` stream and appends incoming messages.
3. **Refactor `sendMessage(text)`:**
   - Generates client-side UUID (`client_nonce`).
   - Optimistically appends the pending message to the UI list with a "sending" state.
   - Calls `CallApi().postAuthData(...)` to `chat/message/send`.
   - On HTTP 200/201, marks message as "sent" with the server-assigned ID.
   - On HTTP failure, displays retry state without dropping the message.

### 4.4 Refactor `ChatMessage` Model (`lib/models/chat_message.dart`)
- Remove `import 'package:cloud_firestore/cloud_firestore.dart';`.
- Change `timestamp` from Firestore `Timestamp` to standard Dart `DateTime`.
- Parse ISO-8601 strings from Laravel API.

### 4.5 Fix Push Notification Tap Routing
- In [`lib/services/firebase_messaging_service.dart`](file:///C:/Users/rsrav/StudioProjects/astro_setu/lib/services/firebase_messaging_service.dart#L254-L268):
  Add handling for `chat_message`:
  ```dart
  if (rawType == 'chat_message' || rawType == 'chat') {
    final sessionId = int.tryParse(data['session_id']?.toString() ?? '');
    final adminId = int.tryParse(data['admin_id']?.toString() ?? '');
    final astrologerId = int.tryParse(data['astrologer_id']?.toString() ?? '');
    final astrologerName = data['user_name']?.toString() ?? 'Astrologer';
    if (adminId != null && astrologerId != null) {
      Get.to(() => ChatScreen(
        adminId: adminId,
        astrologerId: astrologerId,
        astrologerName: astrologerName,
      ));
    }
  }
  ```

---

## 5. Security & Authorization Considerations

1. **Token Capability Narrowing:**
   - Tokens issued by Laravel must carry `subscribe` permission ONLY.
   - Mobile clients must never have `publish`, `presence`, or wildcard `*` permissions.
2. **Session Ownership Enforcement:**
   - Every request to `/chat/ably-token`, `/chat/message/send`, and `/chat/sessions/{id}/messages` must enforce:
     ```php
     if ($session->user_id !== $request->user()->id && $session->astrologer->admin_id !== $request->user()->id) {
         abort(403, 'Unauthorized access to chat session.');
     }
     ```
3. **No Secrets on Client:**
   - The Ably API Key format (`appId.keyId:secret`) must only reside in Laravel's `.env`.
4. **Input Sanitization & Length Limits:**
   - Text messages must be validated: `required|string|max:2000`.
   - HTML tags stripped or escaped to prevent stored XSS attacks.

---

## 6. Testing Strategy

### 6.1 Laravel Automated Tests
- **Unit Tests:**
  - `AblyAuthServiceTest`: Verify that tokens generated for Session A cannot subscribe to Session B.
  - `ChatBroadcasterTest`: Mock Ably REST client and verify payload formatting.
- **Feature Tests:**
  - `ChatMessagingApiTest`:
    - Test unauthorized access rejection (HTTP 403).
    - Test sending a message stores to MySQL and increments counts.
    - Test idempotency (sending identical `client_nonce` returns original message without duplicate insert).
    - Test paginated history with `before_id` and `after_id`.

### 6.2 Flutter Integration Tests
- **UI State Verification:**
  - Verify message bubbles render correctly with local `DateTime` formatting.
  - Verify that sending a message displays immediately in the list.
- **Network Resilience:**
  - Simulate airplane mode during chat: verify reconnect and history sync without duplicates.
- **Notification Tap Navigation:**
  - Verify that tapping a mock chat notification payload routes directly to `ChatScreen`.

---

## 7. Rollback Strategy

1. **Database Safety:**
   - The migration only adds nullable columns (`client_nonce`) and indexes to the existing `chats` table. No destructive table modifications occur.
2. **Feature Flagging:**
   - A configuration flag `CHAT_DRIVER=ably` (with fallback `CHAT_DRIVER=firestore`) can be added in Laravel config.
   - If Ably experiences an upstream outage, the system can instantly fall back by flipping the environment variable.
3. **Parallel Availability:**
   - Keep Firestore security rules intact until Flutter app v1.1.0 with Ably is fully adopted and verified in production.
