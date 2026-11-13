<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Jajman;
use App\Models\ProfessionalBookingRequest;
use App\Models\ProfessionalPublicProfile;
use App\Models\ProfessionalService;
use App\Services\Appointment\AppointmentConflictService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfessionalPublicProfileApiController extends Controller
{
    protected AppointmentConflictService $conflictService;

    public function __construct(AppointmentConflictService $conflictService)
    {
        $this->conflictService = $conflictService;
    }

    /**
     * 1. GET /api/astrologer/public-profile
     * Get or initialize authenticated practitioner's public profile settings.
     */
    public function getProfile(Request $request)
    {
        $admin = $request->user();

        $profile = ProfessionalPublicProfile::firstOrCreate(
            ['admin_id' => $admin->id],
            [
                'display_name' => $admin->name,
                'slug' => ProfessionalPublicProfile::generateUniqueSlug($admin->name, $admin->id),
                'headline' => 'Professional Spiritual & Vedic Consultant',
                'categories' => ['astrologer'],
                'specializations' => [],
                'languages' => ['Hindi', 'English'],
                'years_of_experience' => 5,
                'is_online_available' => true,
                'is_in_person_available' => false,
                'is_home_visit_available' => false,
                'is_published' => false,
                'is_accepting_bookings' => true,
                'is_email_mandatory' => false,
                'profile_status' => 'not_published',
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    /**
     * 2. POST /api/astrologer/public-profile
     * Save/update authenticated practitioner's public profile.
     */
    public function updateProfile(Request $request)
    {
        $admin = $request->user();

        $profile = ProfessionalPublicProfile::firstOrCreate(['admin_id' => $admin->id]);

        $validated = $request->validate([
            'display_name' => 'required|string|max:191',
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('professional_public_profiles', 'slug')->ignore($profile->id),
            ],
            'profile_photo_url' => 'nullable|string|max:500',
            'cover_image_url' => 'nullable|string|max:500',
            'headline' => 'nullable|string|max:255',
            'about' => 'nullable|string|max:5000',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:64',
            'specializations' => 'nullable|array',
            'specializations.*' => 'string|max:100',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:64',
            'years_of_experience' => 'nullable|integer|min:0|max:100',
            'qualifications' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'service_area' => 'nullable|string|max:255',
            'is_online_available' => 'nullable|boolean',
            'is_in_person_available' => 'nullable|boolean',
            'is_home_visit_available' => 'nullable|boolean',
            'public_phone' => 'nullable|string|max:32',
            'public_email' => 'nullable|email|max:191',
            'website' => 'nullable|string|max:255',
            'public_address' => 'nullable|string|max:500',
            'is_published' => 'nullable|boolean',
            'is_accepting_bookings' => 'nullable|boolean',
            'is_email_mandatory' => 'nullable|boolean',
            'profile_status' => 'nullable|string|in:not_published,published,temporarily_unavailable',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = ProfessionalPublicProfile::generateUniqueSlug(
                $validated['display_name'],
                $admin->id
            );
        } else {
            $validated['slug'] = strtolower(trim($validated['slug']));
        }

        if (isset($validated['is_published'])) {
            if ($validated['is_published']) {
                $validated['profile_status'] = 'published';
            } else {
                $validated['profile_status'] = 'not_published';
            }
        }

        $profile->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Public profile saved successfully.',
            'data' => $profile->fresh(),
        ]);
    }

    /**
     * 3. POST /api/astrologer/public-profile/publish
     * Quick toggle to publish, unpublish, or pause public bookings.
     */
    public function togglePublish(Request $request)
    {
        $admin = $request->user();
        $profile = ProfessionalPublicProfile::firstOrCreate(['admin_id' => $admin->id]);

        $validated = $request->validate([
            'is_published' => 'required|boolean',
            'is_accepting_bookings' => 'nullable|boolean',
        ]);

        $isPublished = (bool) $validated['is_published'];

        $profile->is_published = $isPublished;
        $profile->profile_status = $isPublished ? 'published' : 'not_published';
        if (isset($validated['is_accepting_bookings'])) {
            $profile->is_accepting_bookings = (bool) $validated['is_accepting_bookings'];
        }
        $profile->save();

        return response()->json([
            'success' => true,
            'message' => $isPublished ? 'Public profile is now live and published!' : 'Public profile has been unpublished.',
            'data' => $profile,
        ]);
    }

    /**
     * 4. GET /api/astrologer/booking-requests
     * List incoming booking requests for practitioner.
     */
    public function listBookingRequests(Request $request)
    {
        $admin = $request->user();

        $query = ProfessionalBookingRequest::where('admin_id', $admin->id)
            ->with(['service', 'jajman', 'appointment']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                  ->orWhere('client_phone', 'like', "%{$search}%")
                  ->orWhere('booking_reference', 'like', "%{$search}%")
                  ->orWhere('service_name_snapshot', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);
        $requests = $query->orderByDesc('created_at')->paginate($perPage);

        // Compute badge counts
        $counts = [
            'pending' => ProfessionalBookingRequest::where('admin_id', $admin->id)->where('status', 'pending')->count(),
            'confirmed' => ProfessionalBookingRequest::where('admin_id', $admin->id)->where('status', 'confirmed')->count(),
            'rejected' => ProfessionalBookingRequest::where('admin_id', $admin->id)->where('status', 'rejected')->count(),
            'all' => ProfessionalBookingRequest::where('admin_id', $admin->id)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $requests->items(),
            'counts' => $counts,
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * 5. GET /api/astrologer/booking-requests/{id}
     * Get single booking request details.
     */
    public function getBookingRequest(Request $request, $id)
    {
        $admin = $request->user();

        $booking = ProfessionalBookingRequest::where('admin_id', $admin->id)
            ->with(['service', 'jajman', 'appointment'])
            ->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking request not found or unauthorized.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    /**
     * 6. POST /api/astrologer/booking-requests/{id}/accept
     * Accept booking request, safely link/create Jajman, and create scheduled appointment.
     */
    public function acceptBookingRequest(Request $request, $id)
    {
        $admin = $request->user();

        $booking = ProfessionalBookingRequest::where('admin_id', $admin->id)->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking request not found or unauthorized.',
            ], 404);
        }

        if ($booking->status === 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'This booking request has already been accepted and confirmed.',
            ], 422);
        }

        $validated = $request->validate([
            'jajman_id' => 'nullable|integer',
            'create_new_jajman' => 'nullable|boolean',
            'jajman_name' => 'nullable|string|max:191',
            'jajman_phone' => 'nullable|string|max:32',
            'jajman_email' => 'nullable|email|max:191',
            'jajman_city' => 'nullable|string|max:100',
        ]);

        // Revalidate availability & conflict in real time
        $startUtc = $booking->requested_start_at;
        $endUtc = $booking->requested_end_at;

        $conflict = $this->conflictService->findConflictingAppointment($admin->id, $startUtc, $endUtc);
        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => "Cannot accept request: There is an overlapping confirmed appointment ({$conflict->title}) at this time.",
            ], 409);
        }

        return DB::transaction(function () use ($admin, $booking, $validated, $startUtc, $endUtc) {
            // 1. Resolve Jajman safely
            $jajman = null;

            if (!empty($validated['jajman_id'])) {
                // Link to selected existing Jajman
                $jajman = Jajman::whereHas('professionals', function ($q) use ($admin) {
                    $q->where('admin_id', $admin->id);
                })->find($validated['jajman_id']);
                if (!$jajman) {
                    throw new \Exception("Selected Jajman was not found or is unauthorized.");
                }
            } else {
                // Create new Jajman from request details
                $clientName = !empty($validated['jajman_name']) ? trim($validated['jajman_name']) : $booking->client_name;
                $clientPhone = !empty($validated['jajman_phone']) ? trim($validated['jajman_phone']) : $booking->client_phone;
                $clientEmail = !empty($validated['jajman_email']) ? trim($validated['jajman_email']) : $booking->client_email;
                $clientCity = !empty($validated['jajman_city']) ? trim($validated['jajman_city']) : null;

                $jajman = Jajman::create([
                    'name' => $clientName,
                    'phone' => $clientPhone,
                    'email' => $clientEmail,
                    'city' => $clientCity,
                ]);

                // Attach to professional
                $jajman->professionals()->attach($admin->id, [
                    'status' => 'active',
                    'source' => 'public_booking',
                ]);
            }

            // 2. Create the Appointment
            $appointment = Appointment::create([
                'admin_id' => $admin->id,
                'jajman_id' => $jajman->id,
                'service_id' => $booking->service_id,
                'title' => "{$booking->service_name_snapshot} Consultation",
                'description' => $booking->client_message,
                'appointment_type' => $booking->delivery_method,
                'booking_source' => 'client_requested',
                'status' => 'confirmed',
                'start_at' => $startUtc,
                'end_at' => $endUtc,
                'timezone' => $booking->timezone,
                'location' => $booking->location,
                'service_name_snapshot' => $booking->service_name_snapshot,
                'agreed_price_snapshot' => $booking->agreed_price_snapshot,
                'currency_snapshot' => $booking->currency_snapshot,
                'duration_minutes_snapshot' => $booking->duration_minutes_snapshot,
                'delivery_method_snapshot' => $booking->delivery_method,
            ]);

            // 3. Mark booking request as confirmed
            $booking->update([
                'status' => 'confirmed',
                'jajman_id' => $jajman->id,
                'appointment_id' => $appointment->id,
                'confirmed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking request accepted and confirmed as an appointment.',
                'data' => [
                    'booking' => $booking->fresh()->load(['service', 'jajman', 'appointment']),
                    'appointment' => $appointment,
                    'jajman' => $jajman,
                ],
            ]);
        });
    }

    /**
     * 7. POST /api/astrologer/booking-requests/{id}/reject
     * Reject booking request with a reason.
     */
    public function rejectBookingRequest(Request $request, $id)
    {
        $admin = $request->user();

        $booking = ProfessionalBookingRequest::where('admin_id', $admin->id)->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking request not found or unauthorized.',
            ], 404);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $booking->update([
            'status' => 'rejected',
            'rejection_reason' => trim($validated['rejection_reason']),
            'rejected_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Booking request rejected.',
            'data' => $booking->fresh(),
        ]);
    }

    /**
     * 8. POST /api/astrologer/booking-requests/{id}/propose-alternative
     * Propose an alternative date/time slot to the prospective client.
     */
    public function proposeAlternative(Request $request, $id)
    {
        $admin = $request->user();

        $booking = ProfessionalBookingRequest::where('admin_id', $admin->id)->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking request not found or unauthorized.',
            ], 404);
        }

        $validated = $request->validate([
            'proposed_date' => 'required|date_format:Y-m-d',
            'proposed_time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'notes' => 'nullable|string|max:500',
        ]);

        $tzName = $booking->timezone ?: 'Asia/Kolkata';
        try {
            $tz = new \DateTimeZone($tzName);
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('Asia/Kolkata');
        }

        $startLocal = Carbon::createFromFormat('Y-m-d H:i', "{$validated['proposed_date']} {$validated['proposed_time']}", $tz);
        $duration = $validated['duration_minutes'] ?? $booking->duration_minutes_snapshot ?? 30;
        $endLocal = $startLocal->copy()->addMinutes($duration);

        $startUtc = $startLocal->copy()->setTimezone('UTC');
        $endUtc = $endLocal->copy()->setTimezone('UTC');

        // Check conflicts
        $conflict = $this->conflictService->findConflictingAppointment($admin->id, $startUtc, $endUtc);
        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => "The proposed time has a conflicting confirmed appointment ({$conflict->title}).",
            ], 409);
        }

        $booking->update([
            'status' => 'alternative_proposed',
            'alternative_proposed_start_at' => $startUtc,
            'alternative_proposed_end_at' => $endUtc,
            'alternative_notes' => !empty($validated['notes']) ? trim($validated['notes']) : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alternative time slot proposed to client.',
            'data' => $booking->fresh(),
        ]);
    }

    /**
     * 9. POST /api/astrologer/services/{id}/public-visibility
     * Toggle public visibility of a service.
     */
    public function updateServiceVisibility(Request $request, $id)
    {
        $admin = $request->user();

        $service = ProfessionalService::where('admin_id', $admin->id)->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found or unauthorized.',
            ], 404);
        }

        $validated = $request->validate([
            'is_public' => 'required|boolean',
            'public_description' => 'nullable|string|max:2000',
        ]);

        $service->is_public = (bool) $validated['is_public'];
        if (array_key_exists('public_description', $validated)) {
            $service->public_description = $validated['public_description'];
        }
        $service->save();

        return response()->json([
            'success' => true,
            'message' => $service->is_public ? 'Service is now visible on your public profile.' : 'Service is now hidden from public booking.',
            'data' => $service,
        ]);
    }
}
