<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CustomerAstrologerConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerConsentApiController extends Controller
{
    /**
     * 1. GET /api/customer/consent/{adminId}
     * Check consent status for a specific astrologer (admin_id).
     */
    public function getConsent(Request $request, int $adminId)
    {
        $user = $request->user();
        $sessionId = $request->query('session_id') ? (int) $request->query('session_id') : null;

        $consent = CustomerAstrologerConsent::where('user_id', $user->id)
            ->where('admin_id', $adminId)
            ->first();

        $status = $consent ? $consent->status : CustomerAstrologerConsent::STATUS_PENDING;
        $hasConsented = ($consent && $consent->isGranted());
        $shouldPrompt = $consent ? $consent->shouldPromptForSession($sessionId) : true;

        return response()->json([
            'success' => true,
            'consent' => [
                'admin_id' => $adminId,
                'status' => $status,
                'has_consented' => $hasConsented,
                'should_prompt' => $shouldPrompt,
                'consented_at' => $consent?->consented_at?->toIso8601String(),
                'withdrawn_at' => $consent?->withdrawn_at?->toIso8601String(),
                'scope' => $consent ? $consent->scope : CustomerAstrologerConsent::SCOPE_PERSONAL_BIRTH_PROFILE,
                'last_session_id' => $consent?->last_session_id,
            ],
        ]);
    }

    /**
     * 2. POST /api/customer/consent
     * Record customer consent action: grant (YES), decline_session (NO), or withdraw.
     */
    public function recordConsent(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'admin_id' => 'required|integer|exists:admins,id',
            'action' => 'required|in:grant,decline_session,withdraw',
            'chat_session_id' => 'nullable|integer',
        ]);

        $adminId = (int) $validated['admin_id'];
        $action = $validated['action'];
        $sessionId = isset($validated['chat_session_id']) ? (int) $validated['chat_session_id'] : null;

        $consent = CustomerAstrologerConsent::firstOrNew([
            'user_id' => $user->id,
            'admin_id' => $adminId,
        ]);

        if ($action === 'grant') {
            $consent->status = CustomerAstrologerConsent::STATUS_GRANTED;
            $consent->consented_at = now();
            $consent->withdrawn_at = null;
            if ($sessionId) {
                $consent->last_session_id = $sessionId;
            }
            $message = 'Personal & birth profile sharing consent granted.';
        } elseif ($action === 'decline_session') {
            $consent->status = CustomerAstrologerConsent::STATUS_DECLINED_SESSION;
            if ($sessionId) {
                $consent->last_session_id = $sessionId;
            }
            $message = 'Personal details kept private for this session.';
        } else { // withdraw
            $consent->status = CustomerAstrologerConsent::STATUS_WITHDRAWN;
            $consent->withdrawn_at = now();
            $message = 'Personal & birth profile sharing consent withdrawn.';
        }

        $consent->scope = CustomerAstrologerConsent::SCOPE_PERSONAL_BIRTH_PROFILE;
        $consent->save();

        return response()->json([
            'success' => true,
            'message' => $message,
            'consent' => [
                'admin_id' => $adminId,
                'status' => $consent->status,
                'has_consented' => $consent->isGranted(),
                'should_prompt' => $consent->shouldPromptForSession($sessionId),
                'consented_at' => $consent->consented_at?->toIso8601String(),
                'withdrawn_at' => $consent->withdrawn_at?->toIso8601String(),
                'scope' => $consent->scope,
                'last_session_id' => $consent->last_session_id,
            ],
        ]);
    }

    /**
     * 3. GET /api/customer/consents
     * List all astrologers where customer has granted consent.
     */
    public function listConsents(Request $request)
    {
        $user = $request->user();

        $consents = CustomerAstrologerConsent::where('user_id', $user->id)
            ->where('status', CustomerAstrologerConsent::STATUS_GRANTED)
            ->with(['admin:id,name,email'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $consents,
        ]);
    }

    /**
     * 4. POST /api/customer/consent/withdraw
     * Direct endpoint to withdraw consent for an astrologer.
     */
    public function withdrawConsent(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'admin_id' => 'required|integer|exists:admins,id',
        ]);

        $consent = CustomerAstrologerConsent::where('user_id', $user->id)
            ->where('admin_id', $validated['admin_id'])
            ->first();

        if ($consent) {
            $consent->status = CustomerAstrologerConsent::STATUS_WITHDRAWN;
            $consent->withdrawn_at = now();
            $consent->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Consent withdrawn successfully.',
        ]);
    }
}
