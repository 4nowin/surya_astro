<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Services\SubmitDeliverableRequest;
use App\Http\Resources\Services\ProfessionalServiceOrderResource;
use App\Models\ProfessionalServiceOrder;
use App\Services\Services\ServiceOrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ProfessionalServiceOrderApiController extends Controller
{
    public function __construct(
        protected ServiceOrderService $serviceOrderService
    ) {}

    /**
     * GET /api/{lang?}/astrologer/service-orders
     * List incoming orders for the authenticated practitioner.
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        $status = $request->query('status');

        $query = ProfessionalServiceOrder::where('admin_id', $admin->id)
            ->with(['user', 'service', 'professional.astrologer'])
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
     * GET /api/{lang?}/astrologer/service-orders/{id}
     * Retrieve single order details.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $admin = $request->user();

        $order = ProfessionalServiceOrder::with(['user', 'service', 'professional.astrologer'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Service order not found.',
            ], 404);
        }

        if ((int) $order->admin_id !== (int) $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not assigned to this service order.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new ProfessionalServiceOrderResource($order),
        ]);
    }

    /**
     * POST /api/{lang?}/astrologer/service-orders/{id}/start
     * Practitioner marks order as in progress.
     */
    public function start(Request $request, $id): JsonResponse
    {
        $admin = $request->user();

        $order = ProfessionalServiceOrder::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Service order not found.',
            ], 404);
        }

        if ((int) $order->admin_id !== (int) $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You are not assigned to this service order.',
            ], 403);
        }

        if ($order->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => "Order cannot be started in status '{$order->status}'.",
            ], 422);
        }

        $order->status = 'in_progress';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order marked as in progress.',
            'data' => new ProfessionalServiceOrderResource($order->fresh(['user', 'service', 'professional.astrologer'])),
        ]);
    }

    /**
     * POST /api/{lang?}/astrologer/service-orders/{id}/deliver
     * Practitioner submits deliverable report/reading.
     */
    public function deliver(SubmitDeliverableRequest $request, $id): JsonResponse
    {
        $admin = $request->user();
        $validated = $request->validated();

        try {
            $order = $this->serviceOrderService->submitDeliverable(
                (int) $id,
                $admin->id,
                $validated['deliverable_text'],
                $validated['deliverable_attachments'] ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Deliverable submitted successfully. Awaiting client review and confirmation.',
                'data' => new ProfessionalServiceOrderResource($order->fresh(['user', 'service', 'professional.astrologer'])),
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
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to submit deliverable: ' . $e->getMessage(),
            ], 500);
        }
    }
}
