<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Astrologer;
use App\Models\ChatSession;
use App\Models\Consultation;
use App\Models\Jajman;
use App\Models\ProfessionalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConsultationApiController extends Controller
{
    /**
     * List all conversations for the authenticated professional with matched Jajman CRM record.
     * GET /api/astrologer/conversations
     */
    public function getConversations(Request $request)
    {
        $admin = $request->user();
        $astrologerIds = Astrologer::where('admin_id', $admin->id)->pluck('id');

        $sessions = ChatSession::with([
            'user',
            'messages' => fn($q) => $q->latest('id')->limit(1),
        ])
            ->whereIn('astrologer_id', $astrologerIds)
            ->orderByDesc('updated_at')
            ->get()
            ->unique('user_id')
            ->values();

        // Get all Jajmans associated with this professional
        $jajmans = Jajman::whereHas('professionals', function ($q) use ($admin) {
            $q->where('admins.id', $admin->id);
        })->get();

        $formatted = $sessions->map(function (ChatSession $session) use ($jajmans, $admin) {
            $user = $session->user;
            $lastMessage = $session->messages->first();

            // Match Jajman: by user_id first, then by exact phone match if verified
            $matchedJajman = null;
            if ($user) {
                $matchedJajman = $jajmans->firstWhere('user_id', $user->id);
                $userPhone = $user->phone ?? $user->mobile ?? null;
                if (!$matchedJajman && !empty($userPhone)) {
                    $cleanUserPhone = \App\Services\Jajman\ContactNormalization::normalizePhone($userPhone);
                    if ($cleanUserPhone) {
                        $matchedJajman = $jajmans->first(function ($j) use ($cleanUserPhone) {
                            if (empty($j->phone)) return false;
                            $cleanJPhone = \App\Services\Jajman\ContactNormalization::normalizePhone($j->phone);
                            return $cleanJPhone === $cleanUserPhone;
                        });
                    }
                }

                // If user has chatted (sent messages) but no Jajman is linked yet, auto-link/create safely
                if (!$matchedJajman && $session->messages->isNotEmpty()) {
                    try {
                        $matchedJajman = app(\App\Services\Jajman\JajmanAutoLinkService::class)->ensureJajmanForSession($session);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Error auto-linking Jajman in getConversations: ' . $e->getMessage());
                    }
                }
            }

            // Check if user has granted personal data sharing consent to this astrologer (admin_id)
            $consentGranted = false;
            if ($user) {
                $consentGranted = \App\Models\CustomerAstrologerConsent::where('user_id', $user->id)
                    ->where('admin_id', $admin->id)
                    ->where('status', \App\Models\CustomerAstrologerConsent::STATUS_GRANTED)
                    ->exists();
            }

            // Avatar resolution:
            // 1. Prefer user's actual Astro Setu account profile image (users.profile_image)
            // 2. If unavailable, use linked Jajman's profile_photo
            // 3. If neither exists, return null
            $avatarUrl = $user?->avatar_url ?? $matchedJajman?->avatar_url ?? null;

            // Enforce consent for phone and email:
            $clientPhone = null;
            $clientEmail = null;
            if ($consentGranted) {
                $clientPhone = ($user->phone ?? $user->mobile ?? null) ?? ($matchedJajman->phone ?? null);
                $clientEmail = $user->email ?? ($matchedJajman->email ?? null);
            } elseif (!$user && $matchedJajman) {
                // Fully offline manual Jajman created by practitioner
                $clientPhone = $matchedJajman->phone ?? null;
                $clientEmail = $matchedJajman->email ?? null;
            }

            return [
                'session_id' => $session->id,
                'status' => $session->ended_at ? 'ended' : 'active',
                'client_name' => $matchedJajman ? $matchedJajman->name : ($user->name ?? 'Astro Setu Client'),
                'client_avatar_url' => $avatarUrl,
                'client_phone' => $clientPhone,
                'client_email' => $clientEmail,
                'consent_granted' => $consentGranted,
                'jajman_id' => $matchedJajman?->id,
                'has_possible_duplicates' => (bool) ($matchedJajman?->has_possible_duplicates ?? false),
                'possible_duplicate_ids' => $matchedJajman?->possible_duplicate_ids ?? [],
                'last_message' => $lastMessage?->message,
                'last_message_sender' => $lastMessage?->sender,
                'last_message_at' => $lastMessage?->created_at?->toIso8601String() ?? $session->updated_at->toIso8601String(),
                'unread_count' => 0, // In this model, unread can be tracked in future
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ]);
    }

    /**
     * List all recorded consultations (both online chats and offline/in-person entries).
     * GET /api/astrologer/consultations
     */
    public function index(Request $request)
    {
        $admin = $request->user();

        $query = Consultation::where('admin_id', $admin->id)
            ->with(['jajman:id,name,phone,city', 'chatSession:id,ended_at', 'service:id,name,category,price,currency']);

        if ($jajmanId = $request->query('jajman_id')) {
            $query->where('jajman_id', $jajmanId);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $consultations = $query->orderByDesc('consultation_date')->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $consultations->items(),
            'meta' => [
                'current_page' => $consultations->currentPage(),
                'last_page' => $consultations->lastPage(),
                'total' => $consultations->total(),
            ],
        ]);
    }

    /**
     * Record a consultation entry (offline puja, in-person visit, phone reading, or online log).
     * POST /api/astrologer/consultations
     */
    public function store(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'client_name' => 'required|string|max:191',
            'jajman_id' => 'nullable|exists:jajmans,id',
            'service_id' => 'nullable|integer',
            'chat_session_id' => 'nullable|exists:chat_sessions,id',
            'type' => ['required', Rule::in(['online_chat', 'in_person', 'phone_call', 'ritual_puja', 'vastu_visit'])],
            'status' => ['required', Rule::in(['completed', 'in_progress', 'scheduled', 'cancelled'])],
            'consultation_date' => 'nullable|date',
            'duration_minutes' => 'nullable|integer',
            'fee_charged' => 'nullable|numeric',
            'summary' => 'nullable|string',
        ]);

        if (!empty($validated['jajman_id'])) {
            // Ensure Jajman belongs to this professional
            $jajman = Jajman::whereHas('professionals', function ($q) use ($admin) {
                $q->where('admins.id', $admin->id);
            })->find($validated['jajman_id']);

            if (!$jajman) {
                abort(404, 'Jajman not found in your practice.');
            }
        }

        $service = null;
        if (!empty($validated['service_id'])) {
            $service = ProfessionalService::where('admin_id', $admin->id)->find($validated['service_id']);
            if (!$service) {
                abort(404, 'Service not found in your catalogue.');
            }
        }

        $feeCharged = $validated['fee_charged'] ?? $service?->price;

        $consultation = Consultation::create([
            'admin_id' => $admin->id,
            'jajman_id' => $validated['jajman_id'] ?? null,
            'service_id' => $service?->id,
            'service_name_snapshot' => $service?->name,
            'chat_session_id' => $validated['chat_session_id'] ?? null,
            'client_name' => $validated['client_name'],
            'type' => $validated['type'],
            'status' => $validated['status'],
            'consultation_date' => $validated['consultation_date'] ?? now(),
            'duration_minutes' => $validated['duration_minutes'] ?? $service?->duration_minutes,
            'fee_charged' => $feeCharged,
            'summary' => $validated['summary'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Consultation recorded successfully.',
            'data' => $consultation->load(['jajman', 'service', 'chatSession']),
        ], 201);
    }
}
