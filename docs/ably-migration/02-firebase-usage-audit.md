# Firebase Usage Audit: Retention & Deprecation Blueprint

**Audit Date:** November 2026  
**Projects Analyzed:**
- **Astro Setu Flutter Application:** `C:\Users\rsrav\StudioProjects\astro_setu`
- **Astro Setu Laravel Backend:** `C:\wamp64\www\astro_setu`

---

## 1. Complete Catalog of Firebase Services Currently Used

| Firebase Service | Astro Setu (Flutter) | Laravel Backend | Pandit Setu (Astrologer App) | Primary Function in App |
|---|---|---|---|---|
| **Firebase Core** | `firebase_core: ^3.13.1`<br>`lib/firebase_options.dart` | Config `services.firebase` | `firebase_core` | Initializes Firebase client SDKs on Android and iOS |
| **Firebase Auth** | `firebase_auth: ^5.5.4`<br>*(Declared in pubspec, 0 usages in Dart)* | `Kreait\Firebase\Auth`<br>Used in `AdminAuthController` | Used for `signInWithCustomToken` | Signs in astrologers to satisfy Firestore security rules |
| **Firebase Cloud Messaging (FCM)** | `firebase_messaging: ^15.2.6`<br>`lib/services/firebase_messaging_service.dart` | `FirebaseNotificationService`<br>FCM HTTP v1 API (`google/apiclient`) | `firebase_messaging` | App push notifications: daily horoscope, pooja, and chat arrival |
| **Cloud Firestore** | `cloud_firestore: ^5.6.8`<br>`ChatController`, `ChatFirebaseService` | `kreait/firebase-php`<br>`google/cloud-firestore`<br>`ChatController@startSession` | `cloud_firestore`<br>`ChatController`, `ChatService` | Real-time chat transport and message storage |
| **Firebase Cloud Functions** | *Not Used* | *Not Used* | *Not Used* | None |
| **Firebase Storage** | Configured in `firebase_options.dart` | *Not Used* | *Not Used* | Not actively used for chat files (chat is text-only) |

---

## 2. Detailed Service-by-Service Audit

