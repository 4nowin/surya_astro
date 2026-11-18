<?php

declare(strict_types=1);

namespace App\Services\Services;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Appointment;
use App\Models\ProfessionalService;
use App\Models\ProfessionalServiceOrder;
use App\Models\ProfessionalTransaction;
use App\Models\User;
use App\Models\UserWalletTransaction;
use App\Services\Pricing\PricingService;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ServiceOrderService
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Book a scheduled appointment with atomic wallet deduction and escrow holding.
     *
     * @param User $user The consumer booking the appointment
     * @param int $serviceId The professional service ID
     * @param array $data Booking details: start_at, end_at, appointment_type, location, timezone, client_nonce
     * @return array{appointment: Appointment, wallet_transaction: UserWalletTransaction, pricing: array}
     *
     * @throws InsufficientWalletBalanceException
     * @throws InvalidArgumentException
     */
    public function bookAppointment(User $user, int $serviceId, array $data): array
    {
        $service = ProfessionalService::where('id', $serviceId)
            ->where('is_active', true)
            ->firstOrFail();

        $pricing = $this->pricingService->calculateCommercialPricing(
            $service->price,
            (bool) ($service->is_free_chat ?? false)
        );

        $customerPrice = $pricing['customer_price'];
        $basePrice = $pricing['base_price'];
        $markup = $pricing['platform_markup'];

        // Idempotency check via client_nonce
        if (!empty($data['client_nonce'])) {
            $existing = Appointment::where('client_nonce', $data['client_nonce'])->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id) {
                    throw new AuthorizationException("Unauthorized: Client nonce already associated with another user's appointment.");
                }
                if ((int) $existing->service_id !== (int) $service->id) {
                    throw new InvalidArgumentException("Idempotency conflict: Client nonce has already been used for a different appointment booking.");
                }
                return [
                    'appointment' => $existing,
                    'wallet_transaction' => $existing->walletTransactions()->latest()->first(),
                    'pricing' => $this->pricingService->calculateCommercialPricing($existing->base_price),
                    'idempotent' => true,
                ];
            }
        }

        $startAt = Carbon::parse($data['start_at']);
        $endAt = isset($data['end_at'])
            ? Carbon::parse($data['end_at'])
            : $startAt->copy()->addMinutes($service->duration_minutes ?? 30);

        // Double booking prevention: Check conflicting appointments for this practitioner
        $conflicting = Appointment::where('admin_id', $service->admin_id)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where('start_at', '<', $endAt)
                  ->where('end_at', '>', $startAt);
            })
            ->first();

        if ($conflicting) {
            throw new InvalidArgumentException("The selected appointment slot is no longer available. Please select another slot.");
        }

        return DB::transaction(function () use ($user, $service, $data, $pricing, $customerPrice, $basePrice, $markup, $startAt, $endAt) {
            // Lock user row for concurrency-safe balance check & deduction
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            // Concurrency re-check for identical client_nonce under locked transaction
            if (!empty($data['client_nonce'])) {
                $existing = Appointment::where('client_nonce', $data['client_nonce'])->lockForUpdate()->first();
                if ($existing) {
                    if ((int) $existing->user_id !== (int) $user->id) {
                        throw new AuthorizationException("Unauthorized: Client nonce already associated with another user's appointment.");
                    }
                    if ((int) $existing->service_id !== (int) $service->id) {
                        throw new InvalidArgumentException("Idempotency conflict: Client nonce has already been used for a different appointment booking.");
                    }
                    return [
                        'appointment' => $existing,
                        'wallet_transaction' => $existing->walletTransactions()->latest()->first(),
                        'pricing' => $pricing,
                        'idempotent' => true,
                    ];
                }
            }

            if ($customerPrice > 0 && (float) $lockedUser->wallet_balance < $customerPrice) {
                throw new InsufficientWalletBalanceException(
                    $customerPrice,
                    (float) $lockedUser->wallet_balance,
                    $service->currency ?? 'INR'
                );
            }

            $balanceBefore = (float) $lockedUser->wallet_balance;
            $balanceAfter = round($balanceBefore - $customerPrice, 2);

            if ($customerPrice > 0) {
                $lockedUser->wallet_balance = $balanceAfter;
                $lockedUser->save();
            }

            // 1. Create Appointment
            $startAt = Carbon::parse($data['start_at']);
            $endAt = isset($data['end_at'])
                ? Carbon::parse($data['end_at'])
                : $startAt->copy()->addMinutes($service->duration_minutes ?? 30);

            $appointment = Appointment::create([
                'admin_id' => $service->admin_id,
                'user_id' => $lockedUser->id,
                'service_id' => $service->id,
                'title' => $service->name,
                'description' => $data['notes'] ?? $data['description'] ?? $service->description,
                'appointment_type' => $data['appointment_type'] ?? 'online',
                'booking_source' => 'astro_setu',
                'status' => 'pending_confirmation',
                'start_at' => $startAt,
                'end_at' => $endAt,
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'location' => $data['location'] ?? null,
                'acceptance_deadline_at' => Carbon::now()->addHours(24), // Authoritative Policy: 24h acceptance deadline
                'service_name_snapshot' => $service->name,
                'agreed_price_snapshot' => $basePrice,
                'base_price' => $basePrice,
                'platform_markup' => $markup,
                'customer_price' => $customerPrice,
                'currency_snapshot' => $service->currency ?? 'INR',
                'duration_minutes_snapshot' => (int) ($service->duration_minutes ?? 30),
                'delivery_method_snapshot' => $data['delivery_method'] ?? 'online',
                'payment_status' => 'pending', // Escrow status
                'client_nonce' => $data['client_nonce'] ?? null,
            ]);

            // 2. Create User Wallet Ledger Entry
            $walletTx = UserWalletTransaction::create([
                'user_id' => $lockedUser->id,
                'transaction_type' => 'appointment_booking_debit',
                'amount' => $customerPrice,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'appointment_id' => $appointment->id,
                'service_id' => $service->id,
                'reference_id' => 'APT-DEB-' . Str::uuid(),
                'description' => "Booking for {$service->name}",
                'status' => 'completed',
            ]);

            // 3. Create Escrow Professional Transaction (Pending release upon completion)
            $escrowTx = ProfessionalTransaction::create([
                'admin_id' => $service->admin_id,
                'user_id' => $lockedUser->id,
                'appointment_id' => $appointment->id,
                'service_id' => $service->id,
                'transaction_type' => 'appointment_advance',
                'payment_method' => 'wallet',
                'payment_status' => 'pending', // Held in Escrow!
                'amount' => $basePrice,
                'gross_amount' => $customerPrice,
                'platform_fee' => $markup,
                'net_amount' => $basePrice,
                'currency' => $service->currency ?? 'INR',
                'transaction_at' => Carbon::now(),
                'reference_id' => 'APT-ESC-' . Str::uuid(),
                'notes' => "Escrow held for appointment #{$appointment->id} ({$service->name})",
            ]);

            $appointment->escrow_transaction_id = $escrowTx->id;
            $appointment->save();

            return [
                'appointment' => $appointment->fresh(),
                'wallet_transaction' => $walletTx,
                'pricing' => $pricing,
            ];
        });
    }

    /**
     * Order an asynchronous deliverable service with custom deadline snapshot and manual client confirmation.
     *
     * @param User $user The consumer ordering the service
     * @param int $serviceId The professional service ID
     * @param array $data Input payload (birth details, query, blueprint etc.), client_nonce, order_type
     * @return array{order: ProfessionalServiceOrder, wallet_transaction: UserWalletTransaction, pricing: array}
     *
     * @throws InsufficientWalletBalanceException
     */
    public function orderDeliverable(User $user, int $serviceId, array $data): array
    {
        $service = ProfessionalService::where('id', $serviceId)
            ->where('is_active', true)
            ->firstOrFail();

        // Idempotency check via client_nonce
        if (!empty($data['client_nonce'])) {
            $existing = ProfessionalServiceOrder::where('client_nonce', $data['client_nonce'])->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id) {
                    throw new AuthorizationException("Unauthorized: Client nonce already associated with another user's order.");
                }
                if ((int) $existing->service_id !== (int) $service->id || ($existing->order_type !== ($data['order_type'] ?? 'custom'))) {
                    throw new InvalidArgumentException("Idempotency conflict: Client nonce has already been used for a different service order.");
                }
                return [
                    'order' => $existing,
                    'wallet_transaction' => $existing->walletTransactions()->latest()->first(),
                    'pricing' => $this->pricingService->calculateCommercialPricing($existing->base_price),
                    'idempotent' => true,
                ];
            }
        }

        $pricing = $this->pricingService->calculateCommercialPricing(
            $service->price,
            (bool) ($service->is_free_chat ?? false)
        );

        $customerPrice = $pricing['customer_price'];
        $basePrice = $pricing['base_price'];
        $markup = $pricing['platform_markup'];

        return DB::transaction(function () use ($user, $service, $data, $pricing, $customerPrice, $basePrice, $markup) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            // Concurrency re-check for identical client_nonce under locked transaction
            if (!empty($data['client_nonce'])) {
                $existing = ProfessionalServiceOrder::where('client_nonce', $data['client_nonce'])->lockForUpdate()->first();
                if ($existing) {
                    if ((int) $existing->user_id !== (int) $user->id) {
                        throw new AuthorizationException("Unauthorized: Client nonce already associated with another user's order.");
                    }
                    if ((int) $existing->service_id !== (int) $service->id || ($existing->order_type !== ($data['order_type'] ?? 'custom'))) {
                        throw new InvalidArgumentException("Idempotency conflict: Client nonce has already been used for a different service order.");
                    }
                    return [
                        'order' => $existing,
                        'wallet_transaction' => $existing->walletTransactions()->latest()->first(),
                        'pricing' => $pricing,
                        'idempotent' => true,
                    ];
                }
            }

            if ($customerPrice > 0 && (float) $lockedUser->wallet_balance < $customerPrice) {
                throw new InsufficientWalletBalanceException(
                    $customerPrice,
                    (float) $lockedUser->wallet_balance,
                    $service->currency ?? 'INR'
                );
            }

            $balanceBefore = (float) $lockedUser->wallet_balance;
            $balanceAfter = round($balanceBefore - $customerPrice, 2);

            if ($customerPrice > 0) {
                $lockedUser->wallet_balance = $balanceAfter;
                $lockedUser->save();
            }

            // Custom deliverable deadline per service (Authoritative Policy 3)
            $deliveryTimeframeDays = (int) ($service->delivery_timeframe_days ?? 3);
            $deliveryDeadlineAt = Carbon::now()->addDays($deliveryTimeframeDays);

            // Generate unique human-readable order number
            $orderNumber = 'ORD-' . Carbon::now()->format('Ymd') . '-' . strtoupper(Str::random(6));

            $order = ProfessionalServiceOrder::create([
                'order_number' => $orderNumber,
                'user_id' => $lockedUser->id,
                'admin_id' => $service->admin_id,
                'astrologer_id' => $service->professional?->astrologer?->id,
                'service_id' => $service->id,
                'order_type' => $data['order_type'] ?? 'custom',
                'status' => 'submitted',
                'base_price' => $basePrice,
                'platform_markup' => $markup,
                'customer_price' => $customerPrice,
                'currency' => $service->currency ?? 'INR',
                'delivery_timeframe_days' => $deliveryTimeframeDays,
                'delivery_deadline_at' => $deliveryDeadlineAt,
                'input_payload' => $data['input_payload'] ?? null,
                'client_nonce' => $data['client_nonce'] ?? null,
            ]);

            // User wallet transaction
            $walletTx = UserWalletTransaction::create([
                'user_id' => $lockedUser->id,
                'transaction_type' => 'service_order_debit',
                'amount' => $customerPrice,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'service_order_id' => $order->id,
                'service_id' => $service->id,
                'reference_id' => 'ORD-DEB-' . Str::uuid(),
                'description' => "Order {$orderNumber} for {$service->name}",
                'status' => 'completed',
            ]);

            // Escrow transaction (held until manual client confirmation)
            $escrowTx = ProfessionalTransaction::create([
                'admin_id' => $service->admin_id,
                'user_id' => $lockedUser->id,
                'service_order_id' => $order->id,
                'service_id' => $service->id,
                'transaction_type' => 'service_payment',
                'payment_method' => 'wallet',
                'payment_status' => 'pending', // Escrow!
                'amount' => $basePrice,
                'gross_amount' => $customerPrice,
                'platform_fee' => $markup,
                'net_amount' => $basePrice,
                'currency' => $service->currency ?? 'INR',
                'transaction_at' => Carbon::now(),
                'reference_id' => 'ORD-ESC-' . Str::uuid(),
                'notes' => "Escrow held for service order {$orderNumber} ({$service->name})",
            ]);

            $order->escrow_transaction_id = $escrowTx->id;
            $order->save();

            return [
                'order' => $order->fresh(),
                'wallet_transaction' => $walletTx,
                'pricing' => $pricing,
            ];
        });
    }

    /**
     * Astrologer accepts an appointment within 24 hours.
     *
     * @param int $appointmentId
     * @param int $adminId
     * @return Appointment
     */
    public function acceptAppointment(int $appointmentId, int $adminId): Appointment
    {
        $appointment = Appointment::find($appointmentId);
        if (!$appointment) {
            throw new ModelNotFoundException("Appointment not found.");
        }
        if ((int) $appointment->admin_id !== (int) $adminId) {
            throw new AuthorizationException("Unauthorized: You are not assigned to this appointment.");
        }

        if (!$appointment->isPendingAcceptance()) {
            throw new InvalidArgumentException("Appointment is not pending acceptance (current status: {$appointment->status}).");
        }

        // Check if 24-hour acceptance deadline has expired (Authoritative Policy 1)
        if ($appointment->isAcceptanceExpired()) {
            DB::transaction(function () use ($appointment) {
                $this->refundAppointmentInternally(
                    $appointment,
                    'system',
                    'Appointment acceptance deadline (24 hours) expired without practitioner confirmation.'
                );
            });
            throw new InvalidArgumentException("Appointment acceptance deadline has expired. The booking has been refunded.");
        }

        return DB::transaction(function () use ($appointmentId, $adminId) {
            $appointment = Appointment::where('id', $appointmentId)
                ->where('admin_id', $adminId)
                ->lockForUpdate()
                ->firstOrFail();

            $appointment->status = 'confirmed';
            $appointment->accepted_at = Carbon::now();
            $appointment->save();

            return $appointment;
        });
    }

    /**
     * Astrologer rejects an appointment. Triggers full user refund.
     *
     * @param int $appointmentId
     * @param int $adminId
     * @param string $reason
     * @return Appointment
     */
    public function rejectAppointment(int $appointmentId, int $adminId, string $reason): Appointment
    {
        return DB::transaction(function () use ($appointmentId, $adminId, $reason) {
            $appointment = Appointment::where('id', $appointmentId)->lockForUpdate()->first();
            if (!$appointment) {
                throw new ModelNotFoundException("Appointment not found.");
            }
            if ((int) $appointment->admin_id !== (int) $adminId) {
                throw new AuthorizationException("Unauthorized: You are not assigned to this appointment.");
            }

            if (!in_array($appointment->status, ['pending', 'pending_confirmation', 'confirmed'], true)) {
                throw new InvalidArgumentException("Cannot reject appointment with status: {$appointment->status}.");
            }

            $appointment->rejected_at = Carbon::now();
            $appointment->rejection_reason = $reason;

            $this->refundAppointmentInternally($appointment, 'astrologer', $reason);

            return $appointment->fresh();
        });
    }

    /**
     * Cancel an appointment according to the Authoritative 12-hour Cancellation Cutoff policy.
     *
     * @param int $appointmentId
     * @param User|int $actor The actor cancelling (User or Admin/Astrologer ID)
     * @param string $reason
     * @param string $actorType 'user' or 'astrologer' or 'admin'
     * @return Appointment
     */
    public function cancelAppointment(int $appointmentId, User|int $actor, string $reason, string $actorType = 'user'): Appointment
    {
        return DB::transaction(function () use ($appointmentId, $actor, $reason, $actorType) {
            $appointment = Appointment::where('id', $appointmentId)->lockForUpdate()->first();
            if (!$appointment) {
                throw new ModelNotFoundException("Appointment not found.");
            }

            if ($actorType === 'user') {
                $userId = $actor instanceof User ? $actor->id : (int) $actor;
                if ((int) $appointment->user_id !== (int) $userId) {
                    throw new AuthorizationException("Unauthorized: You do not own this appointment.");
                }
            } elseif ($actorType === 'astrologer') {
                $adminId = is_numeric($actor) ? (int) $actor : $actor->id;
                if ((int) $appointment->admin_id !== (int) $adminId) {
                    throw new AuthorizationException("Unauthorized: You are not assigned to this appointment.");
                }
            }

            if (in_array($appointment->status, ['cancelled', 'completed', 'cancelled_pending_review'], true)) {
                throw new InvalidArgumentException("Appointment is already {$appointment->status}.");
            }

            // Authoritative Policy 2: 12-hour cancellation cutoff for user cancellations
            if ($actorType === 'user' && $appointment->isInsideCancellationCutoff(12)) {
                // Inside cutoff: Enter admin review. No automatic penalty, but refund held for review.
                $appointment->status = 'cancelled_pending_review';
                $appointment->cancelled_by = 'user';
                $appointment->cancelled_at = Carbon::now();
                $appointment->cancellation_reason = $reason;
                $appointment->save();

                return $appointment;
            }

            // Outside cutoff or cancelled by astrologer/admin: Full automatic refund
            $this->refundAppointmentInternally($appointment, $actorType, $reason);

            return $appointment->fresh();
        });
    }

    /**
     * Report an appointment no-show (Authoritative Policy 5: Admin review queue).
     *
     * @param int $appointmentId
     * @param string $reportedBy 'user' or 'astrologer'
     * @param string $notes Supporting notes
     * @return Appointment
     */
    public function reportNoShow(int $appointmentId, string $reportedBy, string $notes, ?int $actorId = null): Appointment
    {
        return DB::transaction(function () use ($appointmentId, $reportedBy, $notes, $actorId) {
            $appointment = Appointment::where('id', $appointmentId)->lockForUpdate()->firstOrFail();

            if ($reportedBy === 'user' && $actorId !== null && (int) $appointment->user_id !== (int) $actorId) {
                throw new AuthorizationException("Unauthorized: Appointment does not belong to this user.");
            }

            if ($reportedBy === 'astrologer' && $actorId !== null && (int) $appointment->admin_id !== (int) $actorId) {
                throw new AuthorizationException("Unauthorized: Appointment does not belong to this practitioner.");
            }

            if (in_array($appointment->status, ['cancelled', 'completed'], true)) {
                throw new InvalidArgumentException("Cannot report no-show on an appointment that is already {$appointment->status}.");
            }

            $appointment->status = 'no_show_pending_review';
            $appointment->no_show_reported_at = Carbon::now();
            $appointment->no_show_reported_by = $reportedBy;
            $appointment->no_show_notes = $notes;
            $appointment->no_show_review_status = 'pending_review';
            $appointment->save();

            // Funds remain held in escrow awaiting admin review

            return $appointment;
        });
    }

    /**
     * Mark appointment completed and release escrow earnings to the astrologer.
     *
     * @param int $appointmentId
     * @param int $adminId
     * @return Appointment
     */
    public function completeAppointment(int $appointmentId, int $adminId): Appointment
    {
        return DB::transaction(function () use ($appointmentId, $adminId) {
            $appointment = Appointment::where('id', $appointmentId)->lockForUpdate()->first();
            if (!$appointment) {
                throw new ModelNotFoundException("Appointment not found.");
            }
            if ((int) $appointment->admin_id !== (int) $adminId) {
                throw new AuthorizationException("Unauthorized: You are not assigned to this appointment.");
            }

            $appointment->status = 'completed';
            $appointment->payment_status = 'paid';
            $appointment->save();

            // Release escrow transaction
            if ($appointment->escrow_transaction_id) {
                $escrowTx = ProfessionalTransaction::where('id', $appointment->escrow_transaction_id)
                    ->lockForUpdate()
                    ->first();

                if ($escrowTx && $escrowTx->payment_status === 'pending') {
                    $escrowTx->payment_status = 'completed';
                    $escrowTx->notes = ($escrowTx->notes ?? '') . " [Escrow released on completion at " . Carbon::now()->toIso8601String() . "]";
                    $escrowTx->save();
                }
            }

            return $appointment;
        });
    }

    /**
     * Astrologer submits deliverable for an asynchronous order (Authoritative Policy 4).
     * Does NOT release escrow payment until customer manually confirms completion!
     *
     * @param int $orderId
     * @param int $adminId
     * @param string $text Deliverable text / report
     * @param array $attachments Array of attachment URLs / paths
     * @return ProfessionalServiceOrder
     */
    public function submitDeliverable(int $orderId, int $adminId, string $text, array $attachments = []): ProfessionalServiceOrder
    {
        return DB::transaction(function () use ($orderId, $adminId, $text, $attachments) {
            $order = ProfessionalServiceOrder::where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                throw new ModelNotFoundException("Service order not found.");
            }
            if ((int) $order->admin_id !== (int) $adminId) {
                throw new AuthorizationException("Unauthorized: You are not assigned to this service order.");
            }

            if (!$order->canBeDelivered()) {
                throw new InvalidArgumentException("Order cannot be delivered in current status ({$order->status}).");
            }

            $order->status = 'delivered';
            $order->delivered_at = Carbon::now();
            $order->deliverable_text = $text;
            $order->deliverable_attachments = $attachments;
            $order->save();

            // Escrow stays 'pending' until client confirmation or admin dispute resolution!

            return $order;
        });
    }

    /**
     * Client manually confirms deliverable completion (Authoritative Policy 4).
     * Releases escrow payment to astrologer.
     *
     * @param int $orderId
     * @param int $userId
     * @return ProfessionalServiceOrder
     */
    public function confirmDeliverableCompletion(int $orderId, int $userId): ProfessionalServiceOrder
    {
        return DB::transaction(function () use ($orderId, $userId) {
            $order = ProfessionalServiceOrder::where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                throw new ModelNotFoundException("Service order not found.");
            }
            if ((int) $order->user_id !== (int) $userId) {
                throw new AuthorizationException("Unauthorized: You do not own this service order.");
            }

            if ($order->status !== 'delivered') {
                throw new InvalidArgumentException("Only delivered orders can be confirmed by client (current status: {$order->status}).");
            }

            $order->status = 'completed';
            $order->completed_at = Carbon::now();
            $order->save();

            // Release escrow funds to astrologer
            if ($order->escrow_transaction_id) {
                $escrowTx = ProfessionalTransaction::where('id', $order->escrow_transaction_id)
                    ->lockForUpdate()
                    ->first();

                if ($escrowTx && $escrowTx->payment_status === 'pending') {
                    $escrowTx->payment_status = 'completed';
                    $escrowTx->notes = ($escrowTx->notes ?? '') . " [Escrow released on client confirmation at " . Carbon::now()->toIso8601String() . "]";
                    $escrowTx->save();
                }
            }

            return $order;
        });
    }

    /**
     * Client disputes an order or deliverable. Enters admin review.
     *
     * @param int $orderId
     * @param int $userId
     * @param string $reason
     * @return ProfessionalServiceOrder
     */
    public function disputeOrder(int $orderId, int $userId, string $reason): ProfessionalServiceOrder
    {
        return DB::transaction(function () use ($orderId, $userId, $reason) {
            $order = ProfessionalServiceOrder::where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                throw new ModelNotFoundException("Service order not found.");
            }
            if ((int) $order->user_id !== (int) $userId) {
                throw new AuthorizationException("Unauthorized: You do not own this service order.");
            }

            if (in_array($order->status, ['completed', 'cancelled', 'refunded'], true)) {
                throw new InvalidArgumentException("Cannot dispute an order that is already {$order->status}.");
            }

            $order->status = 'disputed';
            $order->disputed_at = Carbon::now();
            $order->dispute_reason = $reason;
            $order->dispute_status = 'pending_review';
            $order->save();

            return $order;
        });
    }

    /**
     * Admin resolves a disputed service order.
     *
     * @param int $orderId
     * @param string $resolution 'refund' or 'payout'
     * @param string $notes
     * @param int|null $adminId Resolving administrator ID
     * @return ProfessionalServiceOrder
     */
    public function resolveOrderDispute(int $orderId, string $resolution, string $notes, ?int $adminId = null): ProfessionalServiceOrder
    {
        return DB::transaction(function () use ($orderId, $resolution, $notes, $adminId) {
            $order = ProfessionalServiceOrder::where('id', $orderId)->lockForUpdate()->firstOrFail();

            if ($order->status !== 'disputed') {
                throw new InvalidArgumentException("Order is not in disputed state (current status: {$order->status}).");
            }

            $order->dispute_resolved_at = Carbon::now();
            $order->dispute_resolution_notes = $notes;
            $order->resolved_by_admin_id = $adminId;

            if ($resolution === 'refund') {
                $order->status = 'refunded';
                $order->dispute_status = 'resolved_refund';
                $order->save();

                // Refund consumer wallet
                if ((float) $order->customer_price > 0 && $order->user_id) {
                    $user = User::where('id', $order->user_id)->lockForUpdate()->first();
                    if ($user) {
                        $before = (float) $user->wallet_balance;
                        $after = round($before + (float) $order->customer_price, 2);
                        $user->wallet_balance = $after;
                        $user->save();

                        UserWalletTransaction::create([
                            'user_id' => $user->id,
                            'transaction_type' => 'refund',
                            'amount' => (float) $order->customer_price,
                            'balance_before' => $before,
                            'balance_after' => $after,
                            'service_order_id' => $order->id,
                            'service_id' => $order->service_id,
                            'reference_id' => 'ORD-REF-' . Str::uuid(),
                            'description' => "Dispute refund for order {$order->order_number}: {$notes}",
                            'status' => 'completed',
                        ]);
                    }
                }

                // Mark escrow transaction refunded
                if ($order->escrow_transaction_id) {
                    $escrowTx = ProfessionalTransaction::where('id', $order->escrow_transaction_id)->lockForUpdate()->first();
                    if ($escrowTx) {
                        $escrowTx->payment_status = 'refunded';
                        $escrowTx->notes = ($escrowTx->notes ?? '') . " [Refunded by dispute resolution: {$notes}]";
                        $escrowTx->save();
                    }
                }
            } elseif ($resolution === 'payout') {
                $order->status = 'completed';
                $order->dispute_status = 'resolved_payout';
                $order->completed_at = Carbon::now();
                $order->save();

                // Release escrow to practitioner
                if ($order->escrow_transaction_id) {
                    $escrowTx = ProfessionalTransaction::where('id', $order->escrow_transaction_id)->lockForUpdate()->first();
                    if ($escrowTx) {
                        $escrowTx->payment_status = 'completed';
                        $escrowTx->notes = ($escrowTx->notes ?? '') . " [Released by dispute resolution: {$notes}]";
                        $escrowTx->save();
                    }
                }
            } else {
                throw new InvalidArgumentException("Unknown resolution type: {$resolution}. Must be 'refund' or 'payout'.");
            }

            return $order;
        });
    }

    /**
     * Admin resolves late appointment cancellation (cancelled within 12h cutoff).
     *
     * @param int $appointmentId
     * @param string $resolution 'refund' or 'payout'
     * @param string $notes
     * @param int|null $adminId
     * @return Appointment
     */
    public function resolveAppointmentCancellation(int $appointmentId, string $resolution, string $notes, ?int $adminId = null): Appointment
    {
        return DB::transaction(function () use ($appointmentId, $resolution, $notes, $adminId) {
            $appointment = Appointment::where('id', $appointmentId)->lockForUpdate()->firstOrFail();

            if ($appointment->status !== 'cancelled_pending_review') {
                throw new InvalidArgumentException("Appointment is not pending cancellation review (current status: {$appointment->status}).");
            }

            $appointment->resolved_by_admin_id = $adminId;
            $appointment->resolution_notes = $notes;
            $appointment->resolved_at = Carbon::now();

            if ($resolution === 'refund') {
                $appointment->status = 'cancelled';
                $appointment->cancellation_review_status = 'resolved_refund';
                $appointment->payment_status = 'refunded';
                $appointment->save();

                $refundAmount = (float) $appointment->customer_price;
                if ($refundAmount > 0 && $appointment->user_id) {
                    $user = User::where('id', $appointment->user_id)->lockForUpdate()->first();
                    if ($user) {
                        $before = (float) $user->wallet_balance;
                        $after = round($before + $refundAmount, 2);
                        $user->wallet_balance = $after;
                        $user->save();

                        UserWalletTransaction::create([
                            'user_id' => $user->id,
                            'transaction_type' => 'refund',
                            'amount' => $refundAmount,
                            'balance_before' => $before,
                            'balance_after' => $after,
                            'appointment_id' => $appointment->id,
                            'service_id' => $appointment->service_id,
                            'reference_id' => 'APT-REF-' . Str::uuid(),
                            'description' => "Admin review refund for late cancelled appointment #{$appointment->id}: {$notes}",
                            'status' => 'completed',
                        ]);
                    }
                }

                if ($appointment->escrow_transaction_id) {
                    $escrowTx = ProfessionalTransaction::where('id', $appointment->escrow_transaction_id)->lockForUpdate()->first();
                    if ($escrowTx && $escrowTx->payment_status === 'pending') {
                        $escrowTx->payment_status = 'refunded';
                        $escrowTx->notes = ($escrowTx->notes ?? '') . " [Admin review refunded: {$notes}]";
                        $escrowTx->save();
                    }
                }
            } elseif ($resolution === 'payout') {
                $appointment->status = 'cancelled';
                $appointment->cancellation_review_status = 'resolved_payout';
                $appointment->payment_status = 'paid';
                $appointment->save();

                // Release escrow to practitioner
                if ($appointment->escrow_transaction_id) {
                    $escrowTx = ProfessionalTransaction::where('id', $appointment->escrow_transaction_id)->lockForUpdate()->first();
                    if ($escrowTx && $escrowTx->payment_status === 'pending') {
                        $escrowTx->payment_status = 'completed';
                        $escrowTx->notes = ($escrowTx->notes ?? '') . " [Admin review release to practitioner: {$notes}]";
                        $escrowTx->save();
                    }
                }
            } else {
                throw new InvalidArgumentException("Unknown resolution type: {$resolution}. Must be 'refund' or 'payout'.");
            }

            return $appointment;
        });
    }

    /**
     * Admin resolves appointment no-show report.
     *
     * @param int $appointmentId
     * @param string $resolution 'refund' or 'payout'
     * @param string $notes
     * @param int|null $adminId
     * @return Appointment
     */
    public function resolveAppointmentNoShow(int $appointmentId, string $resolution, string $notes, ?int $adminId = null): Appointment
    {
        return DB::transaction(function () use ($appointmentId, $resolution, $notes, $adminId) {
            $appointment = Appointment::where('id', $appointmentId)->lockForUpdate()->firstOrFail();

            if ($appointment->status !== 'no_show_pending_review') {
                throw new InvalidArgumentException("Appointment is not pending no-show review (current status: {$appointment->status}).");
            }

            $appointment->resolved_by_admin_id = $adminId;
            $appointment->resolution_notes = $notes;
            $appointment->resolved_at = Carbon::now();

            if ($resolution === 'refund') {
                $appointment->status = 'cancelled';
                $appointment->no_show_review_status = 'resolved_refund';
                $appointment->payment_status = 'refunded';
                $appointment->save();

                $refundAmount = (float) $appointment->customer_price;
                if ($refundAmount > 0 && $appointment->user_id) {
                    $user = User::where('id', $appointment->user_id)->lockForUpdate()->first();
                    if ($user) {
                        $before = (float) $user->wallet_balance;
                        $after = round($before + $refundAmount, 2);
                        $user->wallet_balance = $after;
                        $user->save();

                        UserWalletTransaction::create([
                            'user_id' => $user->id,
                            'transaction_type' => 'refund',
                            'amount' => $refundAmount,
                            'balance_before' => $before,
                            'balance_after' => $after,
                            'appointment_id' => $appointment->id,
                            'service_id' => $appointment->service_id,
                            'reference_id' => 'APT-REF-' . Str::uuid(),
                            'description' => "No-show resolution refund for appointment #{$appointment->id}: {$notes}",
                            'status' => 'completed',
                        ]);
                    }
                }

                if ($appointment->escrow_transaction_id) {
                    $escrowTx = ProfessionalTransaction::where('id', $appointment->escrow_transaction_id)->lockForUpdate()->first();
                    if ($escrowTx && $escrowTx->payment_status === 'pending') {
                        $escrowTx->payment_status = 'refunded';
                        $escrowTx->notes = ($escrowTx->notes ?? '') . " [No-show refund: {$notes}]";
                        $escrowTx->save();
                    }
                }
            } elseif ($resolution === 'payout') {
                $appointment->status = 'completed';
                $appointment->no_show_review_status = 'resolved_payout';
                $appointment->payment_status = 'paid';
                $appointment->save();

                // Release escrow to practitioner
                if ($appointment->escrow_transaction_id) {
                    $escrowTx = ProfessionalTransaction::where('id', $appointment->escrow_transaction_id)->lockForUpdate()->first();
                    if ($escrowTx && $escrowTx->payment_status === 'pending') {
                        $escrowTx->payment_status = 'completed';
                        $escrowTx->notes = ($escrowTx->notes ?? '') . " [No-show release to practitioner: {$notes}]";
                        $escrowTx->save();
                    }
                }
            } else {
                throw new InvalidArgumentException("Unknown resolution type: {$resolution}. Must be 'refund' or 'payout'.");
            }

            return $appointment;
        });
    }

    /**
     * Automatically find and expire pending appointments past their 24-hour acceptance deadline.
     * Atomically marks them expired, issues a full wallet refund to the customer,
     * updates the escrow transaction to refunded, and records the refund in the wallet ledger.
     *
     * @return int Number of appointments expired and refunded
     */
    public function expirePendingAppointments(): int
    {
        $expiredCount = 0;
        $now = Carbon::now();

        // Find candidate appointments that have passed the acceptance deadline
        $candidates = Appointment::whereIn('status', ['pending', 'pending_confirmation'])
            ->whereNotNull('acceptance_deadline_at')
            ->where('acceptance_deadline_at', '<=', $now)
            ->get();

        foreach ($candidates as $candidate) {
            $processed = DB::transaction(function () use ($candidate) {
                // Lock row to prevent race conditions with astrologer accepting concurrently
                $appointment = Appointment::where('id', $candidate->id)
                    ->lockForUpdate()
                    ->first();

                if (!$appointment || !$appointment->isAcceptanceExpired()) {
                    return false; // Already accepted or cancelled concurrently
                }

                $this->refundAppointmentInternally(
                    $appointment,
                    'system',
                    'Appointment acceptance deadline (24 hours) expired without practitioner confirmation.',
                    'expired'
                );

                return true;
            });

            if ($processed) {
                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    /**
     * Internal helper to refund an appointment and update escrow status atomically.
     */
    protected function refundAppointmentInternally(Appointment $appointment, string $actorType, string $reason, string $targetStatus = 'cancelled'): void
    {
        $appointment->status = $targetStatus;
        $appointment->cancelled_by = $actorType;
        $appointment->cancelled_at = Carbon::now();
        $appointment->cancellation_reason = $reason;
        $appointment->payment_status = 'refunded';
        $appointment->save();

        $refundAmount = (float) $appointment->customer_price;

        if ($refundAmount > 0 && $appointment->user_id) {
            $user = User::where('id', $appointment->user_id)->lockForUpdate()->first();
            if ($user) {
                $before = (float) $user->wallet_balance;
                $after = round($before + $refundAmount, 2);
                $user->wallet_balance = $after;
                $user->save();

                UserWalletTransaction::create([
                    'user_id' => $user->id,
                    'transaction_type' => 'refund',
                    'amount' => $refundAmount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'appointment_id' => $appointment->id,
                    'service_id' => $appointment->service_id,
                    'reference_id' => 'APT-REF-' . Str::uuid(),
                    'description' => "Refund for cancelled appointment #{$appointment->id} ({$reason})",
                    'status' => 'completed',
                ]);
            }
        }

        if ($appointment->escrow_transaction_id) {
            $escrowTx = ProfessionalTransaction::where('id', $appointment->escrow_transaction_id)
                ->lockForUpdate()
                ->first();

            if ($escrowTx) {
                $escrowTx->payment_status = 'refunded';
                $escrowTx->notes = ($escrowTx->notes ?? '') . " [Refunded: {$reason}]";
                $escrowTx->save();
            }
        }
    }
}
