<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Jajman;
use App\Models\ProfessionalTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
                'jajman:id,name,phone,city',
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

        // Date range filter
        if ($from = $request->query('from')) {
            try {
                $query->where('transaction_at', '>=', Carbon::parse($from)->startOfDay());
            } catch (\Exception $e) {}
        }
        if ($to = $request->query('to')) {
            try {
                $query->where('transaction_at', '<=', Carbon::parse($to)->endOfDay());
            } catch (\Exception $e) {}
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
                ]),
            ],
            'payment_method' => [
                'required',
                Rule::in(['cash', 'upi', 'bank_transfer', 'card', 'cheque', 'other']),
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
                'jajman',
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
                'appointment_id' => $original->appointment_id,
                'consultation_id' => $original->consultation_id,
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

        $query = ProfessionalTransaction::where('admin_id', $admin->id);

        $now = Carbon::now();
        switch ($period) {
            case 'today':
                $query->whereBetween('transaction_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
                break;
            case 'this_week':
                $query->whereBetween('transaction_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
                break;
            case 'this_month':
                $query->whereBetween('transaction_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
                break;
            case 'custom':
                if ($from = $request->query('from')) {
                    $query->where('transaction_at', '>=', Carbon::parse($from)->startOfDay());
                }
                if ($to = $request->query('to')) {
                    $query->where('transaction_at', '<=', Carbon::parse($to)->endOfDay());
                }
                break;
            case 'all_time':
            default:
                break;
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

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'total_received' => round($totalReceived, 2),
                'total_refunded' => round($totalRefunded, 2),
                'net_received' => $netReceived,
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
}
