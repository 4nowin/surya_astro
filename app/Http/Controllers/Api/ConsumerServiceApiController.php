<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\BookAppointmentRequest;
use App\Http\Requests\Services\CancelAppointmentRequest;
use App\Http\Requests\Services\DisputeOrderRequest;
use App\Http\Requests\Services\OrderDeliverableRequest;
use App\Http\Requests\Services\ReportNoShowRequest;
use App\Http\Resources\Services\AppointmentBookingResource;
use App\Http\Resources\Services\ProfessionalServiceOrderResource;
use App\Models\Appointment;
use App\Models\Astrologer;
use App\Models\ProfessionalService;
use App\Models\ProfessionalServiceOrder;
use App\Services\Services\ServiceOrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ConsumerServiceApiController extends Controller
{
    public function __construct(
        protected ServiceOrderService $serviceOrderService
    ) {}

    /**
     * GET /api/{lang?}/services
     * Returns publicly available services across all astrologers with transparent pricing.
     */
    public function getAllServices(Request $request): JsonResponse
    {
        $query = ProfessionalService::publiclyVisible()
            ->with(['professional.astrologer']);

        if ($cat = $request->query('category')) {
            $query->where('category', $cat);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('public_description', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($astrologerId = $request->query('astrologer_id')) {
            $astro = Astrologer::where('id', $astrologerId)->orWhere('user_id', $astrologerId)->first();
            if ($astro) {
                $adminId = $astro->admin_id ?? $astro->user_id;
                $query->where('admin_id', $adminId);
            }
        }

        $services = $query->orderBy('id', 'desc')->paginate(20);

        $data = collect($services->items())->map(fn (ProfessionalService $service) => $service->toPublicArray());

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ],
        ]);
    }

    /**
     * GET /api/{lang?}/services/{id}
     * Returns single service details with transparent pricing and astrologer profile.
     */
    public function getServiceDetail(Request $request, $id): JsonResponse
    {
        $service = ProfessionalService::publiclyVisible()
            ->with(['professional.astrologer'])
            ->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found or is currently unavailable.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $service->toPublicArray(),
        ]);
    }

    /**
     * GET /api/{lang?}/astrologers/{id}/services
     * Returns publicly available services configured by the astrologer with transparent pricing.
     */
    public function getAstrologerServices(Request $request, $id): JsonResponse
    {
        $astrologer = Astrologer::where('id', $id)
            ->orWhere('user_id', $id)
            ->first();

        if (!$astrologer) {
            return response()->json([
                'success' => false,
                'message' => 'Astrologer not found.',
            ], 404);
        }

        $adminId = $astrologer->admin_id ?? $astrologer->user_id;

        $services = ProfessionalService::where('admin_id', $adminId)
            ->publiclyVisible()
            ->with(['professional.astrologer'])
            ->orderBy('id', 'asc')
            ->get();

        $data = $services->map(fn (ProfessionalService $service) => $service->toPublicArray());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /api/{lang?}/astrologers/{id}/availability
     * Get available booking slots for a selected date.
     */
    public function getAstrologerAvailability(Request $request, $id): JsonResponse
    {
        $astrologer = Astrologer::where('id', $id)
            ->orWhere('user_id', $id)
            ->first();

        if (!$astrologer) {
            return response()->json([
                'success' => false,
                'message' => 'Astrologer not found.',
            ], 404);
        }

        $adminId = $astrologer->admin_id ?? $astrologer->user_id;
        $dateStr = $request->query('date', \Carbon\Carbon::today()->toDateString());

        try {
            $date = \Carbon\Carbon::parse($dateStr);
        } catch (\Exception $e) {
            $date = \Carbon\Carbon::today();
            $dateStr = $date->toDateString();
        }

        if ($date->isPast() && !$date->isToday()) {
            return response()->json([
                'success' => true,
                'date' => $dateStr,
                'slots' => [],
                'message' => 'Selected date is in the past.',
            ]);
        }

        // Check if there is an exception (leave or holiday)
        $exception = \App\Models\ProfessionalAvailabilityException::where('admin_id', $adminId)
            ->where('date', $dateStr)
            ->first();

        if ($exception && in_array($exception->type, ['leave', 'holiday'])) {
            return response()->json([
                'success' => true,
                'date' => $dateStr,
                'slots' => [],
                'message' => $exception->reason ?: 'Astrologer is unavailable on this date.',
            ]);
        }

        // Available intervals
        $durationMinutes = 30;
        $serviceId = $request->query('service_id');
        if ($serviceId) {
            $svc = ProfessionalService::find($serviceId);
            if ($svc && $svc->duration_minutes > 0) {
                $durationMinutes = $svc->duration_minutes;
            }
        }

        $dayOfWeek = (int) $date->dayOfWeek;
        $weekly = \App\Models\ProfessionalAvailability::where('admin_id', $adminId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        $intervals = [];
        if ($weekly && $weekly->is_available && !empty($weekly->slots)) {
            $intervals = $weekly->slots;
        } else {
            // Default consultation hours 10:00 to 19:00 if astrologer hasn't set custom hours
            $intervals = [
                ['start' => '10:00', 'end' => '13:00'],
                ['start' => '14:00', 'end' => '19:00'],
            ];
        }

        // Generate candidate slots
        $candidateSlots = [];
        foreach ($intervals as $interval) {
            $start = \Carbon\Carbon::parse($dateStr . ' ' . $interval['start']);
            $end = \Carbon\Carbon::parse($dateStr . ' ' . $interval['end']);

            $curr = $start->copy();
            while ($curr->copy()->addMinutes($durationMinutes)->lte($end)) {
                // If today, exclude slots in the past + 30 min buffer
                if (!$date->isToday() || $curr->isAfter(\Carbon\Carbon::now()->addMinutes(30))) {
                    $candidateSlots[] = $curr->format('H:i');
                }
                $curr->addMinutes($durationMinutes);
            }
        }

        // Exclude slots that conflict with existing non-cancelled appointments
        $existingAppointments = Appointment::where('admin_id', $adminId)
            ->whereDate('start_at', $dateStr)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->get();

        $availableSlots = [];
        foreach ($candidateSlots as $slotTime) {
            $slotStart = \Carbon\Carbon::parse($dateStr . ' ' . $slotTime);
            $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

            $hasConflict = false;
            foreach ($existingAppointments as $appt) {
                $apptStart = \Carbon\Carbon::parse($appt->start_at);
                $apptEnd = $appt->end_at ? \Carbon\Carbon::parse($appt->end_at) : $apptStart->copy()->addMinutes($durationMinutes);

                if ($slotStart->lt($apptEnd) && $slotEnd->gt($apptStart)) {
                    $hasConflict = true;
                    break;
                }
            }

            if (!$hasConflict) {
                $availableSlots[] = [
                    'time' => $slotTime,
                    'datetime' => $slotStart->toIso8601String(),
                    'display' => $slotStart->format('h:i A'),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'date' => $dateStr,
            'duration_minutes' => $durationMinutes,
            'slots' => $availableSlots,
        ]);
    }

    /**
     * GET /api/{lang?}/services/categories
     * Returns catalogue of available service categories.
     */
    public function getCategories(Request $request): JsonResponse
    {
        $categories = [
            [
                'slug' => 'janam_kundli',
                'name' => 'Janam Kundli',
                'description' => 'Comprehensive birth chart analysis and personal horoscope report.',
                'icon' => 'menu_book',
            ],
            [
                'slug' => 'kundli_consultation',
                'name' => 'Kundli Consultation',
                'description' => '1-on-1 personalized horoscope reading and query resolution.',
                'icon' => 'record_voice_over',
            ],
            [
                'slug' => 'vastu',
                'name' => 'Vastu Consultation',
                'description' => 'Residential and commercial floor plan audits and direction corrections.',
                'icon' => 'explore',
            ],
            [
                'slug' => 'muhurat',
                'name' => 'Shubh Muhurat',
                'description' => 'Auspicious timing determination for weddings, griha pravesh, and business.',
                'icon' => 'calendar_month',
            ],
            [
                'slug' => 'puja',
                'name' => 'Puja & Rituals',
                'description' => 'Vedic rituals, dosha nivaran pujas, and yagnas.',
                'icon' => 'local_fire_department',
            ],
            [
                'slug' => 'other',
                'name' => 'Specialized Consultation',
                'description' => 'Gemology, palmistry, and numerology consultations.',
                'icon' => 'psychology',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * GET /api/{lang?}/services/providers
     * Discover astrologers providing a specific service or category.
     */
    public function getProviders(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $query = Astrologer::query();

        if ($category) {
            $query->whereHas('admin.professionalServices', function ($q) use ($category) {
                $q->where('is_active', true)
                  ->where('is_public', true)
                  ->where('category', $category);
            });
        }

        $astrologers = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $astrologers->items(),
            'meta' => [
                'current_page' => $astrologers->currentPage(),
                'last_page' => $astrologers->lastPage(),
                'per_page' => $astrologers->perPage(),
                'total' => $astrologers->total(),
            ],
        ]);
    }

    /**
     * POST /api/{lang?}/services/{id}/book-appointment
     * Book a scheduled appointment (e.g. video/voice consultation, vastu visit).
     */
    public function bookAppointment(BookAppointmentRequest $request, $id): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $this->serviceOrderService->bookAppointment(
                $user,
                (int) $id,
                $request->validated()
            );

            $isIdempotent = !empty($result['idempotent']);

            return response()->json([
                'success' => true,
                'idempotent' => $isIdempotent,
                'message' => $isIdempotent
                    ? 'Appointment retrieved via idempotent client nonce.'
                    : 'Appointment booked successfully. Awaiting practitioner confirmation.',
                'data' => new AppointmentBookingResource($result['appointment']),
                'pricing' => $result['pricing'],
            ], $isIdempotent ? 200 : 201);
        } catch (InsufficientWalletBalanceException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'INSUFFICIENT_WALLET_BALANCE',
                'message' => $e->getMessage(),
                'required_amount' => $e->getRequiredAmount(),
                'wallet_balance' => $e->getWalletBalance(),
                'currency' => $e->getCurrency(),
            ], 402);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database unique constraint collisions gracefully without HTTP 500
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                $nonce = $request->input('client_nonce');
                if (!empty($nonce)) {
                    $existing = Appointment::where('client_nonce', $nonce)->first();
                    if ($existing && (int) $existing->user_id === (int) $user->id) {
                        return response()->json([
                            'success' => true,
                            'idempotent' => true,
                            'message' => 'Appointment retrieved via idempotent client nonce.',
                            'data' => new AppointmentBookingResource($existing),
                            'pricing' => $this->pricingService->calculateCommercialPricing($existing->base_price),
                        ], 200);
                    }
                }
                return response()->json([
                    'success' => false,
                    'message' => 'A transaction with this request key is already being processed. Please refresh.',
                ], 409);
            }
            return response()->json([
                'success' => false,
                'message' => 'Database error encountered during booking: ' . $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to book appointment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/{lang?}/services/{id}/order-deliverable
     * Order an asynchronous deliverable service (written report, vastu audit, etc.).
     */
    public function orderDeliverable(OrderDeliverableRequest $request, $id): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $this->serviceOrderService->orderDeliverable(
                $user,
                (int) $id,
                $request->validated()
            );

            $isIdempotent = !empty($result['idempotent']);

            return response()->json([
                'success' => true,
                'idempotent' => $isIdempotent,
                'message' => $isIdempotent
                    ? 'Service order retrieved via idempotent client nonce.'
                    : 'Service order submitted successfully. Astrologer will deliver within the specified timeframe.',
                'data' => new ProfessionalServiceOrderResource($result['order']),
                'pricing' => $result['pricing'],
            ], $isIdempotent ? 200 : 201);
        } catch (InsufficientWalletBalanceException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'INSUFFICIENT_WALLET_BALANCE',
                'message' => $e->getMessage(),
                'required_amount' => $e->getRequiredAmount(),
                'wallet_balance' => $e->getWalletBalance(),
                'currency' => $e->getCurrency(),
            ], 402);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database unique constraint collisions gracefully without HTTP 500
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                $nonce = $request->input('client_nonce');
                if (!empty($nonce)) {
                    $existing = ProfessionalServiceOrder::where('client_nonce', $nonce)->first();
                    if ($existing && (int) $existing->user_id === (int) $user->id) {
                        return response()->json([
                            'success' => true,
                            'idempotent' => true,
                            'message' => 'Service order retrieved via idempotent client nonce.',
                            'data' => new ProfessionalServiceOrderResource($existing),
                            'pricing' => $this->pricingService->calculateCommercialPricing($existing->base_price),
                        ], 200);
                    }
                }
                return response()->json([
                    'success' => false,
                    'message' => 'A transaction with this request key is already being processed. Please refresh.',
                ], 409);
            }
            return response()->json([
                'success' => false,
                'message' => 'Database error encountered during order placement: ' . $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to submit service order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/{lang?}/user/service-orders
     * List user's asynchronous orders.
     */
    public function getUserServiceOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->query('status');

        $query = ProfessionalServiceOrder::where('user_id', $user->id)
            ->with(['service', 'professional.astrologer'])
            ->orderBy('id', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ProfessionalServiceOrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * GET /api/{lang?}/user/service-orders/{id}
     * Get a single asynchronous service order.
     */
    public function getUserServiceOrder(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $order = ProfessionalServiceOrder::with(['service', 'professional.astrologer'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Service order not found.',
            ], 404);
        }

        if ((int) $order->user_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You do not own this service order.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new ProfessionalServiceOrderResource($order),
        ]);
    }

    /**
     * POST /api/{lang?}/user/service-orders/{id}/confirm-completed
     * Manual client confirmation to release escrow earnings to the astrologer.
     */
    public function confirmDeliverableCompletion(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        try {
            $order = $this->serviceOrderService->confirmDeliverableCompletion((int) $id, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Order marked as completed. Thank you for confirming!',
                'data' => new ProfessionalServiceOrderResource($order->fresh(['service', 'professional.astrologer'])),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service order not found.',
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to confirm completion: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/{lang?}/user/service-orders/{id}/dispute
     * Dispute deliverable order (enters admin review).
     */
    public function disputeOrder(DisputeOrderRequest $request, $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $order = $this->serviceOrderService->disputeOrder(
                (int) $id,
                $user->id,
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Dispute submitted. Our administrative support team will review this order.',
                'data' => new ProfessionalServiceOrderResource($order->fresh(['service', 'professional.astrologer'])),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service order not found.',
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to submit dispute: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/{lang?}/user/appointments
     * List user's scheduled appointments.
     */
    public function getUserAppointments(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->query('status');

        $query = Appointment::where('user_id', $user->id)
            ->with(['service', 'professional.astrologer'])
            ->orderBy('start_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $appointments = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => AppointmentBookingResource::collection($appointments->items()),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ],
        ]);
    }

    /**
     * POST /api/{lang?}/user/appointments/{id}/cancel
     * Cancel an appointment following the 12-hour cutoff policy.
     */
    public function cancelUserAppointment(CancelAppointmentRequest $request, $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $appointment = $this->serviceOrderService->cancelAppointment(
                (int) $id,
                $user,
                $validated['reason'],
                'user'
            );

            $message = $appointment->status === 'cancelled_pending_review'
                ? 'Cancellation requested within 12 hours of the appointment. Your request has been forwarded for admin review.'
                : 'Appointment cancelled successfully. Full booking amount has been refunded to your wallet.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new AppointmentBookingResource($appointment->fresh(['service', 'professional.astrologer'])),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found.',
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to cancel appointment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/{lang?}/user/appointments/{id}/report-no-show
     * Report user/practitioner no-show.
     */
    public function reportAppointmentNoShow(ReportNoShowRequest $request, $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $appointment = $this->serviceOrderService->reportNoShow(
                (int) $id,
                'user',
                $validated['notes'],
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'No-show report received. Support will review this booking.',
                'data' => new AppointmentBookingResource($appointment->fresh(['service', 'professional.astrologer'])),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found.',
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to report no-show: ' . $e->getMessage(),
            ], 500);
        }
    }
}
