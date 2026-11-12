<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalBookingRequest;
use App\Models\ProfessionalPublicProfile;
use App\Models\ProfessionalService;
use App\Models\ProfessionalAvailability;
use App\Models\ProfessionalAvailabilityException;
use App\Services\Appointment\AppointmentConflictService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PublicBookingApiController extends Controller
{
    protected AppointmentConflictService $conflictService;

    public function __construct(AppointmentConflictService $conflictService)
    {
        $this->conflictService = $conflictService;
    }

    /**
     * 1. GET /api/public/practitioners/{slug}
     * Retrieve public profile details by slug.
     */
    public function getProfile(string $slug)
    {
        $profile = ProfessionalPublicProfile::where('slug', $slug)
            ->with(['admin:id,name,email'])
            ->first();

        if (!$profile || !$profile->isLive()) {
            return response()->json([
                'success' => false,
                'message' => 'Practitioner public profile is not published or currently unavailable.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $profile->toPublicArray(),
        ]);
    }

    /**
     * 2. GET /api/public/practitioners/{slug}/services
     * Retrieve publicly visible services offered by practitioner.
     */
    public function getServices(string $slug)
    {
        $profile = ProfessionalPublicProfile::where('slug', $slug)->first();

        if (!$profile || !$profile->isLive()) {
            return response()->json([
                'success' => false,
                'message' => 'Practitioner public profile is not available.',
            ], 404);
        }

        $services = ProfessionalService::where('admin_id', $profile->admin_id)
            ->publiclyVisible()
            ->orderBy('id', 'asc')
            ->get();

        $data = $services->map(fn($s) => $s->toPublicArray())->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 3. GET /api/public/practitioners/{slug}/availability
     * Calculate and return available booking time slots for a given date.
     */
    public function getAvailability(Request $request, string $slug)
    {
        $profile = ProfessionalPublicProfile::where('slug', $slug)->first();

        if (!$profile || !$profile->isLive()) {
            return response()->json([
                'success' => false,
                'message' => 'Practitioner public profile is not available.',
            ], 404);
        }

        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'service_id' => 'nullable|integer',
        ]);

        $dateStr = $validated['date'];
        $adminId = $profile->admin_id;

        // Determine duration in minutes
        $durationMinutes = 30;
        if (!empty($validated['service_id'])) {
            $service = ProfessionalService::where('admin_id', $adminId)
                ->publiclyVisible()
                ->find($validated['service_id']);
            if ($service && $service->duration_minutes > 0) {
                $durationMinutes = $service->duration_minutes;
            }
        }

        // Determine timezone
        $firstAvailability = ProfessionalAvailability::where('admin_id', $adminId)->first();
        $tzName = $firstAvailability?->timezone ?: 'Asia/Kolkata';

        try {
            $tz = new \DateTimeZone($tzName);
        } catch (\Exception $e) {
            $tzName = 'Asia/Kolkata';
            $tz = new \DateTimeZone($tzName);
        }

        $localDate = Carbon::createFromFormat('Y-m-d', $dateStr, $tz)->startOfDay();

        // Check if date is in past
        if ($localDate->isPast() && !$localDate->isToday()) {
            return response()->json([
                'success' => true,
                'date' => $dateStr,
                'timezone' => $tzName,
                'slots' => [],
                'reason' => 'Date is in the past.',
            ]);
        }

        // Check date exceptions (leave or holiday)
        $exception = ProfessionalAvailabilityException::where('admin_id', $adminId)
            ->where('date', $dateStr)
            ->first();

        if ($exception && in_array($exception->type, ['leave', 'holiday'])) {
            return response()->json([
                'success' => true,
                'date' => $dateStr,
                'timezone' => $tzName,
                'slots' => [],
                'reason' => $exception->reason ?: 'Practitioner is not available on this date.',
            ]);
        }

        // Determine working window slots
        $workingIntervals = [];
        if ($exception && $exception->type === 'custom_hours' && !empty($exception->slots)) {
            $workingIntervals = $exception->slots;
        } else {
            $dayOfWeek = (int) $localDate->dayOfWeek;
            $weekly = ProfessionalAvailability::where('admin_id', $adminId)
                ->where('day_of_week', $dayOfWeek)
                ->first();

            if ($weekly && $weekly->is_enabled && !empty($weekly->slots)) {
                $workingIntervals = $weekly->slots;
            } elseif (!$weekly) {
                // Default 09:00-13:00 and 14:00-18:00 on weekdays/Saturdays
                if ($dayOfWeek >= 1 && $dayOfWeek <= 6) {
                    $workingIntervals = [
                        ['start' => '09:00', 'end' => '13:00'],
                        ['start' => '14:00', 'end' => '18:00'],
                    ];
                }
            }
        }

        if (empty($workingIntervals)) {
            return response()->json([
                'success' => true,
                'date' => $dateStr,
                'timezone' => $tzName,
                'slots' => [],
                'reason' => 'No scheduled hours for this day.',
            ]);
        }

        // Generate discrete slots and check conflicts
        $availableSlots = [];
        $nowTz = Carbon::now($tz);

        foreach ($workingIntervals as $interval) {
            $intStart = Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$interval['start']}", $tz);
            $intEnd = Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$interval['end']}", $tz);

            $currentSlotStart = $intStart->copy();
            while ($currentSlotStart->copy()->addMinutes($durationMinutes)->lte($intEnd)) {
                $currentSlotEnd = $currentSlotStart->copy()->addMinutes($durationMinutes);

                // If today, skip times that have already passed
                if ($currentSlotStart->gt($nowTz)) {
                    $startUtc = $currentSlotStart->copy()->setTimezone('UTC');
                    $endUtc = $currentSlotEnd->copy()->setTimezone('UTC');

                    $conflict = $this->conflictService->findConflictingAppointment($adminId, $startUtc, $endUtc);

                    if (!$conflict) {
                        $availableSlots[] = [
                            'start' => $currentSlotStart->format('H:i'),
                            'end' => $currentSlotEnd->format('H:i'),
                            'duration_minutes' => $durationMinutes,
                        ];
                    }
                }

                // Increment in 30-min steps or duration steps
                $step = min($durationMinutes, 30);
                $currentSlotStart->addMinutes($step);
            }
        }

        return response()->json([
            'success' => true,
            'date' => $dateStr,
            'timezone' => $tzName,
            'duration_minutes' => $durationMinutes,
            'slots' => $availableSlots,
        ]);
    }

    /**
     * 4. POST /api/public/practitioners/{slug}/booking-requests
     * Submit an appointment booking request from a prospective client.
     */
    public function submitBookingRequest(Request $request, string $slug)
    {
        $profile = ProfessionalPublicProfile::where('slug', $slug)->first();

        if (!$profile || !$profile->isLive()) {
            return response()->json([
                'success' => false,
                'message' => 'Practitioner public profile is not available.',
            ], 404);
        }

        if (!$profile->is_accepting_bookings) {
            return response()->json([
                'success' => false,
                'message' => 'Practitioner is currently not accepting new booking requests.',
            ], 422);
        }

        $emailRule = $profile->is_email_mandatory ? 'required|email|max:191' : 'nullable|email|max:191';

        $validated = $request->validate([
            'client_name' => 'required|string|max:191',
            'client_phone' => 'required|string|max:32',
            'client_email' => $emailRule,
            'service_id' => 'required|integer',
            'requested_date' => 'required|date_format:Y-m-d',
            'requested_time' => 'required|date_format:H:i',
            'delivery_method' => 'required|string|in:online,in_person,home_visit,phone_call',
            'location' => 'nullable|string|max:255',
            'client_message' => 'nullable|string|max:1000',
        ]);

        $adminId = $profile->admin_id;

        // 1. Validate service
        $service = ProfessionalService::where('admin_id', $adminId)
            ->publiclyVisible()
            ->find($validated['service_id']);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Selected service is invalid, inactive, or not available for public booking.',
            ], 422);
        }

        // Validate delivery method
        $deliveryMethods = $service->delivery_methods ?? ['online'];
        if (!in_array($validated['delivery_method'], $deliveryMethods)) {
            return response()->json([
                'success' => false,
                'message' => "The selected delivery method is not supported for this service.",
            ], 422);
        }

        // 2. Compute timestamps
        $firstAvailability = ProfessionalAvailability::where('admin_id', $adminId)->first();
        $tzName = $firstAvailability?->timezone ?: 'Asia/Kolkata';

        try {
            $tz = new \DateTimeZone($tzName);
        } catch (\Exception $e) {
            $tzName = 'Asia/Kolkata';
            $tz = new \DateTimeZone($tzName);
        }

        $startLocal = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$validated['requested_date']} {$validated['requested_time']}",
            $tz
        );

        if ($startLocal->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot request an appointment in the past.',
            ], 422);
        }

        $duration = $service->duration_minutes > 0 ? $service->duration_minutes : 30;
        $endLocal = $startLocal->copy()->addMinutes($duration);

        $startUtc = $startLocal->copy()->setTimezone('UTC');
        $endUtc = $endLocal->copy()->setTimezone('UTC');

        // 3. Backend conflict & availability validation
        [$isAvailable, $availReason] = $this->conflictService->checkPractitionerAvailability(
            $adminId,
            $startUtc,
            $endUtc,
            $tzName
        );

        if (!$isAvailable) {
            return response()->json([
                'success' => false,
                'message' => $availReason ?: 'Requested slot is outside practitioner working hours.',
            ], 422);
        }

        $conflict = $this->conflictService->findConflictingAppointment($adminId, $startUtc, $endUtc);
        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'The requested time slot has already been booked. Please select another time.',
            ], 409);
        }

        // 4. Create Booking Request with secure reference and access token
        $bookingRef = ProfessionalBookingRequest::generateBookingReference();
        $accessToken = ProfessionalBookingRequest::generateAccessToken();

        $bookingRequest = ProfessionalBookingRequest::create([
            'admin_id' => $adminId,
            'booking_reference' => $bookingRef,
            'access_token' => $accessToken,
            'service_id' => $service->id,
            'service_name_snapshot' => $service->name,
            'agreed_price_snapshot' => $service->price,
            'currency_snapshot' => $service->currency ?: 'INR',
            'duration_minutes_snapshot' => $duration,
            'delivery_method' => $validated['delivery_method'],
            'requested_start_at' => $startUtc,
            'requested_end_at' => $endUtc,
            'timezone' => $tzName,
            'client_name' => trim($validated['client_name']),
            'client_phone' => trim($validated['client_phone']),
            'client_email' => !empty($validated['client_email']) ? trim($validated['client_email']) : null,
            'location' => !empty($validated['location']) ? trim($validated['location']) : null,
            'client_message' => !empty($validated['client_message']) ? trim($validated['client_message']) : null,
            'status' => 'pending',
        ]);

        // 5. Send FCM Notification to Practitioner if token registered
        $this->notifyPractitionerOfNewRequest($profile->admin, $bookingRequest);

        return response()->json([
            'success' => true,
            'message' => 'Your appointment request has been submitted successfully.',
            'data' => [
                'booking_reference' => $bookingRef,
                'access_token' => $accessToken,
                'status' => 'pending',
                'service_name' => $service->name,
                'practitioner_name' => $profile->display_name,
                'requested_start_at' => $startUtc->toIso8601String(),
                'requested_end_at' => $endUtc->toIso8601String(),
                'duration_minutes' => $duration,
                'timezone' => $tzName,
                'delivery_method' => $validated['delivery_method'],
            ],
        ], 201);
    }

    /**
     * 5. GET /api/public/booking-requests/{reference}
     * Check booking request status using reference and token.
     */
    public function getBookingStatus(Request $request, string $reference)
    {
        $token = $request->query('token') ?: $request->header('X-Booking-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Access token is required to inspect booking request status.',
            ], 401);
        }

        $booking = ProfessionalBookingRequest::where('booking_reference', $reference)
            ->where('access_token', $token)
            ->with(['admin.publicProfile'])
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking request not found or invalid access token.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $booking->toPublicArray(),
        ]);
    }

    /**
     * 6. POST /api/public/booking-requests/{reference}/cancel
     * Prospective client cancels their pending booking request.
     */
    public function clientCancel(Request $request, string $reference)
    {
        $token = $request->input('token') ?: $request->query('token') ?: $request->header('X-Booking-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Access token is required.',
            ], 401);
        }

        $booking = ProfessionalBookingRequest::where('booking_reference', $reference)
            ->where('access_token', $token)
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking request not found or invalid access token.',
            ], 404);
        }

        if ($booking->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Cannot cancel this request because it is already {$booking->status}.",
            ], 422);
        }

        $booking->status = 'cancelled';
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Booking request has been cancelled.',
            'data' => $booking->toPublicArray(),
        ]);
    }

    /**
     * Send FCM Notification to Practitioner
     */
    protected function notifyPractitionerOfNewRequest($admin, ProfessionalBookingRequest $booking): void
    {
        if (!$admin || empty($admin->fcm_token)) {
            return;
        }

        try {
            $firebaseServiceAccountPath = storage_path('app/firebase/firebase_credentials.json');
            if (!file_exists($firebaseServiceAccountPath)) {
                return;
            }

            $factory = (new Factory)->withServiceAccount($firebaseServiceAccountPath);
            $messaging = $factory->createMessaging();

            $dateFormatted = $booking->requested_start_at ? $booking->requested_start_at->setTimezone($booking->timezone)->format('d M, h:i A') : '';
            $title = 'New Appointment Request';
            $body = "{$booking->client_name} requested {$booking->service_name_snapshot} for {$dateFormatted}.";

            $message = CloudMessage::withTarget('token', $admin->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData([
                    'type' => 'booking_request',
                    'booking_id' => (string) $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'client_name' => $booking->client_name,
                ]);

            $messaging->send($message);
        } catch (\Throwable $e) {
            // Fail safely: notification error must not disrupt database booking record
            \Log::warning("FCM notification failed for booking request: " . $e->getMessage());
        }
    }
}
