<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Jajman;
use App\Services\Appointment\AppointmentConflictService;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AppointmentApiController extends Controller
{
    protected AppointmentConflictService $conflictService;

    public function __construct(AppointmentConflictService $conflictService)
    {
        $this->conflictService = $conflictService;
    }

    /**
     * 1. GET /api/astrologer/appointments
     * List appointments with filters, search, and pagination.
     */
    public function index(Request $request)
    {
        $admin = $request->user();

        $query = Appointment::where('admin_id', $admin->id)
            ->with([
                'jajman:id,name,phone,email,gender',
                'consultation:id,type,fee_charged,duration_minutes',
            ]);

        // Status filter
        if ($status = $request->input('status')) {
            $statuses = array_filter(array_map('trim', explode(',', $status)));
            if (!empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        // Type filter
        if ($type = $request->input('type')) {
            $query->where('appointment_type', $type);
        }

        // Jajman filter
        if ($jajmanId = $request->input('jajman_id')) {
            $query->where('jajman_id', $jajmanId);
        }

        // Date range filters (UTC)
        if ($from = $request->input('from')) {
            try {
                $query->where('end_at', '>=', Carbon::parse($from)->utc());
            } catch (\Exception $e) {}
        }
        if ($to = $request->input('to')) {
            try {
                $query->where('start_at', '<=', Carbon::parse($to)->utc());
            } catch (\Exception $e) {}
        }

        // Search in title, description, or client name
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('jajman', function ($jq) use ($search) {
                      $jq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $appointments = $query->orderBy('start_at', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $appointments->items(),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'total' => $appointments->total(),
                'per_page' => $appointments->perPage(),
            ],
        ]);
    }

    /**
     * 2. POST /api/astrologer/appointments
     * Create a new appointment with conflict detection and availability validation.
     */
    public function store(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:191',
            'description' => 'nullable|string',
            'appointment_type' => ['required', Rule::in(['online', 'in_person', 'phone_call', 'puja', 'vastu_visit', 'other'])],
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'timezone' => 'nullable|string|max:64',
            'jajman_id' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|string|max:255',
            'override_availability' => 'nullable|boolean',
        ]);

        $tzName = $this->validateTimezone($validated['timezone'] ?? 'Asia/Kolkata');
        $startUtc = Carbon::parse($validated['start_at'])->utc();
        $endUtc = Carbon::parse($validated['end_at'])->utc();

        if ($endUtc->lte($startUtc)) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment end time must be after the start time.',
            ], 422);
        }

        // Validate Jajman ownership if provided
        if (!empty($validated['jajman_id'])) {
            $isAuthorized = DB::table('professional_jajmans')
                ->where('admin_id', $admin->id)
                ->where('jajman_id', $validated['jajman_id'])
                ->exists();

            if (!$isAuthorized) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected Jajman does not belong to your workspace.',
                ], 404);
            }
        }

        $overrideAvailability = (bool) ($validated['override_availability'] ?? false);

        return DB::transaction(function () use ($admin, $validated, $startUtc, $endUtc, $tzName, $overrideAvailability) {
            // Check overlapping appointment conflict
            $conflict = $this->conflictService->findConflictingAppointment($admin->id, $startUtc, $endUtc);
            if ($conflict) {
                $conflictStart = $conflict->start_at->copy()->setTimezone($tzName)->format('d M, h:i A');
                $conflictEnd = $conflict->end_at->copy()->setTimezone($tzName)->format('h:i A');

                return response()->json([
                    'success' => false,
                    'conflict' => true,
                    'message' => "This appointment conflicts with existing booking '{$conflict->title}' scheduled at {$conflictStart} - {$conflictEnd}.",
                    'conflicting_appointment_id' => $conflict->id,
                ], 422);
            }

            // Check working hours and leaves unless explicitly overridden
            if (!$overrideAvailability) {
                [$isAvailable, $unavailReason] = $this->conflictService->checkPractitionerAvailability(
                    $admin->id,
                    $startUtc,
                    $endUtc,
                    $tzName
                );

                if (!$isAvailable) {
                    return response()->json([
                        'success' => false,
                        'availability_conflict' => true,
                        'message' => $unavailReason,
                        'can_override' => true,
                    ], 422);
                }
            }

            $appointment = Appointment::create([
                'admin_id' => $admin->id,
                'jajman_id' => $validated['jajman_id'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'appointment_type' => $validated['appointment_type'],
                'booking_source' => 'professional_created',
                'status' => 'confirmed',
                'start_at' => $startUtc,
                'end_at' => $endUtc,
                'timezone' => $tzName,
                'location' => $validated['location'] ?? null,
                'meeting_link' => $validated['meeting_link'] ?? null,
            ]);

            $this->sendFCMNotification(
                $admin,
                "New Appointment Scheduled: {$appointment->title}",
                "Scheduled for " . $startUtc->copy()->setTimezone($tzName)->format('d M, h:i A'),
                ['appointment_id' => (string) $appointment->id, 'type' => 'appointment_created']
            );

            return response()->json([
                'success' => true,
                'message' => 'Appointment scheduled successfully.',
                'data' => $appointment->load('jajman:id,name,phone,email'),
            ], 201);
        });
    }

    /**
     * 3. GET /api/astrologer/appointments/{id}
     * Retrieve single appointment details.
     */
    public function show(Request $request, $id)
    {
        $admin = $request->user();

        $appointment = Appointment::where('admin_id', $admin->id)
            ->with([
                'jajman',
                'consultation',
            ])
            ->find($id);

        if (!$appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found or unauthorized.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $appointment,
        ]);
    }

    /**
     * 4. PUT /api/astrologer/appointments/{id}
     * Update appointment details with optional time change conflict verification.
     */
    public function update(Request $request, $id)
    {
        $admin = $request->user();

        $appointment = Appointment::where('admin_id', $admin->id)->find($id);
        if (!$appointment) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }

        if (in_array($appointment->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot update an appointment that is already {$appointment->status}.",
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:191',
            'description' => 'nullable|string',
            'appointment_type' => ['sometimes', 'required', Rule::in(['online', 'in_person', 'phone_call', 'puja', 'vastu_visit', 'other'])],
            'start_at' => 'sometimes|required|date',
            'end_at' => 'sometimes|required|date|after:start_at',
            'timezone' => 'nullable|string|max:64',
            'jajman_id' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|string|max:255',
            'override_availability' => 'nullable|boolean',
        ]);

        $tzName = $this->validateTimezone($validated['timezone'] ?? $appointment->timezone);
        $startUtc = isset($validated['start_at']) ? Carbon::parse($validated['start_at'])->utc() : $appointment->start_at;
        $endUtc = isset($validated['end_at']) ? Carbon::parse($validated['end_at'])->utc() : $appointment->end_at;

        if ($endUtc->lte($startUtc)) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment end time must be after the start time.',
            ], 422);
        }

        // Validate Jajman ownership if provided
        if (!empty($validated['jajman_id'])) {
            $isAuthorized = DB::table('professional_jajmans')
                ->where('admin_id', $admin->id)
                ->where('jajman_id', $validated['jajman_id'])
                ->exists();

            if (!$isAuthorized) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected Jajman does not belong to your workspace.',
                ], 404);
            }
        }

        $overrideAvailability = (bool) ($validated['override_availability'] ?? false);

        return DB::transaction(function () use ($appointment, $validated, $startUtc, $endUtc, $tzName, $overrideAvailability) {
            // Check conflict if times changed
            if ($startUtc->ne($appointment->start_at) || $endUtc->ne($appointment->end_at)) {
                $conflict = $this->conflictService->findConflictingAppointment($appointment->admin_id, $startUtc, $endUtc, $appointment->id);
                if ($conflict) {
                    $conflictStart = $conflict->start_at->copy()->setTimezone($tzName)->format('d M, h:i A');
                    $conflictEnd = $conflict->end_at->copy()->setTimezone($tzName)->format('h:i A');

                    return response()->json([
                        'success' => false,
                        'conflict' => true,
                        'message' => "Time slot conflicts with '{$conflict->title}' ({$conflictStart} - {$conflictEnd}).",
                    ], 422);
                }

                if (!$overrideAvailability) {
                    [$isAvailable, $unavailReason] = $this->conflictService->checkPractitionerAvailability(
                        $appointment->admin_id,
                        $startUtc,
                        $endUtc,
                        $tzName
                    );

                    if (!$isAvailable) {
                        return response()->json([
                            'success' => false,
                            'availability_conflict' => true,
                            'message' => $unavailReason,
                            'can_override' => true,
                        ], 422);
                    }
                }
            }

            $appointment->update([
                'title' => $validated['title'] ?? $appointment->title,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $appointment->description,
                'appointment_type' => $validated['appointment_type'] ?? $appointment->appointment_type,
                'start_at' => $startUtc,
                'end_at' => $endUtc,
                'timezone' => $tzName,
                'jajman_id' => array_key_exists('jajman_id', $validated) ? $validated['jajman_id'] : $appointment->jajman_id,
                'location' => array_key_exists('location', $validated) ? $validated['location'] : $appointment->location,
                'meeting_link' => array_key_exists('meeting_link', $validated) ? $validated['meeting_link'] : $appointment->meeting_link,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Appointment updated successfully.',
                'data' => $appointment->fresh()->load('jajman'),
            ]);
        });
    }

    /**
     * 5. POST /api/astrologer/appointments/{id}/reschedule
     * Reschedule appointment to a new date/time slot.
     */
    public function reschedule(Request $request, $id)
    {
        $admin = $request->user();

        $appointment = Appointment::where('admin_id', $admin->id)->find($id);
        if (!$appointment) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }

        if (in_array($appointment->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot reschedule a {$appointment->status} appointment.",
            ], 422);
        }

        $validated = $request->validate([
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'timezone' => 'nullable|string|max:64',
            'override_availability' => 'nullable|boolean',
            'reason' => 'nullable|string',
        ]);

        $tzName = $this->validateTimezone($validated['timezone'] ?? $appointment->timezone);
        $startUtc = Carbon::parse($validated['start_at'])->utc();
        $endUtc = Carbon::parse($validated['end_at'])->utc();
        $overrideAvailability = (bool) ($validated['override_availability'] ?? false);

        return DB::transaction(function () use ($admin, $appointment, $startUtc, $endUtc, $tzName, $overrideAvailability, $validated) {
            $conflict = $this->conflictService->findConflictingAppointment($admin->id, $startUtc, $endUtc, $appointment->id);
            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'conflict' => true,
                    'message' => "Reschedule slot conflicts with '{$conflict->title}'.",
                ], 422);
            }

            if (!$overrideAvailability) {
                [$isAvailable, $unavailReason] = $this->conflictService->checkPractitionerAvailability(
                    $admin->id,
                    $startUtc,
                    $endUtc,
                    $tzName
                );

                if (!$isAvailable) {
                    return response()->json([
                        'success' => false,
                        'availability_conflict' => true,
                        'message' => $unavailReason,
                        'can_override' => true,
                    ], 422);
                }
            }

            $appointment->update([
                'start_at' => $startUtc,
                'end_at' => $endUtc,
                'timezone' => $tzName,
                'status' => 'confirmed',
            ]);

            $this->sendFCMNotification(
                $admin,
                "Appointment Rescheduled: {$appointment->title}",
                "Moved to " . $startUtc->copy()->setTimezone($tzName)->format('d M, h:i A'),
                ['appointment_id' => (string) $appointment->id, 'type' => 'appointment_rescheduled']
            );

            return response()->json([
                'success' => true,
                'message' => 'Appointment rescheduled successfully.',
                'data' => $appointment->fresh()->load('jajman'),
            ]);
        });
    }

    /**
     * 6. POST /api/astrologer/appointments/{id}/cancel
     * Cancel appointment with optional reason.
     */
    public function cancel(Request $request, $id)
    {
        $admin = $request->user();

        $appointment = Appointment::where('admin_id', $admin->id)->find($id);
        if (!$appointment) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }

        if ($appointment->status === 'cancelled') {
            return response()->json(['success' => true, 'message' => 'Appointment already cancelled.', 'data' => $appointment]);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['cancellation_reason'] ?? null,
        ]);

        $this->sendFCMNotification(
            $admin,
            "Appointment Cancelled: {$appointment->title}",
            $validated['cancellation_reason'] ?? "Appointment has been cancelled.",
            ['appointment_id' => (string) $appointment->id, 'type' => 'appointment_cancelled']
        );

        return response()->json([
            'success' => true,
            'message' => 'Appointment cancelled successfully.',
            'data' => $appointment->fresh(),
        ]);
    }

    /**
     * 7. POST /api/astrologer/appointments/{id}/complete
     * Mark completed with option to create a linked Consultation record.
     */
    public function complete(Request $request, $id)
    {
        $admin = $request->user();

        $appointment = Appointment::where('admin_id', $admin->id)->with('jajman')->find($id);
        if (!$appointment) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }

        $validated = $request->validate([
            'create_consultation' => 'nullable|boolean',
            'fee_charged' => 'nullable|numeric|min:0',
            'summary' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($admin, $appointment, $validated) {
            $appointment->status = 'completed';

            // Check if consultation should be generated
            if (!empty($validated['create_consultation']) && !$appointment->consultation_id) {
                $clientName = $appointment->jajman ? $appointment->jajman->name : $appointment->title;
                $duration = max(15, (int) $appointment->start_at->diffInMinutes($appointment->end_at));

                // Map appointment type to consultation type
                $typeMap = [
                    'online' => 'online_chat',
                    'in_person' => 'in_person',
                    'phone_call' => 'phone_call',
                    'puja' => 'ritual_puja',
                    'vastu_visit' => 'vastu_visit',
                    'other' => 'in_person',
                ];
                $consultType = $typeMap[$appointment->appointment_type] ?? 'in_person';

                $consultation = Consultation::create([
                    'admin_id' => $admin->id,
                    'jajman_id' => $appointment->jajman_id,
                    'client_name' => $clientName,
                    'type' => $consultType,
                    'status' => 'completed',
                    'consultation_date' => $appointment->start_at,
                    'duration_minutes' => $duration,
                    'fee_charged' => $validated['fee_charged'] ?? null,
                    'summary' => $validated['summary'] ?? $appointment->description,
                ]);

                $appointment->consultation_id = $consultation->id;
            }

            $appointment->save();

            return response()->json([
                'success' => true,
                'message' => 'Appointment marked as completed.',
                'data' => $appointment->fresh()->load(['jajman', 'consultation']),
            ]);
        });
    }

    /**
     * 8. POST /api/astrologer/appointments/{id}/no-show
     */
    public function noShow(Request $request, $id)
    {
        $admin = $request->user();

        $appointment = Appointment::where('admin_id', $admin->id)->find($id);
        if (!$appointment) {
            return response()->json(['success' => false, 'message' => 'Appointment not found.'], 404);
        }

        $appointment->update(['status' => 'no_show']);

        return response()->json([
            'success' => true,
            'message' => 'Appointment marked as no-show.',
            'data' => $appointment->fresh(),
        ]);
    }

    /**
     * 9. GET /api/astrologer/calendar
     * Returns appointments and summary counts grouped by date for month/week views.
     */
    public function calendar(Request $request)
    {
        $admin = $request->user();

        $tzName = $this->validateTimezone($request->input('timezone', 'Asia/Kolkata'));
        $from = $request->input('from', Carbon::now($tzName)->startOfMonth()->toIso8601String());
        $to = $request->input('to', Carbon::now($tzName)->endOfMonth()->toIso8601String());

        $startUtc = Carbon::parse($from)->utc();
        $endUtc = Carbon::parse($to)->utc();

        $appointments = Appointment::where('admin_id', $admin->id)
            ->where('end_at', '>=', $startUtc)
            ->where('start_at', '<=', $endUtc)
            ->with('jajman:id,name,phone')
            ->orderBy('start_at', 'asc')
            ->get();

        // Group by local date string (YYYY-MM-DD)
        $grouped = [];
        foreach ($appointments as $apt) {
            $localDate = $apt->start_at->copy()->setTimezone($tzName)->toDateString();
            if (!isset($grouped[$localDate])) {
                $grouped[$localDate] = [
                    'date' => $localDate,
                    'count' => 0,
                    'appointments' => [],
                ];
            }
            $grouped[$localDate]['count']++;
            $grouped[$localDate]['appointments'][] = [
                'id' => $apt->id,
                'title' => $apt->title,
                'appointment_type' => $apt->appointment_type,
                'status' => $apt->status,
                'start_at' => $apt->start_at->toIso8601String(),
                'end_at' => $apt->end_at->toIso8601String(),
                'local_start_time' => $apt->start_at->copy()->setTimezone($tzName)->format('h:i A'),
                'local_end_time' => $apt->end_at->copy()->setTimezone($tzName)->format('h:i A'),
                'jajman_name' => $apt->jajman?->name,
                'jajman_id' => $apt->jajman_id,
            ];
        }

        return response()->json([
            'success' => true,
            'timezone' => $tzName,
            'from' => $startUtc->toIso8601String(),
            'to' => $endUtc->toIso8601String(),
            'days' => array_values($grouped),
        ]);
    }

    /**
     * Validate and normalize timezone string.
     */
    protected function validateTimezone(string $timezone): string
    {
        try {
            new DateTimeZone($timezone);
            return $timezone;
        } catch (\Exception $e) {
            return 'Asia/Kolkata';
        }
    }

    /**
     * Helper to safely dispatch FCM notifications without failing the request.
     */
    protected function sendFCMNotification($admin, string $title, string $body, array $data = []): void
    {
        if (empty($admin->fcm_token)) return;

        try {
            $fcm = app(FirebaseNotificationService::class);
            $fcm->sendToToken($admin->fcm_token, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::warning("Appointment FCM dispatch failed: " . $e->getMessage());
        }
    }
}
