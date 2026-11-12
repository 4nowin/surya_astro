<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalBookingRequest;
use App\Models\ProfessionalPublicProfile;
use App\Models\ProfessionalService;
use App\Models\ProfessionalAvailability;
use App\Services\Appointment\AppointmentConflictService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicBookingWebController extends Controller
{
    protected AppointmentConflictService $conflictService;

    public function __construct(AppointmentConflictService $conflictService)
    {
        $this->conflictService = $conflictService;
    }

    /**
     * Show the public profile and booking page for a practitioner.
     */
    public function showProfile(string $slug)
    {
        $profile = ProfessionalPublicProfile::where('slug', $slug)->first();

        if (!$profile || !$profile->isLive()) {
            return response()->view('public_booking.unavailable', [
                'profile' => $profile,
            ], 404);
        }

        $services = ProfessionalService::where('admin_id', $profile->admin_id)
            ->publiclyVisible()
            ->orderBy('id', 'asc')
            ->get();

        return view('public_booking.profile', [
            'profile' => $profile,
            'services' => $services,
        ]);
    }

    /**
     * Submit booking request from web page form.
     */
    public function submitBooking(Request $request, string $slug)
    {
        $profile = ProfessionalPublicProfile::where('slug', $slug)->first();

        if (!$profile || !$profile->isLive() || !$profile->is_accepting_bookings) {
            return back()->with('error', 'Practitioner is not currently accepting booking requests.')->withInput();
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

        $service = ProfessionalService::where('admin_id', $adminId)
            ->publiclyVisible()
            ->find($validated['service_id']);

        if (!$service) {
            return back()->with('error', 'The selected service is invalid or unavailable.')->withInput();
        }

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
            return back()->with('error', 'Cannot request an appointment slot in the past.')->withInput();
        }

        $duration = $service->duration_minutes > 0 ? $service->duration_minutes : 30;
        $endLocal = $startLocal->copy()->addMinutes($duration);

        $startUtc = $startLocal->copy()->setTimezone('UTC');
        $endUtc = $endLocal->copy()->setTimezone('UTC');

        // Check availability and conflicts
        [$isAvailable, $availReason] = $this->conflictService->checkPractitionerAvailability(
            $adminId,
            $startUtc,
            $endUtc,
            $tzName
        );

        if (!$isAvailable) {
            return back()->with('error', $availReason ?: 'Requested slot is outside practitioner working hours.')->withInput();
        }

        $conflict = $this->conflictService->findConflictingAppointment($adminId, $startUtc, $endUtc);
        if ($conflict) {
            return back()->with('error', 'The requested slot is already booked. Please choose another time.')->withInput();
        }

        $bookingRef = ProfessionalBookingRequest::generateBookingReference();
        $accessToken = ProfessionalBookingRequest::generateAccessToken();

        $booking = ProfessionalBookingRequest::create([
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

        return redirect()->route('public.booking.confirmation', [
            'slug' => $slug,
            'reference' => $bookingRef,
            'token' => $accessToken,
        ])->with('success', 'Your appointment request has been submitted successfully.');
    }

    /**
     * Show booking confirmation and status.
     */
    public function bookingConfirmation(string $slug, string $reference, Request $request)
    {
        $token = $request->query('token');

        $booking = ProfessionalBookingRequest::where('booking_reference', $reference)
            ->when($token, function ($q) use ($token) {
                $q->where('access_token', $token);
            })
            ->with(['admin.publicProfile'])
            ->first();

        if (!$booking) {
            return abort(404, 'Booking reference not found.');
        }

        return view('public_booking.confirmation', [
            'booking' => $booking,
            'profile' => $booking->admin?->publicProfile,
        ]);
    }
}