### 2.1 Firebase Core
- **Current Role:**
  - Essential foundation for initializing Firebase SDKs in Flutter ([`lib/main.dart`](file:///C:/Users/rsrav/StudioProjects/astro_setu/lib/main.dart#L35)):
    ```dart
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
    ```
- **Audit Finding:**
  - Required by `firebase_messaging` (FCM). Cannot be removed as long as FCM is retained.
- **Decision:** **RETAIN.**

---

### 2.2 Firebase Authentication
- **Flutter Astro Setu Inspection:**
  - `pubspec.yaml` contains `firebase_auth: ^5.5.4`.
  - Thorough code search across all Dart files confirmed **zero usages** of `FirebaseAuth` or `firebase_auth`.
  - Google Sign-In is executed using `google_sign_in: ^6.3.0`, obtaining an `idToken` that is verified by Laravel using `Google_Client` to issue a Laravel Sanctum session token.
  - The Flutter user never creates or signs into a Firebase Auth account.
- **Laravel Backend Inspection:**
  - Laravel uses `Kreait\Firebase\Auth` in `AdminAuthController`:
    ```php
    $uid = 'astrologer_' . $admin->id;
    $auth = app(\Kreait\Firebase\Auth::class);
    $firebaseToken = $auth->createCustomToken($uid)->toString();
    ```
  - This custom token is sent to the astrologer app (Pandit Setu) so that the astrologer can sign into Firebase Auth. The sole reason for this is to satisfy Firestore security rules:
    ```
    match /chats/{chatId}/messages/{messageId} {
      allow create: if (
        (request.auth != null && request.auth.uid == "astrologer_" + request.resource.data.admin_id) ||
        (request.auth == null)
      );
    }
    ```
- **Audit Finding:**
  - Astro Setu does not use `firebase_auth`.
  - In Laravel, Firebase custom token generation exists exclusively to allow the astrologer client to authenticate with Firestore.
- **Decision:**
  - **RETAIN** Google Sign-In / Laravel authentication flow unchanged.
  - In Astro Setu `pubspec.yaml`, `firebase_auth` is dead code and can safely be removed during Phase 4 cleanup.
  - In Laravel, `AdminAuthController@login`'s custom token generation can be retired once Pandit Setu completes its Ably migration.

---

### 2.3 Firebase Cloud Messaging (FCM)
- **Current Role:**
  - Essential for background notifications, daily engagement briefings (horoscope, panchang, insights), and alerting recipients when a new message arrives while their app is backgrounded or killed.
  - Implementation in Laravel is modern and compliant: uses official **FCM HTTP v1** with service account OAuth2 tokens ([`App\Services\FirebaseNotificationService`](file:///C:/wamp64/www/astro_setu/app/Services/FirebaseNotificationService.php)).
  - Implementation in Flutter ([`lib/services/firebase_messaging_service.dart`](file:///C:/Users/rsrav/StudioProjects/astro_setu/lib/services/firebase_messaging_service.dart)) handles token refresh, background message handling, local notifications display, and topic subscriptions.
- **Audit Finding:**
  - FCM is core to the application's user re-engagement and notification delivery.
  - Migrating push notifications to Ably is explicitly excluded from project scope.
- **Decision:** **RETAIN WITHOUT MODIFICATION.** FCM remains the dedicated push notification carrier.

---

### 2.4 Cloud Firestore (Target for Complete Removal from Chat)
- **Current Role:**
  - Used as the real-time chat message transport and message history store.
  - Flutter writes messages to `chats/{sessionId}/messages` and listens via snapshot streams.
  - Laravel creates session metadata documents via `kreait/firebase-php` in `ChatController@startSession`.
- **Cost & Operational Impact:**
  - Every message sent generates Firestore write and read operations.
  - Listening to the snapshot generates billable Firestore document reads for every message delivered.
  - No permanent relational integrity with MySQL (messages are not queryable via Laravel ORM, reporting, or user data exports).
  - Security rules are fragile and currently allow unauthenticated writes.
- **Decision:** **ELIMINATE COMPLETELY FOR CHAT.**
  - All real-time message broadcasting will move to Ably Realtime.
  - All message persistence will move exclusively to Laravel MySQL (`chats` table).
  - All message history queries will be served by paginated Laravel REST endpoints.

---

## 3. Demarcation Matrix: What Stays vs What Goes

```
+-----------------------------------------------------------------------------------+
|                               FIREBASE USAGE MATRIX                               |
+----------------------------------------------------+------------------------------+
| RETAINED (Production Requirements)                 | ELIMINATED (Cost & Transport)|
+----------------------------------------------------+------------------------------+
| [x] Firebase Core (Flutter & Android/iOS)          | [X] Cloud Firestore SDK      |
|     - App initialization in main.dart              |     (cloud_firestore Flutter)|
|     - DefaultFirebaseOptions config                |                              |
|                                                    | [X] Firestore Reads & Writes |
| [x] Firebase Cloud Messaging (FCM HTTP v1)         |     - chats/{id} collection  |
|     - Device token registration                    |     - messages subcollection |
|     - Foreground local notifications               |                              |
|     - Background push notifications                | [X] Firestore in Laravel     |
|     - Astrology engagement topics                  |     - kreait/firebase-php    |
|                                                    |       createFirestore()      |
| [x] Google Authentication                          |     - google/cloud-firestore|
|     - google_sign_in package                       |                              |
|     - Laravel Google_Client ID token verify        | [X] Dead Dependencies        |
|     - Laravel Sanctum session tokens               |     - firebase_auth in       |
|                                                    |       Astro Setu pubspec     |
+----------------------------------------------------+------------------------------+
```

---

## 4. Firestore Artifacts to Retire in Migration

### 4.1 Flutter Application (`astro_setu`)
1. **Remove Dependency:**
   - `cloud_firestore: ^5.6.8` from `pubspec.yaml`.
   - `firebase_auth: ^5.5.4` from `pubspec.yaml` (dead dependency).
2. **Delete / Refactor Service Files:**
   - [`lib/services/chat_firebase_service.dart`](file:///C:/Users/rsrav/StudioProjects/astro_setu/lib/services/chat_firebase_service.dart): Replace with `AblyRealtimeService` and `ChatApiService`.
3. **Refactor Controller & Models:**
   - [`lib/controllers/chat_controller.dart`](file:///C:/Users/rsrav/StudioProjects/astro_setu/lib/controllers/chat_controller.dart): Replace `FirebaseFirestore.instance.collection('chats')...` stream listener with Ably channel subscriber.
   - [`lib/models/chat_message.dart`](file:///C:/Users/rsrav/StudioProjects/astro_setu/lib/models/chat_message.dart): Replace `Timestamp` (Firestore class) with standard ISO-8601 string / `DateTime`.

### 4.2 Laravel Backend (`astro_setu`)
1. **Remove Firestore Document Creation:**
   - In [`app/Http/Controllers/Api/ChatController.php`](file:///C:/wamp64/www/astro_setu/app/Http/Controllers/Api/ChatController.php#L101-L153):
     Remove the `try { ... $firestore->collection('chats')->document(...) ... }` block completely from `startSession`.
2. **Retire Dormant Controller:**
   - [`app/Http/Controllers/Api/FirebaseChatController.php`](file:///C:/wamp64/www/astro_setu/app/Http/Controllers/Api/FirebaseChatController.php) can be marked deprecated and its route removed.
3. **Clean Up Composer Packages (Phase 4):**
   - `google/cloud-firestore` can be removed from `composer.json` once all references are purged.
   - Retain `google/apiclient` and `google/auth` as they are actively used by `FirebaseNotificationService` for FCM HTTP v1 OAuth2 token generation.
