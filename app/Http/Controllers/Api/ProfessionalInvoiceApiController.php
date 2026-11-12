<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Jajman;
use App\Models\ProfessionalDocumentSetting;
use App\Models\ProfessionalInvoice;
use App\Models\ProfessionalInvoiceItem;
use App\Models\ProfessionalService;
use App\Models\ProfessionalTransaction;
use App\Services\DocumentPdfService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfessionalInvoiceApiController extends Controller
{
    public function __construct(
        protected DocumentPdfService $pdfService
    ) {}

    /**
     * 1. GET /api/astrologer/invoices
     * List invoices for authenticated practitioner with search & status filters.
     */
    public function index(Request $request)
    {
        $admin = $request->user();

        $query = ProfessionalInvoice::where('admin_id', $admin->id)
            ->with(['jajman:id,name,phone,city', 'items:id,invoice_id,description,quantity,unit_price,line_total']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('jajman_id')) {
            $query->where('jajman_id', $request->query('jajman_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('invoice_date', '>=', Carbon::parse($request->query('from'))->toDateString());
        }

        if ($request->filled('to')) {
            $query->whereDate('invoice_date', '<=', Carbon::parse($request->query('to'))->toDateString());
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', $search)
                    ->orWhereHas('jajman', function ($jq) use ($search) {
                        $jq->where('name', 'LIKE', $search)
                            ->orWhere('phone', 'LIKE', $search);
                    });
            });
        }

        $invoices = $query->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * 2. POST /api/astrologer/invoices
     * Create a new invoice (draft or issued).
     */
    public function store(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'jajman_id' => 'nullable|integer',
            'appointment_id' => 'nullable|integer',
            'consultation_id' => 'nullable|integer',
            'service_id' => 'nullable|integer',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'currency' => 'nullable|string|max:10',
            'status' => ['nullable', Rule::in(['draft', 'issued'])],
            'discount_type' => ['nullable', Rule::in(['none', 'fixed', 'percentage'])],
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_enabled' => 'nullable|boolean',
            'tax_name' => 'nullable|string|max:50',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_tax_inclusive' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|gte:0',
            'items.*.service_id' => 'nullable|integer',
        ]);

        // Validate multi-tenant ownership
        $jajman = null;
        if (!empty($validated['jajman_id'])) {
            $jajman = Jajman::whereHas('professionals', function ($q) use ($admin) {
                $q->where('admin_id', $admin->id);
            })->find($validated['jajman_id']);

            if (!$jajman) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected Jajman is invalid or does not belong to your practice.',
                ], 422);
            }
        }

        $appointment = null;
        if (!empty($validated['appointment_id'])) {
            $appointment = Appointment::where('admin_id', $admin->id)->find($validated['appointment_id']);
            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'The linked appointment is invalid or does not belong to your practice.',
                ], 422);
            }
        }

        $consultation = null;
        if (!empty($validated['consultation_id'])) {
            $consultation = Consultation::where('admin_id', $admin->id)->find($validated['consultation_id']);
            if (!$consultation) {
                return response()->json([
                    'success' => false,
                    'message' => 'The linked consultation is invalid or does not belong to your practice.',
                ], 422);
            }
        }

        $service = null;
        if (!empty($validated['service_id'])) {
            $service = ProfessionalService::where('admin_id', $admin->id)->find($validated['service_id']);
        }

        $docSettings = ProfessionalDocumentSetting::getOrCreateForAdmin($admin);

        return DB::transaction(function () use ($admin, $validated, $jajman, $appointment, $consultation, $service, $docSettings) {
            $invoiceDate = Carbon::parse($validated['invoice_date']);
            $isIssued = ($validated['status'] ?? 'draft') === 'issued';

            // Generate atomic invoice number
            $invoiceNumber = $isIssued
                ? ProfessionalDocumentSetting::generateNextInvoiceNumber($admin, $invoiceDate)
                : 'DRAFT-' . strtoupper(bin2hex(random_bytes(4)));

            $taxEnabled = $validated['tax_enabled'] ?? $docSettings->tax_enabled;
            $taxName = $validated['tax_name'] ?? $docSettings->tax_name;
            $taxRate = $validated['tax_rate'] ?? ($taxEnabled ? $docSettings->default_tax_rate : 0.0);
            $taxInclusive = $validated['is_tax_inclusive'] ?? $docSettings->is_tax_inclusive;

            $invoice = new ProfessionalInvoice([
                'admin_id' => $admin->id,
                'jajman_id' => $jajman?->id,
                'appointment_id' => $appointment?->id,
                'consultation_id' => $consultation?->id,
                'service_id' => $service?->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'due_date' => !empty($validated['due_date']) ? Carbon::parse($validated['due_date']) : null,
                'currency' => $validated['currency'] ?? 'INR',
                'status' => $isIssued ? 'issued' : 'draft',
                'discount_type' => $validated['discount_type'] ?? 'none',
                'discount_rate' => $validated['discount_rate'] ?? 0.0,
                'discount_amount' => $validated['discount_amount'] ?? 0.0,
                'tax_enabled' => $taxEnabled,
                'tax_name' => $taxName,
                'tax_rate' => $taxRate,
                'is_tax_inclusive' => $taxInclusive,
                'notes' => $validated['notes'] ?? $docSettings->invoice_notes,
                'payment_terms' => $validated['payment_terms'] ?? $docSettings->invoice_terms,
            ]);

            // Save snapshots
            $invoice->snapshotProfessionalDetails($admin, $docSettings);
            $invoice->snapshotJajmanDetails($jajman);
            $invoice->save();

            // Insert line items
            foreach ($validated['items'] as $itemData) {
                $invoice->items()->create([
                    'service_id' => $itemData['service_id'] ?? null,
                    'description' => trim($itemData['description']),
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                ]);
            }

            // Recalculate totals and balances
            $invoice->load('items');
            $invoice->recalculateTotals()->save();

            return response()->json([
                'success' => true,
                'message' => $isIssued ? 'Invoice issued successfully.' : 'Draft invoice created successfully.',
                'data' => $invoice->load(['items', 'jajman', 'appointment', 'consultation']),
            ], 201);
        });
    }

    /**
     * 3. GET /api/astrologer/invoices/{id}
     * Retrieve single invoice details with line items and payment transactions.
     */
    public function show(Request $request, $id)
    {
        $admin = $request->user();

        $invoice = ProfessionalInvoice::where('admin_id', $admin->id)
            ->with([
                'items',
                'jajman',
                'appointment',
                'consultation',
                'transactions' => function ($q) {
                    $q->orderByDesc('transaction_at');
                },
            ])
            ->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found or unauthorized.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    /**
     * 4. PUT /api/astrologer/invoices/{id}
     * Update a draft invoice.
     */
    public function update(Request $request, $id)
    {
        $admin = $request->user();

        $invoice = ProfessionalInvoice::where('admin_id', $admin->id)->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found or unauthorized.',
            ], 404);
        }

        if (!$invoice->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Issued invoices are immutable. To make corrections, cancel and issue a replacement invoice.',
            ], 422);
        }

        $validated = $request->validate([
            'jajman_id' => 'nullable|integer',
            'appointment_id' => 'nullable|integer',
            'consultation_id' => 'nullable|integer',
            'service_id' => 'nullable|integer',
            'invoice_date' => 'sometimes|date',
            'due_date' => 'nullable|date',
            'currency' => 'nullable|string|max:10',
            'discount_type' => ['nullable', Rule::in(['none', 'fixed', 'percentage'])],
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_enabled' => 'nullable|boolean',
            'tax_name' => 'nullable|string|max:50',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_tax_inclusive' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|gte:0',
            'items.*.service_id' => 'nullable|integer',
        ]);

        $jajman = null;
        if (!empty($validated['jajman_id'])) {
            $jajman = Jajman::whereHas('professionals', function ($q) use ($admin) {
                $q->where('admin_id', $admin->id);
            })->find($validated['jajman_id']);

            if (!$jajman) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected Jajman is invalid or unauthorized.',
                ], 422);
            }
        }

        return DB::transaction(function () use ($invoice, $validated, $jajman, $admin) {
            if (isset($validated['invoice_date'])) {
                $invoice->invoice_date = Carbon::parse($validated['invoice_date']);
            }
            if (array_key_exists('due_date', $validated)) {
                $invoice->due_date = !empty($validated['due_date']) ? Carbon::parse($validated['due_date']) : null;
            }
            if (isset($validated['currency'])) $invoice->currency = $validated['currency'];
            if (isset($validated['discount_type'])) $invoice->discount_type = $validated['discount_type'];
            if (isset($validated['discount_rate'])) $invoice->discount_rate = $validated['discount_rate'];
            if (isset($validated['discount_amount'])) $invoice->discount_amount = $validated['discount_amount'];
            if (isset($validated['tax_enabled'])) $invoice->tax_enabled = $validated['tax_enabled'];
            if (isset($validated['tax_name'])) $invoice->tax_name = $validated['tax_name'];
            if (isset($validated['tax_rate'])) $invoice->tax_rate = $validated['tax_rate'];
            if (isset($validated['is_tax_inclusive'])) $invoice->is_tax_inclusive = $validated['is_tax_inclusive'];
            if (isset($validated['notes'])) $invoice->notes = $validated['notes'];
            if (isset($validated['payment_terms'])) $invoice->payment_terms = $validated['payment_terms'];

            if ($jajman) {
                $invoice->jajman_id = $jajman->id;
                $invoice->snapshotJajmanDetails($jajman);
            }

            $invoice->save();

            // Replace line items if provided
            if (isset($validated['items'])) {
                $invoice->items()->delete();
                foreach ($validated['items'] as $itemData) {
                    $invoice->items()->create([
                        'service_id' => $itemData['service_id'] ?? null,
                        'description' => trim($itemData['description']),
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                    ]);
                }
            }

            $invoice->load('items');
            $invoice->recalculateTotals()->save();

            return response()->json([
                'success' => true,
                'message' => 'Draft invoice updated successfully.',
                'data' => $invoice->load(['items', 'jajman']),
            ]);
        });
    }

    /**
     * 5. POST /api/astrologer/invoices/{id}/issue
     * Transition draft invoice to official issued status.
     */
    public function issue(Request $request, $id)
    {
        $admin = $request->user();

        $invoice = ProfessionalInvoice::where('admin_id', $admin->id)->with('items')->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found or unauthorized.',
            ], 404);
        }

        if (!$invoice->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => "Invoice is already in '{$invoice->status}' status.",
            ], 422);
        }

        if ($invoice->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot issue an invoice with zero line items.',
            ], 422);
        }

        return DB::transaction(function () use ($admin, $invoice) {
            $docSettings = ProfessionalDocumentSetting::getOrCreateForAdmin($admin);

            // Assign official sequential invoice number
            $invoice->invoice_number = ProfessionalDocumentSetting::generateNextInvoiceNumber(
                $admin,
                $invoice->invoice_date ? Carbon::parse($invoice->invoice_date) : now()
            );

            $invoice->status = 'issued';
            $invoice->snapshotProfessionalDetails($admin, $docSettings);
            $invoice->recalculateTotals();
            $invoice->save();
            $invoice->syncPaymentStatus();

            return response()->json([
                'success' => true,
                'message' => "Invoice {$invoice->invoice_number} issued successfully.",
                'data' => $invoice->load(['items', 'jajman']),
            ]);
        });
    }

    /**
     * 6. POST /api/astrologer/invoices/{id}/cancel
     * Cancel an invoice with reason.
     */
    public function cancel(Request $request, $id)
    {
        $admin = $request->user();

        $invoice = ProfessionalInvoice::where('admin_id', $admin->id)->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found or unauthorized.',
            ], 404);
        }

        if ($invoice->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'This invoice has already been cancelled.',
            ], 422);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        $invoice->status = 'cancelled';
        $invoice->cancelled_at = now();
        $invoice->cancellation_reason = trim($validated['cancellation_reason']);
        $invoice->save();

        return response()->json([
            'success' => true,
            'message' => "Invoice {$invoice->invoice_number} has been cancelled.",
            'data' => $invoice,
        ]);
    }

    /**
     * 7. POST /api/astrologer/invoices/{id}/payments
     * Record a payment directly against an invoice using Phase 8 transaction infrastructure.
     */
    public function recordPayment(Request $request, $id)
    {
        $admin = $request->user();

        $invoice = ProfessionalInvoice::where('admin_id', $admin->id)->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found or unauthorized.',
            ], 404);
        }

        if ($invoice->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot record payment against a cancelled invoice.',
            ], 422);
        }

        if ($invoice->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Please issue the invoice before recording payments against it.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'payment_method' => [
                'required',
                Rule::in(['cash', 'upi', 'bank_transfer', 'card', 'cheque', 'other']),
            ],
            'transaction_type' => [
                'nullable',
                Rule::in(['service_payment', 'appointment_advance', 'consultation_payment', 'general_payment', 'adjustment']),
            ],
            'payment_status' => [
                'nullable',
                Rule::in(['completed', 'pending']),
            ],
            'currency' => 'nullable|string|max:10',
            'transaction_at' => 'nullable|date',
            'reference_id' => 'nullable|string|max:191',
            'notes' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($admin, $invoice, $validated) {
            $transactionAt = !empty($validated['transaction_at'])
                ? Carbon::parse($validated['transaction_at'])
                : now();

            $isCompleted = ($validated['payment_status'] ?? 'completed') === 'completed';

            // Generate receipt number atomically for completed payment
            $receiptNumber = $isCompleted
                ? ProfessionalDocumentSetting::generateNextReceiptNumber($admin, $transactionAt)
                : null;

            $transaction = ProfessionalTransaction::create([
                'admin_id' => $admin->id,
                'jajman_id' => $invoice->jajman_id,
                'appointment_id' => $invoice->appointment_id,
                'consultation_id' => $invoice->consultation_id,
                'invoice_id' => $invoice->id,
                'transaction_type' => $validated['transaction_type'] ?? 'service_payment',
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_status'] ?? 'completed',
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? $invoice->currency,
                'transaction_at' => $transactionAt,
                'reference_id' => $validated['reference_id'] ?? null,
                'receipt_number' => $receiptNumber,
                'notes' => $validated['notes'] ?? "Payment for Invoice #{$invoice->invoice_number}",
            ]);

            // Synchronize invoice balance and status
            $invoice->syncPaymentStatus();

            // Synchronize linked appointment if present
            if ($invoice->appointment) {
                $apptStatus = $invoice->appointment->calculateFinancialStatus();
                $invoice->appointment->payment_status = $apptStatus['payment_status'];
                $invoice->appointment->save();
            }

            // Synchronize linked consultation if present
            if ($invoice->consultation) {
                $consStatus = $invoice->consultation->calculateFinancialStatus();
                $invoice->consultation->payment_status = $consStatus['payment_status'];
                $invoice->consultation->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded and receipt generated successfully.',
                'data' => [
                    'transaction' => $transaction,
                    'invoice' => $invoice->fresh(['items', 'transactions']),
                ],
            ], 201);
        });
    }

    /**
     * 8. GET /api/astrologer/invoices/summary
     * Financial dashboard invoice metrics.
     */
    public function summary(Request $request)
    {
        $admin = $request->user();
        $period = $request->query('period', 'this_month');

        $query = ProfessionalInvoice::where('admin_id', $admin->id);

        $now = now();
        switch ($period) {
            case 'today':
                $query->whereDate('invoice_date', $now->toDateString());
                break;
            case 'this_week':
                $query->whereBetween('invoice_date', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
                break;
            case 'this_month':
                $query->whereBetween('invoice_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
                break;
            case 'custom':
                if ($request->filled('from')) {
                    $query->whereDate('invoice_date', '>=', Carbon::parse($request->query('from'))->toDateString());
                }
                if ($request->filled('to')) {
                    $query->whereDate('invoice_date', '<=', Carbon::parse($request->query('to'))->toDateString());
                }
                break;
            case 'all_time':
            default:
                break;
        }

        $invoices = $query->get();

        $totalInvoiced = 0.0;
        $totalPaid = 0.0;
        $totalOutstanding = 0.0;
        $counts = [
            'total' => $invoices->count(),
            'draft' => 0,
            'issued' => 0,
            'partially_paid' => 0,
            'paid' => 0,
            'overdue' => 0,
            'cancelled' => 0,
        ];

        foreach ($invoices as $inv) {
            if ($inv->status !== 'cancelled') {
                $totalInvoiced += (float) $inv->grand_total;
                $totalPaid += (float) $inv->amount_paid;
                $totalOutstanding += (float) $inv->outstanding_balance;
            }
            if (isset($counts[$inv->status])) {
                $counts[$inv->status]++;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'total_invoiced_amount' => round($totalInvoiced, 2),
                'total_paid_amount' => round($totalPaid, 2),
                'total_outstanding_amount' => round($totalOutstanding, 2),
                'counts' => $counts,
            ],
        ]);
    }

    /**
     * 9. GET /api/astrologer/invoices/jajmans/{id}
     * Retrieve invoice history for a specific Jajman.
     */
    public function jajmanInvoices(Request $request, $id)
    {
        $admin = $request->user();

        $jajman = Jajman::whereHas('professionals', function ($q) use ($admin) {
            $q->where('admin_id', $admin->id);
        })->find($id);

        if (!$jajman) {
            return response()->json([
                'success' => false,
                'message' => 'Jajman not found or unauthorized.',
            ], 404);
        }

        $invoices = ProfessionalInvoice::where('admin_id', $admin->id)
            ->where('jajman_id', $jajman->id)
            ->with(['items'])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        $totalInvoiced = 0.0;
        $totalPaid = 0.0;
        $totalOutstanding = 0.0;

        foreach ($invoices as $inv) {
            if ($inv->status !== 'cancelled') {
                $totalInvoiced += (float) $inv->grand_total;
                $totalPaid += (float) $inv->amount_paid;
                $totalOutstanding += (float) $inv->outstanding_balance;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'jajman' => [
                    'id' => $jajman->id,
                    'name' => $jajman->name,
                    'phone' => $jajman->phone,
                    'city' => $jajman->city,
                ],
                'summary' => [
                    'total_invoiced' => round($totalInvoiced, 2),
                    'total_paid' => round($totalPaid, 2),
                    'total_outstanding' => round($totalOutstanding, 2),
                    'invoices_count' => $invoices->count(),
                ],
                'invoices' => $invoices,
            ],
        ]);
    }

    /**
     * 10. GET /api/astrologer/document-settings
     * Retrieve document and tax settings.
     */
    public function getDocumentSettings(Request $request)
    {
        $admin = $request->user();
        $settings = ProfessionalDocumentSetting::getOrCreateForAdmin($admin);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * 11. POST /api/astrologer/document-settings
     * Update business particulars and tax configuration.
     */
    public function updateDocumentSettings(Request $request)
    {
        $admin = $request->user();
        $settings = ProfessionalDocumentSetting::getOrCreateForAdmin($admin);

        $validated = $request->validate([
            'business_name' => 'nullable|string|max:191',
            'designation' => 'nullable|string|max:191',
            'business_address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:191',
            'website' => 'nullable|string|max:191',
            'logo_url' => 'nullable|string|max:255',
            'gstin' => 'nullable|string|max:20',
            'pan' => 'nullable|string|max:20',
            'tax_enabled' => 'nullable|boolean',
            'tax_name' => 'nullable|string|max:50',
            'default_tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_tax_inclusive' => 'nullable|boolean',
            'place_of_supply' => 'nullable|string|max:100',
            'invoice_prefix' => 'nullable|string|max:20',
            'receipt_prefix' => 'nullable|string|max:20',
            'invoice_notes' => 'nullable|string',
            'invoice_terms' => 'nullable|string',
        ]);

        $settings->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Document and business profile settings updated.',
            'data' => $settings,
        ]);
    }

    /**
     * 12. GET /api/astrologer/receipts
     * List completed payment receipts.
     */
    public function receipts(Request $request)
    {
        $admin = $request->user();

        $query = ProfessionalTransaction::where('admin_id', $admin->id)
            ->where('payment_status', 'completed')
            ->whereNotNull('receipt_number')
            ->with(['jajman:id,name,phone,city', 'invoice:id,invoice_number,grand_total,amount_paid,outstanding_balance']);

        if ($request->filled('jajman_id')) {
            $query->where('jajman_id', $request->query('jajman_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'LIKE', $search)
                    ->orWhere('reference_id', 'LIKE', $search)
                    ->orWhereHas('jajman', function ($jq) use ($search) {
                        $jq->where('name', 'LIKE', $search);
                    });
            });
        }

        $receipts = $query->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $receipts->items(),
            'meta' => [
                'current_page' => $receipts->currentPage(),
                'last_page' => $receipts->lastPage(),
                'total' => $receipts->total(),
            ],
        ]);
    }

    /**
     * 13. GET /api/astrologer/receipts/{id}
     * Single receipt particulars.
     */
    public function receiptShow(Request $request, $id)
    {
        $admin = $request->user();

        $transaction = ProfessionalTransaction::where('admin_id', $admin->id)
            ->with([
                'jajman',
                'invoice',
                'appointment:id,title,start_at',
                'consultation:id,client_name,type',
            ])
            ->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found or unauthorized.',
            ], 404);
        }

        // Ensure receipt number is assigned
        $transaction->ensureReceiptNumber();

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }

    /**
     * 14. GET /api/astrologer/invoices/{id}/pdf
     * Download or stream Invoice PDF.
     */
    public function downloadInvoicePdf(Request $request, $id)
    {
        $admin = $request->user();

        $invoice = ProfessionalInvoice::where('admin_id', $admin->id)
            ->with(['items', 'jajman', 'admin'])
            ->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found or unauthorized.',
            ], 404);
        }

        $pdfBytes = $this->pdfService->generateInvoicePdf($invoice);
        $safeNumber = str_replace('/', '_', $invoice->invoice_number);

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"Invoice_{$safeNumber}.pdf\"",
        ]);
    }

    /**
     * 15. GET /api/astrologer/receipts/{id}/pdf
     * Download or stream Payment Receipt PDF.
     */
    public function downloadReceiptPdf(Request $request, $id)
    {
        $admin = $request->user();

        $transaction = ProfessionalTransaction::where('admin_id', $admin->id)
            ->with(['professional', 'jajman', 'invoice'])
            ->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found or unauthorized.',
            ], 404);
        }

        $transaction->ensureReceiptNumber();
        $pdfBytes = $this->pdfService->generateReceiptPdf($transaction);
        $safeNumber = str_replace('/', '_', $transaction->receipt_number);

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"Receipt_{$safeNumber}.pdf\"",
        ]);
    }
}
