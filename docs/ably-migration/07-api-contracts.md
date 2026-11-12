# 07 – API Contracts & Ably Event Specifications

## Overview
This document specifies the exact API contracts, HTTP endpoints, Ably channel definitions, and event payloads used across the Astro Setu ecosystem (Laravel backend, Astro Setu Flutter app, and Pandit Setu Flutter app).

---

## 1. Authentication & Security Policy

1. **Authentication Carrier:**
   - Standard Sanctum Bearer Token via HTTP Header: `Authorization: Bearer <token>`.
   - Users authenticate with guard `web` / Sanctum token.
   - Astrologers authenticate with guard `admin` / Sanctum token.
2. **Ably Key Protection:**
   - The root Ably API key (`ABLY_KEY`) is stored strictly in Laravel server environment (`.env`).
   - Neither mobile application receives or embeds the root key.
   - Mobile clients obtain temporary, signed `TokenRequest` objects issued by Laravel.
3. **Capability Constraints:**
   - Channel: `chat:session:{chat_session_id}`
   - Allowed operations: `["subscribe"]` strictly.
   - Mobile clients have zero publish capabilities on Ably channels. All publishes are performed by Laravel REST client.
   - Token TTL: 3600 seconds (1 hour).

---

## 2. API Endpoints

### 2.1 Request Ably Token
Generates a signed, scoped Ably `TokenRequest`.

- **User Route:** `POST /api/{lang}/chat/ably-token`
- **Astrologer Route:** `POST /api/astrologer/chat/ably-token`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer <token>`
- **Request Body:**
  ```json
  {
    "chat_session_id": 142
  }
  ```
- **Success Response (200 OK):**
  ```json
  {
    "success": true,
    "data": {
      "channel": "chat:session:142",
      "token_request": {
        "keyName": "xVLyHw.example",
        "ttl": 3600000,
        "timestamp": 1731362400000,
        "capability": "{\"chat:session:142\":[\"subscribe\"]}",
        "clientId": "user_45",
        "nonce": "c4ca4238a0b923820dcc509a6f75849b",
        "mac": "abcdef1234567890..."
      }
    }
  }
  ```
- **Error Responses:**
  - `401 Unauthorized`: Missing or invalid Bearer token.
  - `403 Forbidden`: Authenticated entity does not own or participate in this session.
  - `404 Not Found`: Chat session does not exist.
  - `410 Gone`: Chat session has already ended (`status != 'active'` or `ended_at IS NOT NULL`).

---

### 2.2 Send Message
Stores message permanently in MySQL, publishes to Ably channel, and sends FCM push notification.

- **User Route:** `POST /api/{lang}/chat/message/send`
- **Astrologer Route:** `POST /api/astrologer/chat/message/send`
- **Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `Authorization: Bearer <token>`
- **Request Body:**
  ```json
  {
    "session_id": 142,
    "message": "Namaste Guruji, can you check my kundli?",
    "client_nonce": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d"
  }
  ```
  *(Note: `chat_session_id` is also accepted as alias for `session_id`).*
- **Success Response (201 Created or 200 OK):**
  ```json
  {
    "success": true,
    "data": {
      "id": 1052,
      "chat_session_id": 142,
      "client_nonce": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
      "message": "Namaste Guruji, can you check my kundli?",
      "sender_id": 45,
      "sender_type": "user",
      "receiver_id": 12,
      "user_id": 45,
      "astrologer_id": 12,
      "user_name": "Ramesh Kumar",
      "sent_at": "2026-11-12T10:45:00.000000Z"
    }
  }
  ```
- **Idempotency Behavior:**
  - If a message with the identical `(chat_session_id, client_nonce)` has already been saved, the server returns the existing record with `200 OK` and does not duplicate the record or re-notify.
- **Error Responses:**
  - `422 Unprocessable Content`: Validation failure (empty message, message > 2000 chars, missing session_id or client_nonce).
  - `403 Forbidden`: Authenticated sender is not a participant in this session.
  - `410 Gone`: Session is closed or ended.

---

### 2.3 Fetch Messages (History & Reconnection Catch-Up)
Retrieves paginated messages from MySQL in ascending chronological order (oldest to newest).

- **User Route:** `GET /api/{lang}/chat/sessions/{sessionId}/messages`
- **Astrologer Route:** `GET /api/astrologer/chat/sessions/{sessionId}/messages`
- **Query Parameters:**
  - `limit`: (Optional, default 30, max 100). Number of records to return.
  - `before_id`: (Optional). Fetch messages with `id < before_id` (scroll up for older messages).
  - `after_id`: (Optional). Fetch messages with `id > after_id` (reconnection catch-up for missed messages).
- **Success Response (200 OK):**
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 1050,
        "chat_session_id": 142,
        "client_nonce": "a1b2c3d4-...",
        "message": "Hello",
        "sender_id": 12,
        "sender_type": "astrologer",
        "receiver_id": 45,
        "user_id": 45,
        "astrologer_id": 12,
        "user_name": "Pandit Sharma",
        "sent_at": "2026-11-12T10:40:00.000000Z"
      },
      {
        "id": 1052,
        "chat_session_id": 142,
        "client_nonce": "9b1deb4d-...",
        "message": "Namaste Guruji, can you check my kundli?",
        "sender_id": 45,
        "sender_type": "user",
        "receiver_id": 12,
        "user_id": 45,
        "astrologer_id": 12,
        "user_name": "Ramesh Kumar",
        "sent_at": "2026-11-12T10:45:00.000000Z"
      }
    ],
    "pagination": {
      "limit": 30,
      "before_id": null,
      "after_id": null,
      "has_more": false
    }
  }
  ```

