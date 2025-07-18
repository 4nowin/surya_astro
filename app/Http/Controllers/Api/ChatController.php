<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ChatSession;
use App\Models\User;
use App\Models\Astrologer;
use App\Models\ChatPayment;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{

  public function startSession(Request $request)
  {
    try {
      $request->validate([
        'astrologer_id' => 'required|exists:astrologers,id',
        'admin_id' => 'required|exists:admins,id',
      ]);
    } catch (\Throwable $e) {
      Log::error('Validation failed in startSession', [
        'error' => $e->getMessage(),
        'data' => $request->all(),
        'trace' => $e->getTraceAsString(),
      ]);

      return response()->json([
        'status' => 'error',
        'message' => 'Invalid astrologer ID or missing data.',
      ], 422);
    }

    try {
      $user = Auth::user();
      $astrologerId = $request->input('astrologer_id');
      $adminId = $request->input('admin_id');

      $existingSession = ChatSession::where('user_id', $user->id)
        ->where('admin_id', $adminId)
        ->where('astrologer_id', $astrologerId)
        ->whereNull('ended_at')
        ->latest()
        ->first();

      if ($existingSession) {
        $chatSession = $existingSession;
      } else {
        $chatSession = ChatSession::create([
          'user_id' => $user->id,
          'admin_id' => $adminId,
          'astrologer_id' => $astrologerId,
          'started_at' => now(),
        ]);
      }

      try {
        $credentialsPath = storage_path('app/' . config('services.firebase.credentials_path'));
        if (!$credentialsPath) {
          Log::error("FIREBASE_CREDENTIALS_PATH is not set in config");
          return response()->json(['error' => 'Server misconfiguration.'], 500);
        }
        $factory = (new Factory)->withServiceAccount($credentialsPath);
        $firestore = $factory->createFirestore()->database();

        $firestore->collection('chats')->document((string) $chatSession->id)->set([
          'text' => '',
          'user_id' => $user->id,
          'admin_id' => $adminId,
          'astrologer_id' => $astrologerId,
          'user_type' => 'user',
          'user_name' => $user->name,
          'timestamp' => now()->toDateTimeString(),
        ]);
      } catch (\Throwable $e) {
        Log::error('Firestore write failed in startSession', [
          'chat_session_id' => $chatSession->id,
          'user_id' => $user->id,
          'admin_id' => $adminId,
          'astrologer_id' => $astrologerId,
          'error' => $e->getMessage(),
          'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
          'status' => 'error',
          'message' => 'Failed to create Firestore chat metadata.',
        ], 500);
      }

      return response()->json([
        'status' => $existingSession ? 'existing' : 'new',
        'chat_session_id' => $chatSession->id,
        'message' => $existingSession ? 'Existing chat session resumed' : 'New chat session started',
      ]);
    } catch (\Throwable $e) {
      Log::error('General error in startSession', [
        'user_id' => Auth::id(),
        'data' => $request->all(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      return response()->json([
        'status' => 'error',
        'message' => 'Something went wrong while starting chat session.',
      ], 500);
    }
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
      'admin_id' => $astrologer->admin_id,
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
