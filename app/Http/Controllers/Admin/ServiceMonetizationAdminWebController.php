<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ProfessionalServiceOrder;
use App\Services\Pricing\PricingService;
use App\Services\Services\ServiceOrderService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class ServiceMonetizationAdminWebController extends Controller
{
    public function __construct(
        protected ServiceOrderService $serviceOrderService,
        protected PricingService $pricingService
    ) {
        $this->middleware(['auth:admin', 'super_admin']);
    }

    /**
     * Display the Monetization Review Queue & Settlement Dashboard.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'disputed_orders');

        $disputedOrders = ProfessionalServiceOrder::where('status', 'disputed')
            ->with(['user', 'service', 'professional.astrologer', 'escrowTransaction'])
            ->latest('id')
            ->paginate(15, ['*'], 'disputed_orders_page');

        $lateCancellations = Appointment::where('status', 'cancelled_pending_review')
            ->with(['user', 'service', 'professional.astrologer', 'escrowTransaction'])
            ->latest('id')
            ->paginate(15, ['*'], 'late_cancellations_page');

        $noShows = Appointment::where('status', 'no_show_pending_review')
            ->with(['user', 'service', 'professional.astrologer', 'escrowTransaction'])
            ->latest('id')
            ->paginate(15, ['*'], 'no_shows_page');

        $resolvedOrders = ProfessionalServiceOrder::whereNotNull('resolved_at')
            ->with(['user', 'service', 'professional.astrologer', 'resolver'])
            ->latest('resolved_at')
            ->paginate(15, ['*'], 'resolved_orders_page');

        $resolvedAppointments = Appointment::whereNotNull('resolved_at')
            ->with(['user', 'service', 'professional.astrologer', 'resolver'])
            ->latest('resolved_at')
            ->paginate(15, ['*'], 'resolved_appts_page');

        $counts = [
            'disputed_orders' => ProfessionalServiceOrder::where('status', 'disputed')->count(),
            'late_cancellations' => Appointment::where('status', 'cancelled_pending_review')->count(),
            'no_shows' => Appointment::where('status', 'no_show_pending_review')->count(),
            'resolved_orders' => ProfessionalServiceOrder::whereNotNull('resolved_at')->count(),
            'resolved_appointments' => Appointment::whereNotNull('resolved_at')->count(),
        ];
        $counts['total_pending'] = $counts['disputed_orders'] + $counts['late_cancellations'] + $counts['no_shows'];

        return view('admin.monetization.index', compact(
            'tab',
            'disputedOrders',
            'lateCancellations',
            'noShows',
            'resolvedOrders',
            'resolvedAppointments',
            'counts'
        ));
    }

    /**
     * Display detailed adjudication view for a deliverable service order.
     */
    public function showOrder($id): View
    {
        $order = ProfessionalServiceOrder::with([
            'user',
            'service',
            'professional.astrologer',
            'escrowTransaction',
            'walletTransactions',
            'resolver',
        ])->findOrFail($id);

        $pricing = $this->pricingService->calculateCommercialPricing((float) $order->base_price);

        return view('admin.monetization.show_order', compact('order', 'pricing'));
    }

    /**
     * Process administrative adjudication for a disputed deliverable order.
     */
    public function resolveOrderDispute(Request $request, $id): RedirectResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:refund,payout'],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $this->serviceOrderService->resolveOrderDispute(
                (int) $id,
                $validated['resolution'],
                $validated['notes'],
                Auth::id()
            );

            $actionText = $validated['resolution'] === 'refund' ? 'refunded to customer wallet' : 'paid out to astrologer';
            return redirect()
                ->route('admin.monetization.orders.show', $id)
                ->with('success', "Order dispute successfully resolved: {$actionText}.");
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (Exception $e) {
            return back()->with('error', 'Unable to resolve order dispute: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display detailed adjudication view for an appointment (late cancellation or no-show).
     */
    public function showAppointment($id): View
    {
        $appointment = Appointment::with([
            'user',
            'service',
            'professional.astrologer',
            'escrowTransaction',
            'walletTransactions',
            'resolver',
        ])->findOrFail($id);

        $pricing = $this->pricingService->calculateCommercialPricing((float) $appointment->base_price);

        return view('admin.monetization.show_appointment', compact('appointment', 'pricing'));
    }

    /**
     * Process administrative adjudication for a late appointment cancellation (<12 hours).
     */
    public function resolveCancellation(Request $request, $id): RedirectResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:refund,payout'],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $this->serviceOrderService->resolveAppointmentCancellation(
                (int) $id,
                $validated['resolution'],
                $validated['notes'],
                Auth::id()
            );

            $actionText = $validated['resolution'] === 'refund' ? 'refunded to customer wallet' : 'released to astrologer';
            return redirect()
                ->route('admin.monetization.appointments.show', $id)
                ->with('success', "Late cancellation successfully resolved: {$actionText}.");
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (Exception $e) {
            return back()->with('error', 'Unable to resolve cancellation: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Process administrative adjudication for a customer or practitioner no-show report.
     */
    public function resolveNoShow(Request $request, $id): RedirectResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:refund,payout'],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $this->serviceOrderService->resolveAppointmentNoShow(
                (int) $id,
                $validated['resolution'],
                $validated['notes'],
                Auth::id()
            );

            $actionText = $validated['resolution'] === 'refund' ? 'refunded to customer wallet' : 'released to astrologer';
            return redirect()
                ->route('admin.monetization.appointments.show', $id)
                ->with('success', "No-show report successfully resolved: {$actionText}.");
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (Exception $e) {
            return back()->with('error', 'Unable to resolve no-show: ' . $e->getMessage())->withInput();
        }
    }
}