---

### 2.4 End Chat Session
Terminates an active chat session.

- **User Route:** `POST /api/{lang}/chat/session/end`
- **Astrologer Route:** `POST /api/astrologer/chat/session/end`
- **Request Body:**
  ```json
  {
    "chat_session_id": 142,
    "reason": "user_left"
  }
  ```
- **Success Response (200 OK):**
  ```json
  {
    "success": true,
    "message": "Chat session ended successfully."
  }
  ```

---

## 3. Ably Realtime Event Specification

### 3.1 Channel Naming Convention
Every chat session has a dedicated Ably channel:
```
chat:session:{chat_session_id}
```
Example: `chat:session:142`

### 3.2 Event: `message.created`
Broadcast by Laravel server when a message is persisted.

- **Channel:** `chat:session:{chat_session_id}`
- **Event Name:** `message.created`
- **Payload Schema:**
  ```json
  {
    "id": 1052,
    "chat_session_id": 142,
    "client_nonce": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
    "message": "Namaste Guruji, can you check my kundli?",
    "sender_id": 45,
    "sender_type": "user",
    "receiver_id": 12,
    "user_id": 45,
    "astrologer_id": 12,
    "user_name": "Ramesh Kumar",
    "sent_at": "2026-11-12T10:45:00.000000Z"
  }
  ```

---

## 4. Push Notification (FCM HTTP v1) Payload

Dispatched exclusively by Laravel via `ChatNotificationService` when a message is received:

```json
{
  "token": "<recipient_fcm_token>",
  "notification": {
    "title": "New message from Ramesh Kumar",
    "body": "Namaste Guruji, can you check my kundli?"
  },
  "data": {
    "type": "chat_message",
    "session_id": "142",
    "chat_session_id": "142",
    "user_id": "45",
    "astrologer_id": "12",
    "user_name": "Ramesh Kumar",
    "title": "New message from Ramesh Kumar",
    "body": "Namaste Guruji, can you check my kundli?"
  }
}
```
*(Foreground suppression is active on both apps when `session_id == activeSessionId`).*
