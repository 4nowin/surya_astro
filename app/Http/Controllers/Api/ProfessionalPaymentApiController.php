<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\CustomerAstrologerConsent;
use App\Models\Jajman;
use App\Models\ProfessionalTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfessionalPaymentApiController extends Controller
{
    /**
     * 1. GET /api/astrologer/payments
     * List financial transactions with filters, search, and pagination.
     */
    public function index(Request $request)
    {
        $admin = $request->user();

        $query = ProfessionalTransaction::where('admin_id', $admin->id)
            ->with([
                'jajman:id,name,phone,city,user_id',
                'user:id,name,phone,profile_image',
                'service:id,name,pricing_model,category',
                'appointment:id,title,start_at,appointment_type',
                'consultation:id,client_name,type,consultation_date',
            ]);

        // Filter by Jajman
        if ($jajmanId = $request->query('jajman_id')) {
            $query->where('jajman_id', $jajmanId);
        }

        // Filter by Appointment
        if ($appointmentId = $request->query('appointment_id')) {
            $query->where('appointment_id', $appointmentId);
        }

        // Filter by Consultation
        if ($consultationId = $request->query('consultation_id')) {
            $query->where('consultation_id', $consultationId);
        }

        // Filter by Transaction Type
        if ($type = $request->query('transaction_type')) {
            $query->where('transaction_type', $type);
        }

        // Filter by Payment Method
        if ($method = $request->query('payment_method')) {
            $query->where('payment_method', $method);
        }

        // Filter by Payment Status
        if ($status = $request->query('payment_status')) {
            $query->where('payment_status', $status);
        }

        // Timezone-aware date filter
        [$startUtc, $endUtc] = $this->resolveDateRange($request);
        if ($startUtc && $endUtc) {
            $query->whereBetween('transaction_at', [$startUtc, $endUtc]);
        } elseif ($startUtc) {
            $query->where('transaction_at', '>=', $startUtc);
        } elseif ($endUtc) {
            $query->where('transaction_at', '<=', $endUtc);
        }

        // Search in reference_id, notes, or client name
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_id', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('jajman', function ($jq) use ($search) {
                      $jq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int) $request->query('per_page', 20);
        $transactions = $query->orderBy('transaction_at', 'desc')->paginate($perPage);

        // Sanitize personal contact details per Task B2 consent
        $this->sanitizeConsentForTransactions(collect($transactions->items()), $admin->id);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
            ],
        ]);
    }

    /**
     * 2. POST /api/astrologer/payments
     * Record a financial transaction with ownership validation.
     */
    public function store(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'currency' => 'nullable|string|max:8',
            'jajman_id' => 'nullable|integer',
            'appointment_id' => 'nullable|integer',
            'consultation_id' => 'nullable|integer',
            'transaction_type' => [
                'required',
                Rule::in([
                    'service_payment',
                    'appointment_advance',
                    'consultation_payment',
                    'general_payment',
                    'adjustment',
                    'chat_message',
                ]),
            ],
            'payment_method' => [
                'required',
                Rule::in(['cash', 'upi', 'bank_transfer', 'card', 'cheque', 'other', 'wallet']),
            ],
            'payment_status' => [
                'nullable',
                Rule::in(['completed', 'pending', 'failed']),
            ],
            'transaction_at' => 'nullable|date',
            'reference_id' => 'nullable|string|max:191',
            'notes' => 'nullable|string',
        ]);

        $jajmanId = $validated['jajman_id'] ?? null;

        // Verify Jajman ownership if provided
        if ($jajmanId) {
            $isAuthorized = DB::table('professional_jajmans')
                ->where('admin_id', $admin->id)
                ->where('jajman_id', $jajmanId)
                ->exists();

            if (!$isAuthorized) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected Jajman does not belong to your workspace.',
                ], 404);
            }
        }

        // Verify Appointment ownership if provided
        $appointment = null;
        if (!empty($validated['appointment_id'])) {
            $appointment = Appointment::where('admin_id', $admin->id)->find($validated['appointment_id']);
            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected appointment was not found in your schedule.',
                ], 404);
            }
            if (!$jajmanId && $appointment->jajman_id) {
                $jajmanId = $appointment->jajman_id;
            }
        }

        // Verify Consultation ownership if provided
        $consultation = null;
        if (!empty($validated['consultation_id'])) {
            $consultation = Consultation::where('admin_id', $admin->id)->find($validated['consultation_id']);
            if (!$consultation) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected consultation was not found in your records.',
                ], 404);
            }
            if (!$jajmanId && $consultation->jajman_id) {
                $jajmanId = $consultation->jajman_id;
            }
        }

        return DB::transaction(function () use ($admin, $validated, $jajmanId, $appointment, $consultation) {
            $transaction = ProfessionalTransaction::create([
                'admin_id' => $admin->id,
                'jajman_id' => $jajmanId,
                'appointment_id' => $appointment?->id,
                'consultation_id' => $consultation?->id,
                'transaction_type' => $validated['transaction_type'],
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_status'] ?? 'completed',
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? 'INR',
                'transaction_at' => !empty($validated['transaction_at'])
                    ? Carbon::parse($validated['transaction_at'])
                    : now(),
                'reference_id' => $validated['reference_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Sync payment status on Appointment if linked
            if ($appointment) {
                $apptStatus = $appointment->calculateFinancialStatus();
                $appointment->payment_status = $apptStatus['payment_status'];
                $appointment->save();
            }

            // Sync payment status on Consultation if linked
            if ($consultation) {
                $consStatus = $consultation->calculateFinancialStatus();
                $consultation->payment_status = $consStatus['payment_status'];
                $consultation->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully.',
                'data' => $transaction->load([
                    'jajman:id,name,phone,city',
                    'appointment:id,title,start_at',
                    'consultation:id,client_name,type',
                ]),
            ], 201);
        });
    }

    /**
     * 3. GET /api/astrologer/payments/{id}
     * Retrieve single transaction details.
     */
    public function show(Request $request, $id)
    {
        $admin = $request->user();

        $transaction = ProfessionalTransaction::where('admin_id', $admin->id)
            ->with([
                'jajman:id,name,phone,city,user_id',
                'user:id,name,phone,profile_image',
                'service:id,name,pricing_model,category',
                'chatSession',
                'appointment',
                'consultation',
                'parentTransaction',
                'refunds',
            ])
            ->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found or unauthorized.',
            ], 404);
        }

        // Sanitize personal contact info per Task B2 consent
        $this->sanitizeConsentForTransactions(collect([$transaction]), $admin->id);

        $data = $transaction->toArray();
        $data['refundable_balance'] = $transaction->refundableBalance();
        $data['total_refunded'] = $transaction->totalRefunded();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 4. POST /api/astrologer/payments/{id}/refund
     * Record a full or partial refund against an existing payment.
     */
    public function refund(Request $request, $id)
    {
        $admin = $request->user();

        $original = ProfessionalTransaction::where('admin_id', $admin->id)->find($id);

        if (!$original) {
            return response()->json([
                'success' => false,
                'message' => 'Original transaction not found or unauthorized.',
            ], 404);
        }

        if ($original->transaction_type === 'refund') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot issue a refund against a refund record.',
            ], 422);
        }

        if (!in_array($original->payment_status, ['completed', 'partially_refunded'])) {
            return response()->json([
                'success' => false,
                'message' => 'Refunds can only be issued against completed or partially refunded transactions.',
            ], 422);
        }

        $refundableBalance = $original->refundableBalance();
        if ($refundableBalance <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This transaction has already been fully refunded.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'reason' => 'required|string|max:500',
            'refund_at' => 'nullable|date',
            'reference_id' => 'nullable|string|max:191',
            'payment_method' => [
                'nullable',
                Rule::in(['cash', 'upi', 'bank_transfer', 'card', 'cheque', 'other']),
            ],
        ]);

        if ($validated['amount'] > $refundableBalance) {
            return response()->json([
                'success' => false,
                'message' => "Refund amount (₹{$validated['amount']}) exceeds available refundable balance of ₹{$refundableBalance}.",
            ], 422);
        }

        return DB::transaction(function () use ($admin, $original, $validated, $refundableBalance) {
            $refundTransaction = ProfessionalTransaction::create([
                'admin_id' => $admin->id,
                'jajman_id' => $original->jajman_id,
                'user_id' => $original->user_id,
                'appointment_id' => $original->appointment_id,
                'consultation_id' => $original->consultation_id,
                'service_id' => $original->service_id,
                'chat_session_id' => $original->chat_session_id,
                'chat_id' => $original->chat_id,
                'parent_transaction_id' => $original->id,
                'transaction_type' => 'refund',
                'payment_method' => $validated['payment_method'] ?? $original->payment_method,
                'payment_status' => 'completed',
                'amount' => $validated['amount'],
                'currency' => $original->currency,
                'transaction_at' => !empty($validated['refund_at'])
                    ? Carbon::parse($validated['refund_at'])
                    : now(),
                'reference_id' => $validated['reference_id'] ?? null,
                'notes' => 'Refund: ' . $validated['reason'],
            ]);

            // Update original payment status
            $remaining = round($refundableBalance - $validated['amount'], 2);
            $original->payment_status = $remaining <= 0 ? 'refunded' : 'partially_refunded';
            $original->save();

            // Sync Appointment payment_status if linked
            if ($original->appointment_id) {
                $appointment = Appointment::find($original->appointment_id);
                if ($appointment) {
                    $apptStatus = $appointment->calculateFinancialStatus();
                    $appointment->payment_status = $apptStatus['payment_status'];
                    $appointment->save();
                }
            }

            // Sync Consultation payment_status if linked
            if ($original->consultation_id) {
                $consultation = Consultation::find($original->consultation_id);
                if ($consultation) {
                    $consStatus = $consultation->calculateFinancialStatus();
                    $consultation->payment_status = $consStatus['payment_status'];
                    $consultation->save();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Refund recorded successfully.',
                'data' => $refundTransaction,
            ], 201);
        });
    }

    /**
     * 5. GET /api/astrologer/payments/summary
     * Financial dashboard metrics for earnings and outstanding balances.
     */
    public function summary(Request $request)
    {
        $admin = $request->user();
        $period = $request->query('period', 'all_time');

        $query = ProfessionalTransaction::where('admin_id', $admin->id)->with('service');

        [$startUtc, $endUtc, $tzName] = $this->resolveDateRange($request);
        if ($startUtc && $endUtc) {
            $query->whereBetween('transaction_at', [$startUtc, $endUtc]);
        } elseif ($startUtc) {
            $query->where('transaction_at', '>=', $startUtc);
        } elseif ($endUtc) {
            $query->where('transaction_at', '<=', $endUtc);
        }

        $transactions = $query->get();

        // Actual received earnings (completed payments excluding refunds)
        $totalReceived = (float) $transactions
            ->where('transaction_type', '!=', 'refund')
            ->where('payment_status', 'completed')
            ->sum('amount');

        // Total refunds issued
        $totalRefunded = (float) $transactions
            ->where('transaction_type', 'refund')
            ->where('payment_status', 'completed')
            ->sum('amount');

        $netReceived = round($totalReceived - $totalRefunded, 2);

        // Pending payments (not counted in received earnings)
        $totalPending = (float) $transactions
            ->where('payment_status', 'pending')
            ->sum('amount');

        // Total outstanding obligations across practitioner's appointments and consultations
        $allAppointments = Appointment::where('admin_id', $admin->id)
            ->where('status', '!=', 'cancelled')
            ->with(['transactions', 'service'])
            ->get();

        $totalApptOutstanding = 0.0;
        foreach ($allAppointments as $appt) {
            $status = $appt->calculateFinancialStatus();
            $totalApptOutstanding += $status['outstanding_balance'];
        }

        $allConsultations = Consultation::where('admin_id', $admin->id)
            ->where('status', '!=', 'cancelled')
            ->with('transactions')
            ->get();

        $totalConsOutstanding = 0.0;
        foreach ($allConsultations as $cons) {
            $status = $cons->calculateFinancialStatus();
            $totalConsOutstanding += $status['outstanding_balance'];
        }

        $totalOutstanding = round($totalApptOutstanding + $totalConsOutstanding, 2);

        // Payment method breakdown for completed received payments
        $methodBreakdown = $transactions
            ->where('transaction_type', '!=', 'refund')
            ->where('payment_status', 'completed')
            ->groupBy('payment_method')
            ->map(fn ($group) => round($group->sum('amount'), 2));

        $chatEarnings = (float) $transactions
            ->where('transaction_type', 'chat_message')
            ->where('payment_status', 'completed')
            ->sum('amount');

        $chatCount = $transactions
            ->where('transaction_type', 'chat_message')
            ->where('payment_status', 'completed')
            ->count();

        // Categorized Service Breakdown
        $completedTransactions = $transactions
            ->where('transaction_type', '!=', 'refund')
            ->where('payment_status', 'completed');

        $kundliEarnings = 0.0;
        $vastuEarnings = 0.0;
        $pujaEarnings = 0.0;
        $otherServiceEarnings = 0.0;

        foreach ($completedTransactions as $tx) {
            if ($tx->transaction_type === 'chat_message') {
                continue;
            }
            $cat = strtolower((string) ($tx->service?->category ?? 'other'));
            $amt = (float) $tx->amount;
            if (str_contains($cat, 'kundli')) {
                $kundliEarnings += $amt;
            } elseif (str_contains($cat, 'vastu')) {
                $vastuEarnings += $amt;
            } elseif (str_contains($cat, 'puja') || str_contains($cat, 'ritual')) {
                $pujaEarnings += $amt;
            } else {
                $otherServiceEarnings += $amt;
            }
        }

        $serviceOrdersEarnings = (float) $completedTransactions
            ->whereNotNull('service_order_id')
            ->sum('amount');

        $appointmentsEarnings = (float) $completedTransactions
            ->whereNotNull('appointment_id')
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'timezone' => $tzName,
                'start_at' => $startUtc?->toIso8601String(),
                'end_at' => $endUtc?->toIso8601String(),
                'total_received' => round($totalReceived, 2),
                'total_refunded' => round($totalRefunded, 2),
                'net_received' => $netReceived,
                'chat_earnings' => round($chatEarnings, 2),
                'chat_messages_count' => $chatCount,
                'service_orders_earnings' => round($serviceOrdersEarnings, 2),
                'appointments_earnings' => round($appointmentsEarnings, 2),
                'category_breakdown' => [
                    'chat' => round($chatEarnings, 2),
                    'kundli' => round($kundliEarnings, 2),
                    'vastu' => round($vastuEarnings, 2),
                    'puja' => round($pujaEarnings, 2),
                    'other' => round($otherServiceEarnings, 2),
                ],
                'total_pending' => round($totalPending, 2),
                'total_outstanding' => $totalOutstanding,
                'transactions_count' => $transactions->count(),
                'method_breakdown' => $methodBreakdown,
            ],
        ]);
    }

    /**
     * 6. GET /api/astrologer/jajmans/{id}/financial-summary
     * Financial history and balance for a specific Jajman client.
     */
    public function jajmanFinancialSummary(Request $request, $id)
    {
        $admin = $request->user();

        // Verify Jajman ownership
        $isAuthorized = DB::table('professional_jajmans')
            ->where('admin_id', $admin->id)
            ->where('jajman_id', $id)
            ->exists();

        if (!$isAuthorized) {
            return response()->json([
                'success' => false,
                'message' => 'Jajman not found in your workspace.',
            ], 404);
        }

        $jajman = Jajman::find($id);

        // Sum appointment fees
        $appointments = Appointment::where('admin_id', $admin->id)
            ->where('jajman_id', $id)
            ->where('status', '!=', 'cancelled')
            ->get();

        $totalCharged = 0.0;
        foreach ($appointments as $appt) {
            $totalCharged += (float) ($appt->agreed_price_snapshot ?? ($appt->service?->price ?? 0.0));
        }

        // Sum standalone consultation fees (not already linked to appointments)
        $consultations = Consultation::where('admin_id', $admin->id)
            ->where('jajman_id', $id)
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($consultations as $cons) {
            $totalCharged += (float) ($cons->fee_charged ?? 0.0);
        }

        // Transactions for this Jajman
        $transactions = ProfessionalTransaction::where('admin_id', $admin->id)
            ->where('jajman_id', $id)
            ->orderBy('transaction_at', 'desc')
            ->get();

        $totalReceived = (float) $transactions
            ->where('transaction_type', '!=', 'refund')
            ->where('payment_status', 'completed')
            ->sum('amount');

        $totalRefunded = (float) $transactions
            ->where('transaction_type', 'refund')
            ->where('payment_status', 'completed')
            ->sum('amount');

        $netReceived = round($totalReceived - $totalRefunded, 2);
        $outstanding = max(0.0, round($totalCharged - $netReceived, 2));

        return response()->json([
            'success' => true,
            'data' => [
                'jajman_id' => (int) $id,
                'jajman_name' => $jajman->name,
                'total_charged' => round($totalCharged, 2),
                'total_received' => round($totalReceived, 2),
                'total_refunded' => round($totalRefunded, 2),
                'net_received' => $netReceived,
                'outstanding_balance' => $outstanding,
                'transactions' => $transactions->take(10)->values(),
            ],
        ]);
    }

    /**
     * 7. GET /api/astrologer/appointments/{id}/financial-summary
     * Financial status for a specific appointment.
     */
    public function appointmentFinancialSummary(Request $request, $id)
    {
        $admin = $request->user();

        $appointment = Appointment::where('admin_id', $admin->id)
            ->with(['transactions', 'service'])
            ->find($id);

        if (!$appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found or unauthorized.',
            ], 404);
        }

        $financials = $appointment->calculateFinancialStatus();
        $financials['appointment_id'] = $appointment->id;
        $financials['title'] = $appointment->title;
        $financials['transactions'] = $appointment->transactions()->orderBy('transaction_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $financials,
        ]);
    }

    /**
     * 8. GET /api/astrologer/consultations/{id}/financial-summary
     * Financial status for a specific consultation.
     */
    public function consultationFinancialSummary(Request $request, $id)
    {
        $admin = $request->user();

        $consultation = Consultation::where('admin_id', $admin->id)
            ->with('transactions')
            ->find($id);

        if (!$consultation) {
            return response()->json([
                'success' => false,
                'message' => 'Consultation not found or unauthorized.',
            ], 404);
        }

        $financials = $consultation->calculateFinancialStatus();
        $financials['consultation_id'] = $consultation->id;
        $financials['client_name'] = $consultation->client_name;
        $financials['transactions'] = $consultation->transactions()->orderBy('transaction_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $financials,
        ]);
    }

    /**
     * 9. GET /api/astrologer/payments/chat-earnings
     * Read-only aggregated chat earnings by session or by customer with drill-down and consent masking.
     */
    public function chatEarnings(Request $request)
    {
        $admin = $request->user();
        $groupBy = $request->query('group_by', 'session');
        if (!in_array($groupBy, ['session', 'customer'])) {
            $groupBy = 'session';
        }

        [$startUtc, $endUtc, $tzName] = $this->resolveDateRange($request);
        $period = $request->query('period', 'all_time');

        // Query chat_message transactions and associated refunds
        $query = ProfessionalTransaction::where('admin_id', $admin->id)
            ->where(function ($q) {
                $q->where('transaction_type', 'chat_message')
                  ->orWhere(function ($rq) {
                      $rq->where('transaction_type', 'refund')
                         ->where(function ($sq) {
                             $sq->whereNotNull('chat_session_id')
                                ->orWhereHas('parentTransaction', fn ($pq) => $pq->where('transaction_type', 'chat_message'));
                         });
                  });
            })
            ->with([
                'user:id,name,phone,profile_image',
                'jajman:id,name,phone,city,user_id',
                'chatSession:id,user_id,admin_id,astrologer_id,started_at,ended_at,total_minutes,total_fee',
                'parentTransaction:id,chat_session_id,chat_id,user_id,amount,transaction_type',
                'service:id,name,pricing_model,category',
            ]);

        // Date boundaries
        if ($startUtc && $endUtc) {
            $query->whereBetween('transaction_at', [$startUtc, $endUtc]);
        } elseif ($startUtc) {
            $query->where('transaction_at', '>=', $startUtc);
        } elseif ($endUtc) {
            $query->where('transaction_at', '<=', $endUtc);
        }

        // Drill-down filters
        if ($sessionId = $request->query('chat_session_id')) {
            $query->where(function ($sq) use ($sessionId) {
                $sq->where('chat_session_id', $sessionId)
                   ->orWhereHas('parentTransaction', fn ($pq) => $pq->where('chat_session_id', $sessionId));
            });
        }
        if ($userId = $request->query('user_id')) {
            $query->where(function ($uq) use ($userId) {
                $uq->where('user_id', $userId)
                   ->orWhereHas('parentTransaction', fn ($pq) => $pq->where('user_id', $userId));
            });
        }
        if ($jajmanId = $request->query('jajman_id')) {
            $query->where(function ($jq) use ($jajmanId) {
                $jq->where('jajman_id', $jajmanId)
                   ->orWhereHas('parentTransaction', fn ($pq) => $pq->where('jajman_id', $jajmanId));
            });
        }

        $allTx = $query->orderBy('transaction_at', 'desc')->get();

        // Enforce Task B2 consent masking
        $this->sanitizeConsentForTransactions($allTx, $admin->id);

        $withTransactions = $request->boolean('with_transactions', $request->has('chat_session_id') || $request->has('user_id'));

        // Map any unassociated Jajman IDs for customer accounts
        $distinctUserIds = $allTx->pluck('user_id')->filter()->unique();
        $userJajmanMap = DB::table('professional_jajmans')
            ->join('jajmans', 'jajmans.id', '=', 'professional_jajmans.jajman_id')
            ->where('professional_jajmans.admin_id', $admin->id)
            ->whereIn('jajmans.user_id', $distinctUserIds)
            ->pluck('jajmans.id', 'jajmans.user_id');

        $groupedItems = collect();

        if ($groupBy === 'session') {
            $bySession = $allTx->groupBy(function ($tx) {
                return $tx->chat_session_id ?? $tx->parentTransaction?->chat_session_id ?? 0;
            });

            foreach ($bySession as $sessId => $txList) {
                $firstChatTx = $txList->firstWhere('transaction_type', 'chat_message') ?? $txList->first();
                $userId = $firstChatTx->user_id ?? $firstChatTx->parentTransaction?->user_id;
                $jajmanId = $firstChatTx->jajman_id ?? ($userId ? ($userJajmanMap[$userId] ?? null) : null);

                $customerName = $firstChatTx->jajman?->name
                    ?? $firstChatTx->user?->name
                    ?? 'Astro Setu Client';

                $completedTx = $txList->where('transaction_type', 'chat_message')->whereIn('payment_status', ['completed', 'partially_refunded', 'refunded']);
                $refundedTx = $txList->where('transaction_type', 'refund')->where('payment_status', 'completed');
                $pendingTx = $txList->where('payment_status', 'pending');

                $completedEarnings = (float) $completedTx->sum('amount');
                $refundedAmount = (float) $refundedTx->sum('amount');
                $pendingEarnings = (float) $pendingTx->sum('amount');
                $netEarnings = round($completedEarnings - $refundedAmount, 2);

                $grossAmount = (float) $completedTx->sum('gross_amount');
                $platformFee = (float) $completedTx->sum('platform_fee');

                $sessionObj = $firstChatTx->chatSession;

                $item = [
                    'session_id' => (int) $sessId,
                    'user_id' => $userId ? (int) $userId : null,
                    'jajman_id' => $jajmanId ? (int) $jajmanId : null,
                    'customer_name' => $customerName,
                    'customer_phone' => $firstChatTx->user?->phone ?? $firstChatTx->jajman?->phone,
                    'customer_profile_image' => $firstChatTx->user?->profile_image,
                    'session_started_at' => $sessionObj?->started_at ? Carbon::parse($sessionObj->started_at)->toIso8601String() : null,
                    'session_ended_at' => $sessionObj?->ended_at ? Carbon::parse($sessionObj->ended_at)->toIso8601String() : null,
                    'message_count' => $txList->where('transaction_type', 'chat_message')->count(),
                    'transaction_count' => $txList->count(),
                    'gross_amount' => round($grossAmount, 2),
                    'platform_fee' => round($platformFee, 2),
                    'completed_earnings' => round($completedEarnings, 2),
                    'pending_earnings' => round($pendingEarnings, 2),
                    'refunded_amount' => round($refundedAmount, 2),
                    'net_earnings' => $netEarnings,
                    'first_transaction_at' => $txList->min('transaction_at') ? Carbon::parse($txList->min('transaction_at'))->toIso8601String() : null,
                    'last_transaction_at' => $txList->max('transaction_at') ? Carbon::parse($txList->max('transaction_at'))->toIso8601String() : null,
                    'status_summary' => [
                        'completed' => $completedTx->count(),
                        'pending' => $pendingTx->count(),
                        'refunded' => $txList->where('payment_status', 'refunded')->count(),
                        'partially_refunded' => $txList->where('payment_status', 'partially_refunded')->count(),
                    ],
                ];

                if ($withTransactions) {
                    $item['transactions'] = $txList->values();
                }

                $groupedItems->push($item);
            }
        } else {
            // group_by === 'customer'
            $byUser = $allTx->groupBy(function ($tx) {
                return $tx->user_id ?? $tx->parentTransaction?->user_id ?? 0;
            });

            foreach ($byUser as $uId => $txList) {
                $firstTx = $txList->first();
                $jajmanId = $firstTx->jajman_id ?? ($uId ? ($userJajmanMap[$uId] ?? null) : null);

                $customerName = $firstTx->jajman?->name
                    ?? $firstTx->user?->name
                    ?? 'Astro Setu Client';

                $completedTx = $txList->where('transaction_type', 'chat_message')->whereIn('payment_status', ['completed', 'partially_refunded', 'refunded']);
                $refundedTx = $txList->where('transaction_type', 'refund')->where('payment_status', 'completed');
                $pendingTx = $txList->where('payment_status', 'pending');

                $completedEarnings = (float) $completedTx->sum('amount');
                $refundedAmount = (float) $refundedTx->sum('amount');
                $pendingEarnings = (float) $pendingTx->sum('amount');
                $netEarnings = round($completedEarnings - $refundedAmount, 2);

                $grossAmount = (float) $completedTx->sum('gross_amount');
                $platformFee = (float) $completedTx->sum('platform_fee');

                $distinctSessions = $txList->pluck('chat_session_id')
                    ->merge($txList->pluck('parentTransaction.chat_session_id'))
                    ->filter()
                    ->unique();

                $item = [
                    'user_id' => $uId ? (int) $uId : null,
                    'jajman_id' => $jajmanId ? (int) $jajmanId : null,
                    'customer_name' => $customerName,
                    'customer_phone' => $firstTx->user?->phone ?? $firstTx->jajman?->phone,
                    'customer_profile_image' => $firstTx->user?->profile_image,
                    'session_count' => $distinctSessions->count(),
                    'session_ids' => $distinctSessions->values(),
                    'message_count' => $txList->where('transaction_type', 'chat_message')->count(),
                    'transaction_count' => $txList->count(),
                    'gross_amount' => round($grossAmount, 2),
                    'platform_fee' => round($platformFee, 2),
                    'completed_earnings' => round($completedEarnings, 2),
                    'pending_earnings' => round($pendingEarnings, 2),
                    'refunded_amount' => round($refundedAmount, 2),
                    'net_earnings' => $netEarnings,
                    'first_transaction_at' => $txList->min('transaction_at') ? Carbon::parse($txList->min('transaction_at'))->toIso8601String() : null,
                    'last_transaction_at' => $txList->max('transaction_at') ? Carbon::parse($txList->max('transaction_at'))->toIso8601String() : null,
                    'status_summary' => [
                        'completed' => $completedTx->count(),
                        'pending' => $pendingTx->count(),
                        'refunded' => $txList->where('payment_status', 'refunded')->count(),
                        'partially_refunded' => $txList->where('payment_status', 'partially_refunded')->count(),
                    ],
                ];

                if ($withTransactions) {
                    $item['transactions'] = $txList->values();
                }

                $groupedItems->push($item);
            }
        }

        // Summary totals across all transactions
        $allCompletedChat = $allTx->where('transaction_type', 'chat_message')->whereIn('payment_status', ['completed', 'partially_refunded', 'refunded']);
        $allRefunded = $allTx->where('transaction_type', 'refund')->where('payment_status', 'completed');
        $allPending = $allTx->where('payment_status', 'pending');

        $totalCompleted = (float) $allCompletedChat->sum('amount');
        $totalRefunded = (float) $allRefunded->sum('amount');
        $totalPending = (float) $allPending->sum('amount');
        $totalGross = (float) $allCompletedChat->sum('gross_amount');
        $totalPlatformFee = (float) $allCompletedChat->sum('platform_fee');
        $totalNet = round($totalCompleted - $totalRefunded, 2);

        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);
        $total = $groupedItems->count();
        $paginatedItems = $groupedItems->forPage($page, $perPage)->values();

        return response()->json([
            'success' => true,
            'summary' => [
                'period' => $period,
                'group_by' => $groupBy,
                'timezone' => $tzName,
                'start_at' => $startUtc?->toIso8601String(),
                'end_at' => $endUtc?->toIso8601String(),
                'total_gross_amount' => round($totalGross, 2),
                'total_platform_fee' => round($totalPlatformFee, 2),
                'total_completed_earnings' => round($totalCompleted, 2),
                'total_pending_earnings' => round($totalPending, 2),
                'total_refunded_amount' => round($totalRefunded, 2),
                'total_net_earnings' => round($totalNet, 2),
                'total_transactions' => $allTx->count(),
                'total_sessions' => $allTx->pluck('chat_session_id')->filter()->unique()->count(),
                'total_customers' => $allTx->pluck('user_id')->filter()->unique()->count(),
            ],
            'data' => $paginatedItems,
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) ceil($total / max(1, $perPage)),
                'total' => $total,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Parse period or custom dates into UTC query boundaries for the given practitioner timezone.
     *
     * @return array{0: ?Carbon, 1: ?Carbon, 2: string} [?startUtc, ?endUtc, timezone]
     */
    protected function resolveDateRange(Request $request): array
    {
        $tzName = $request->query('timezone', 'Asia/Kolkata');
        try {
            new \DateTimeZone($tzName);
        } catch (\Throwable $e) {
            $tzName = 'Asia/Kolkata';
        }

        $period = $request->query('period');
        $nowLocal = Carbon::now($tzName);

        $startUtc = null;
        $endUtc = null;

        switch ($period) {
            case 'today':
                $startUtc = $nowLocal->copy()->startOfDay()->setTimezone('UTC');
                $endUtc = $nowLocal->copy()->endOfDay()->setTimezone('UTC');
                break;
            case 'yesterday':
                $startUtc = $nowLocal->copy()->subDay()->startOfDay()->setTimezone('UTC');
                $endUtc = $nowLocal->copy()->subDay()->endOfDay()->setTimezone('UTC');
                break;
            case 'this_week':
                $startUtc = $nowLocal->copy()->startOfWeek()->setTimezone('UTC');
                $endUtc = $nowLocal->copy()->endOfWeek()->setTimezone('UTC');
                break;
            case 'this_month':
                $startUtc = $nowLocal->copy()->startOfMonth()->setTimezone('UTC');
                $endUtc = $nowLocal->copy()->endOfMonth()->setTimezone('UTC');
                break;
            case 'custom':
                if ($from = $request->query('from')) {
                    try {
                        $startUtc = $this->parseDateBoundary($from, $tzName, false);
                    } catch (\Throwable $e) {}
                }
                if ($to = $request->query('to')) {
                    try {
                        $endUtc = $this->parseDateBoundary($to, $tzName, true);
                    } catch (\Throwable $e) {}
                }
                break;
            case 'all_time':
            default:
                if ($from = $request->query('from')) {
                    try {
                        $startUtc = $this->parseDateBoundary($from, $tzName, false);
                    } catch (\Throwable $e) {}
                }
                if ($to = $request->query('to')) {
                    try {
                        $endUtc = $this->parseDateBoundary($to, $tzName, true);
                    } catch (\Throwable $e) {}
                }
                break;
        }

        return [$startUtc, $endUtc, $tzName];
    }

    /**
     * Safely parse a date boundary string, respecting timezone offsets or local timezone.
     */
    protected function parseDateBoundary(string $dateString, string $timezone, bool $isEnd = false): Carbon
    {
        if (preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $dateString)) {
            $dt = Carbon::parse($dateString);
            return $isEnd ? $dt->copy()->endOfDay()->setTimezone('UTC') : $dt->copy()->startOfDay()->setTimezone('UTC');
        }
        $dt = Carbon::parse($dateString, $timezone);
        return $isEnd ? $dt->copy()->endOfDay()->setTimezone('UTC') : $dt->copy()->startOfDay()->setTimezone('UTC');
    }

    /**
     * Enforce Task B2 personal-data consent privacy on consumer contact details across transactions.
     * Retains financial auditing fields and manual practitioner Jajman data.
     */
    protected function sanitizeConsentForTransactions(Collection $transactions, int $adminId): Collection
    {
        if ($transactions->isEmpty()) {
            return $transactions;
        }

        $userIds = $transactions->pluck('user_id')
            ->merge($transactions->pluck('parentTransaction.user_id'))
            ->merge($transactions->pluck('jajman.user_id'))
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return $transactions;
        }

        $grantedUserIds = CustomerAstrologerConsent::where('admin_id', $adminId)
            ->whereIn('user_id', $userIds)
            ->where('status', CustomerAstrologerConsent::STATUS_GRANTED)
            ->pluck('user_id')
            ->flip();

        foreach ($transactions as $tx) {
            // Linked consumer user
            if ($tx->user_id && $tx->relationLoaded('user') && $tx->user) {
                if (!$grantedUserIds->has($tx->user_id)) {
                    $tx->user->phone = null;
                    $tx->user->email = null;
                }
            }

            // Linked Jajman with consumer account
            if ($tx->relationLoaded('jajman') && $tx->jajman && $tx->jajman->user_id) {
                if (!$grantedUserIds->has($tx->jajman->user_id)) {
                    $tx->jajman->phone = null;
                    $tx->jajman->email = null;
                }
            }

            // Linked child refunds
            if ($tx->relationLoaded('refunds') && $tx->refunds) {
                foreach ($tx->refunds as $refund) {
                    if ($refund->user_id && $refund->relationLoaded('user') && $refund->user) {
                        if (!$grantedUserIds->has($refund->user_id)) {
                            $refund->user->phone = null;
                            $refund->user->email = null;
                        }
                    }
                    if ($refund->relationLoaded('jajman') && $refund->jajman && $refund->jajman->user_id) {
                        if (!$grantedUserIds->has($refund->jajman->user_id)) {
                            $refund->jajman->phone = null;
                            $refund->jajman->email = null;
                        }
                    }
                }
            }
        }

        return $transactions;
    }
}
