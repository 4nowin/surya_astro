<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Chat;
use App\Models\Astrologer;
use App\Models\ChatPayment;

class FirebaseChatController extends Controller
{
  /**
   * Store message in MySQL for historical/logging purposes
   */
  public function store(Request $request)
  {
    $request->validate([
      'chat_id' => 'required|string',
      'sender_id' => 'required|exists:users,id',
      'receiver_id' => 'required|exists:astrologers,id',
      'message' => 'required|string',
      'timestamp' => 'required|date',
    ]);

    // Store message in DB (for logs/history/analytics)
    Chat::create([
      'chat_session_id' => $request->chat_session_id, // rename field if needed
      'user_id' => $request->sender_id,
      'astrologer_id' => $request->receiver_id,
      'message' => $request->message,
      'sender' => $request->sender_type ?? 'user', // default to 'user'
      'sent_at' => $request->timestamp,
    ]);

    // Check if it's the first message in the chat
    $messageCount = Chat::where('chat_id', $request->chat_id)->count();

    if ($messageCount === 1) {
      // Send FCM notification to astrologer
      $fcmToken = Astrologer::where('id', $request->receiver_id)->value('fcm_token');

      if ($fcmToken) {
        $this->sendFcmNotification($fcmToken, 'New Chat', 'A user just started a chat with you.');
      }
    }

    return response()->json(['status' => 'success']);
  }

  /**
   * Deduct fee manually (if needed outside scheduled Laravel jobs)
   */
  public function deductFee(Request $request)
  {
    $request->validate([
      'user_id' => 'required|exists:users,id',
      'astrologer_id' => 'required|exists:astrologers,id',
      'chat_id' => 'required|string',
    ]);

    $user = User::firstOrFail($request->user_id);
    $astrologer = Astrologer::firstOrFail($request->astrologer_id);
    $fee = $astrologer->price ?? 50;

    if ($user->wallet_balance < $fee) {
      return response()->json([
        'status' => 'fail',
        'message' => 'Insufficient balance',
      ], 403);
    }

    // Deduct fee
    $user->wallet_balance -= $fee;
    $user->save();

    // Log payment (optional, for tracking Firebase-triggered fees)
    ChatPayment::create([
      'chat_id' => $request->chat_session_id,
      'user_id' => $user->id,
      'astrologer_id' => $astrologer->id,
      'amount' => $fee,
      'deducted_at' => now(),
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    return response()->json(['status' => 'success']);
  }

  /**
   * Send Firebase Cloud Messaging (FCM) push notification
   */
  private function sendFcmNotification($fcmToken, $title, $body)
  {
    $data = [
      'to' => $fcmToken,
      'notification' => [
        'title' => $title,
        'body' => $body,
        'sound' => 'default'
      ]
    ];

    $headers = [
      'Authorization: key=' . env('FCM_SERVER_KEY'),
      'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
  }
}
