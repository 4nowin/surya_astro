<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Chat;
use App\Models\ChatSession;
use App\Services\FcmService;
use App\Services\FirebaseService;
use App\Models\Astrologer;

class AstrologerChatController extends Controller
{
  private function getAstrologerBySession(Request $request, $sessionId)
  {
    $admin = $request->user();

    $session = ChatSession::find($sessionId);
    if (! $session) {
      abort(404, 'Chat session not found');
    }

    $astrologer = Astrologer::where('id', $session->astrologer_id)
      ->where('admin_id', $admin->id)
      ->first();

    if (! $astrologer) {
      abort(403, 'Unauthorized access to this chat session');
    }

    return $astrologer;
  }

  /**
   * Get historical messages (paginated)
   */
  public function getMessages(Request $request, $sessionId)
  {
    $this->getAstrologerBySession($request, $sessionId);

    $beforeId = $request->query('before_id');
    $limit = $request->query('limit', 20);

    $query = Chat::where('chat_session_id', $sessionId)->orderByDesc('id');

    if ($beforeId) {
      $query->where('id', '<', $beforeId);
    }

    $messages = $query->limit($limit)->get()->sortBy('id')->values();

    return response()->json($messages);
  }

  /**
   * Send a message as an astrologer (persist + push)
   */
  public function sendMessage(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'session_id' => 'required|exists:chat_sessions,id',
      'message' => 'required|string|max:1000',
    ]);

    if ($validator->fails()) {
      return response()->json([
        'message' => 'Validation failed',
        'errors' => $validator->errors()
      ], 422);
    }

    $sessionId = $request->input('session_id');
    $astrologer = $this->getAstrologerBySession($request, $sessionId);

    $chat = new Chat();
    $chat->chat_session_id = $sessionId;
    $chat->sender_id = $astrologer->id;
    $chat->sender_type = 'astrologer';
    $chat->message = $request->input('message');
    $chat->save();

    $chat->session->touch(); // update session timestamp

    // ✅ Send FCM v1 push to user
    $user = $chat->session->user;

    if (!empty($user->fcm_token)) {
      $fcm = new FirebaseService();
      $fcm->sendNotification(
        $user->fcm_token,
        "New message from astrologer",
        $chat->message
      );
    }

    return response()->json(['message' => 'Message sent successfully']);
  }

  /**
   * Get chat sessions for all astrologers under this admin
   */
  public function chatSessions(Request $request)
  {
    $admin = $request->user();
    $astrologerIds = Astrologer::where('admin_id', $admin->id)->pluck('id');

    $sessions = ChatSession::with([
      'user:id,name,email',
      'messages' => fn($q) => $q->latest()->limit(1)
    ])
      ->whereIn('astrologer_id', $astrologerIds)
      ->orderByDesc('updated_at')
      ->get();

    return response()->json($sessions);
  }

  /**
   * Get current online status of astrologer
   */
  public function getStatus(Request $request)
  {
    $astrologerId = $request->query('astrologer_id');
    $admin = $request->user();

    $astrologer = Astrologer::where('id', $astrologerId)
      ->where('admin_id', $admin->id)
      ->firstOrFail();

    return response()->json([
      'is_online' => (bool) $astrologer->is_online,
    ]);
  }

  /**
   * Set astrologer's online status
   */
  public function setOnline(Request $request)
  {
    $request->validate([
      'astrologer_id' => 'required|exists:astrologers,id',
      'is_online' => 'required|boolean',
    ]);

    $admin = $request->user();

    $astrologer = Astrologer::where('id', $request->input('astrologer_id'))
      ->where('admin_id', $admin->id)
      ->firstOrFail();

    $astrologer->is_online = $request->input('is_online');
    $astrologer->save();

    return response()->json([
      'message' => 'Status updated successfully',
      'is_online' => $astrologer->is_online,
    ]);
  }

  /**
   * Toggle astrologer's online status
   */
  public function toggleOnline(Request $request)
  {
    $request->validate([
      'astrologer_id' => 'required|exists:astrologers,id',
    ]);

    $admin = $request->user();

    $astrologer = Astrologer::where('id', $request->input('astrologer_id'))
      ->where('admin_id', $admin->id)
      ->firstOrFail();

    $astrologer->is_online = !$astrologer->is_online;
    $astrologer->save();

    return response()->json([
      'message' => 'Status toggled successfully',
      'is_online' => $astrologer->is_online,
    ]);
  }

  /**
   * (Optional legacy feature) Get chat metadata
   */
  public function getAstrologerChats(Request $request)
  {
    $astrologerId = $request->query('astrologer_id');


    // Fetch latest message per session
    $latestMessages = Chat::where('astrologer_id', $astrologerId)
      ->with('session.user:id,name') // optional: eager load related user
      ->orderByDesc('sent_at')
      ->get()
      ->groupBy('chat_session_id')
      ->map(function ($group) {
        return $group->first(); // latest message per session
      })
      ->values();

    return response()->json($latestMessages);
  }
}
