<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ProfessionalServiceOrder;
use App\Services\Services\ServiceOrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ServiceMonetizationAdminController extends Controller
{
    public function __construct(
        protected ServiceOrderService $serviceOrderService
    ) {}

    /**
     * GET /api/admin/monetization/review-queue
     * List all pending disputes, late cancellations, and no-shows for administrative adjudication.
     */
    public function reviewQueue(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || !($admin instanceof \App\Models\Admin) || !$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Platform administrator privileges required.'], 403);
        }

        $disputedOrders = ProfessionalServiceOrder::where('status', 'disputed')
            ->with(['user', 'service', 'professional.astrologer', 'escrowTransaction'])
            ->orderBy('id', 'desc')
            ->get();

        $lateCancellations = Appointment::where('status', 'cancelled_pending_review')
            ->with(['user', 'service', 'professional.astrologer', 'escrowTransaction'])
            ->orderBy('id', 'desc')
            ->get();

        $noShows = Appointment::where('status', 'no_show_pending_review')
            ->with(['user', 'service', 'professional.astrologer', 'escrowTransaction'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'disputed_orders' => $disputedOrders,
                'late_cancellations' => $lateCancellations,
                'no_shows' => $noShows,
                'counts' => [
                    'disputed_orders' => $disputedOrders->count(),
                    'late_cancellations' => $lateCancellations->count(),
                    'no_shows' => $noShows->count(),
                    'total_pending' => $disputedOrders->count() + $lateCancellations->count() + $noShows->count(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/admin/monetization/orders/{id}/resolve-dispute
     * Adjudicate a disputed deliverable service order.
     */
    public function resolveDispute(Request $request, $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || !($admin instanceof \App\Models\Admin) || !$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Platform administrator privileges required.'], 403);
        }

        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:refund,payout'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $order = $this->serviceOrderService->resolveOrderDispute(
                (int) $id,
                $validated['resolution'],
                $validated['notes'],
                $admin?->id
            );

            return response()->json([
                'success' => true,
                'message' => "Order dispute resolved successfully ({$validated['resolution']}).",
                'data' => $order->fresh(['user', 'service', 'professional.astrologer', 'escrowTransaction']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service order not found.',
            ], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve dispute: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/admin/monetization/appointments/{id}/resolve-cancellation
     * Adjudicate late appointment cancellation (<12h cutoff).
     */
    public function resolveCancellation(Request $request, $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || !($admin instanceof \App\Models\Admin) || !$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Platform administrator privileges required.'], 403);
        }

        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:refund,payout'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $appointment = $this->serviceOrderService->resolveAppointmentCancellation(
                (int) $id,
                $validated['resolution'],
                $validated['notes'],
                $admin?->id
            );

            return response()->json([
                'success' => true,
                'message' => "Appointment cancellation resolved successfully ({$validated['resolution']}).",
                'data' => $appointment->fresh(['user', 'service', 'professional.astrologer', 'escrowTransaction']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found.',
            ], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve cancellation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/admin/monetization/appointments/{id}/resolve-no-show
     * Adjudicate appointment no-show report.
     */
    public function resolveNoShow(Request $request, $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || !($admin instanceof \App\Models\Admin) || !$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Platform administrator privileges required.'], 403);
        }

        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:refund,payout'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $appointment = $this->serviceOrderService->resolveAppointmentNoShow(
                (int) $id,
                $validated['resolution'],
                $validated['notes'],
                $admin?->id
            );

            return response()->json([
                'success' => true,
                'message' => "Appointment no-show report resolved successfully ({$validated['resolution']}).",
                'data' => $appointment->fresh(['user', 'service', 'professional.astrologer', 'escrowTransaction']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found.',
            ], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve no-show: ' . $e->getMessage(),
            ], 500);
        }
    }
}
