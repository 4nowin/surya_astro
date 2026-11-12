<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ChatSession;
use App\Models\User;
use App\Models\Astrologer;
use App\Models\ChatPayment;
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

    Log::info('Start session request received', [
      'user_id' => Auth::id(),
      'admin_id' => $request->input('admin_id'),
      'astrologer_id' => $request->input('astrologer_id'),
      'all_data' => $request->all(),
    ]);

    try {
      $user = Auth::user();
      $astrologerId = $request->input('astrologer_id');
      $adminId = $request->input('admin_id');

      // Debug: Log the search parameters
      Log::info('Searching for existing session', [
        'user_id' => $user->id,
        'admin_id' => $adminId,
        'astrologer_id' => $astrologerId,
      ]);

      // First, check for existing active session in the database
      $existingSession = ChatSession::where('user_id', $user->id)
        ->where('admin_id', $adminId)
        ->where('astrologer_id', $astrologerId)
        ->whereNull('ended_at')
        ->latest()
        ->first();

      // Debug: Log if we found an existing session
      if ($existingSession) {
        Log::info('Found existing session', [
          'session_id' => $existingSession->id,
          'started_at' => $existingSession->started_at,
          'ended_at' => $existingSession->ended_at,
        ]);
      } else {
        Log::info('No existing session found');
      }

      $chatSession = null;
      $isNew = false;

      if ($existingSession) {
        // Found existing session in database
        $chatSession = $existingSession;
        $isNew = false;
      } else {
        // No existing session, create new one
        $chatSession = ChatSession::create([
          'user_id' => $user->id,
          'admin_id' => $adminId,
          'astrologer_id' => $astrologerId,
          'started_at' => now(),
        ]);
        $isNew = true;

        Log::info('Created new session', [
          'session_id' => $chatSession->id,
          'user_id' => $user->id,
          'admin_id' => $adminId,
          'astrologer_id' => $astrologerId,
        ]);
      }

      // Firestore document creation is deprecated - sessions and messages are managed via MySQL and Ably.

      return response()->json([
        'status' => $isNew ? 'new' : 'existing',
        'chat_session_id' => $chatSession->id,
        'message' => $isNew ? 'New chat session started' : 'Existing chat session resumed',
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

    if ($session && $session->ended_at === null) {
      $session->update(['ended_at' => now()]);
    }

    return response()->json(['status' => 'ended']);
  }
}
