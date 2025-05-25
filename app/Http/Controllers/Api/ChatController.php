<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Chat;
use App\Models\ChatSession;
use App\Models\User;
use App\Models\Astrologer;
use App\Models\ChatPayment;
use Kreait\Firebase\Factory;

class ChatController extends Controller
{

  public function startSession(Request $request)
  {
    $request->validate([
      'astrologer_id' => 'required|exists:astrologers,id',
    ]);

    $user = Auth::user();
    $astrologerId = $request->input('astrologer_id');

    $existingSession = ChatSession::where('user_id', $user->id)
      ->where('astrologer_id', $astrologerId)
      ->whereNull('ended_at')
      ->latest()
      ->first();

    if ($existingSession) {
      $chatSession = $existingSession;
    } else {
      $chatSession = ChatSession::create([
        'user_id' => $user->id,
        'astrologer_id' => $astrologerId,
        'started_at' => now(),
      ]);
    }

    // 🔥 Add metadata to Firestore
    $factory = (new Factory)->withServiceAccount(env('FIREBASE_CREDENTIALS_PATH'));
    $firestore = $factory->createFirestore()->database();

    $firestore->collection('chats')->document((string)$chatSession->id)->set([
      'user_id' => $user->id,
      'user_name' => $user->name,
      'astrologer_id' => $astrologerId,
      'started_at' => now()->toDateTimeString(),
    ]);

    return response()->json([
      'status' => $existingSession ? 'existing' : 'new',
      'chat_session_id' => $chatSession->id,
      'message' => $existingSession ? 'Existing chat session resumed' : 'New chat session started',
    ]);
  }

  public function deductFee(Request $request)
  {
    $request->validate([
      'chat_session_id' => 'required|exists:chat_sessions,id'
    ]);

    $user = User::findOrFail(Auth::id());
    $session = ChatSession::findOrFail($request->chat_session_id);

    $lastFeeLog = $session->updated_at; // use last updated as approximation
    if ($lastFeeLog && now()->diffInSeconds($lastFeeLog) < 50) {
      return response()->json(['message' => 'Fee already deducted recently.'], 429);
    }

    $astrologer = Astrologer::findOrFail($session->astrologer_id);
    $fee = $astrologer->price;

    if ($user->wallet_balance < $fee) {
      return response()->json(['error' => 'Insufficient balance'], 402);
    }

    $user->wallet_balance -= $fee;
    $user->save();

    ChatPayment::create([
      'chat_session_id' => $session->id,
      'user_id' => $user->id,
      'astrologer_id' => $astrologer->id,
      'amount' => $fee,
      'deducted_at' => now(),
    ]);

    $session->increment('total_minutes');
    $session->increment('total_fee', $fee);

    return response()->json(['message' => 'Fee deducted']);
  }

  public function endChatSession(Request $request)
  {
    $user = $request->user();
    $sessionId = $request->input('chat_session_id');

    $session = ChatSession::where('id', $sessionId)
      ->where('user_id', $user->id)
      ->first();

    if ($session) {
      $session->update(['ended_at' => now()]);
    }

    return response()->json(['status' => 'ended']);
  }
}
