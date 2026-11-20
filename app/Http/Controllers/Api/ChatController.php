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
    $astrologerId = $request->input('astrologer_id');
    $astrologer = $astrologerId ? Astrologer::find($astrologerId) : null;
    $adminId = $astrologer?->admin_id ?: $request->input('admin_id');

    if ($adminId) {
      $request->merge(['admin_id' => $adminId]);
    }

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
      $astrologerId = (int) $request->input('astrologer_id');
      $adminId = (int) $request->input('admin_id');

      // Auto-provision default chat service if missing for registered admin
      app(\App\Services\Chat\ChatServiceProvisioner::class)->provisionForAdmin($adminId);

      // Debug: Log the search parameters
      Log::info('Searching for existing session', [
        'user_id' => $user->id,
        'admin_id' => $adminId,
        'astrologer_id' => $astrologerId,
      ]);

      // Check for existing session between this user, admin, and astrologer
      // (resolving across multi-language astrologer records sharing the same admin_id)
      $existingSession = ChatSession::where('user_id', $user->id)
        ->where('admin_id', $adminId)
        ->where(function ($q) use ($astrologerId, $adminId) {
          $q->where('astrologer_id', $astrologerId)
            ->orWhereHas('astrologer', function ($sub) use ($adminId) {
              $sub->where('admin_id', $adminId);
            });
        })
        ->latest('id')
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
        // Reuse existing session and reactivate it if it was previously closed
        $chatSession = $existingSession;
        $shouldSave = false;
        if ($chatSession->ended_at !== null) {
          $chatSession->ended_at = null;
          $shouldSave = true;
          Log::info('Reactivated existing session', ['session_id' => $chatSession->id]);
        }
        if ($chatSession->astrologer_id != $astrologerId) {
          $chatSession->astrologer_id = $astrologerId;
          $shouldSave = true;
        }
        if ($chatSession->admin_id != $adminId) {
          $chatSession->admin_id = $adminId;
          $shouldSave = true;
        }
        if ($shouldSave) {
          $chatSession->save();
        }
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
        'admin_id' => (int) $chatSession->admin_id,
        'astrologer_id' => (int) $chatSession->astrologer_id,
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

  /**
   * Deprecated: Legacy per-minute fee deduction.
   * Chat is now monetized atomically per message with a 20% platform markup and 5 free introductory messages.
   * Returns HTTP 410 Gone to prevent older clients from continuing deduction timers.
   */
  public function deductFee(Request $request)
  {
    return response()->json([
      'success' => false,
      'deprecated' => true,
      'error' => 'per_minute_billing_retired',
      'message' => 'Per-minute billing is retired. Chat is now billed on a per-message basis.',
    ], 410);
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
