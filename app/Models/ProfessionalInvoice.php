<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfessionalInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'professional_invoices';

    protected $fillable = [
        'admin_id',
        'jajman_id',
        'appointment_id',
        'consultation_id',
        'service_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'currency',
        'subtotal',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'tax_enabled',
        'tax_name',
        'tax_rate',
        'tax_amount',
        'is_tax_inclusive',
        'grand_total',
        'amount_paid',
        'outstanding_balance',
        'status',
        'cancelled_at',
        'cancellation_reason',
        'notes',
        'payment_terms',
        'professional_details_snapshot',
        'jajman_details_snapshot',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'cancelled_at' => 'datetime',
        'tax_enabled' => 'boolean',
        'is_tax_inclusive' => 'boolean',
        'subtotal' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'professional_details_snapshot' => 'array',
        'jajman_details_snapshot' => 'array',
    ];

    /**
     * Professional owner.
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * Linked Jajman client.
     */
    public function jajman()
    {
        return $this->belongsTo(Jajman::class, 'jajman_id');
    }

    /**
     * Linked Appointment.
     */
    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /**
     * Linked Consultation.
     */
    public function consultation()
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }

    /**
     * Linked Service Catalogue item.
     */
    public function service()
    {
        return $this->belongsTo(ProfessionalService::class, 'service_id');
    }

    /**
     * Invoice line items.
     */
    public function items()
    {
        return $this->hasMany(ProfessionalInvoiceItem::class, 'invoice_id');
    }

    /**
     * Linked payment transactions.
     */
    public function transactions()
    {
        return $this->hasMany(ProfessionalTransaction::class, 'invoice_id');
    }

    /**
     * Check if invoice is in editable draft state.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if invoice is issued (details become immutable).
     */
    public function isIssued(): bool
    {
        return in_array($this->status, ['issued', 'partially_paid', 'paid', 'overdue']);
    }

    /**
     * Recalculate subtotal, discounts, tax and grand total with decimal precision.
     */
    public function recalculateTotals(): self
    {
        $subtotal = 0.0;
        foreach ($this->items as $item) {
            $subtotal += round((float) $item->quantity * (float) $item->unit_price, 2);
        }
        $this->subtotal = round($subtotal, 2);

        // Discount calculation
        $discountAmount = 0.0;
        if ($this->discount_type === 'percentage') {
            $rate = (float) ($this->discount_rate ?? 0);
            $discountAmount = round($this->subtotal * ($rate / 100), 2);
        } elseif ($this->discount_type === 'fixed') {
            $discountAmount = min($this->subtotal, (float) ($this->discount_amount ?? 0));
        }
        $this->discount_amount = round($discountAmount, 2);

        $taxableBase = max(0.0, round($this->subtotal - $this->discount_amount, 2));

        // Tax calculation
        $taxAmount = 0.0;
        $grandTotal = $taxableBase;

        if ($this->tax_enabled && (float) ($this->tax_rate ?? 0) > 0) {
            $taxRate = (float) $this->tax_rate;
            if ($this->is_tax_inclusive) {
                // Price already includes tax; derive the tax portion
                $base = round($taxableBase / (1 + ($taxRate / 100)), 2);
                $taxAmount = round($taxableBase - $base, 2);
                $grandTotal = $taxableBase;
            } else {
                // Tax exclusive: add tax on top of taxable base
                $taxAmount = round($taxableBase * ($taxRate / 100), 2);
                $grandTotal = round($taxableBase + $taxAmount, 2);
            }
        }

        $this->tax_amount = round($taxAmount, 2);
        $this->grand_total = round($grandTotal, 2);
        $this->outstanding_balance = max(0.0, round($this->grand_total - (float) $this->amount_paid, 2));

        return $this;
    }

    /**
     * Authoritatively synchronize paid amount, outstanding balance, and status
     * based on completed transactions and refunds allocated to this invoice.
     */
    public function syncPaymentStatus(): self
    {
        $completedTransactions = $this->transactions()
            ->where('payment_status', 'completed')
            ->get();

        $totalPaid = 0.0;
        $totalRefunded = 0.0;

        foreach ($completedTransactions as $tx) {
            if ($tx->transaction_type === 'refund') {
                $totalRefunded += (float) $tx->amount;
            } else {
                $totalPaid += (float) $tx->amount;
                // Add any child refunds recorded against this payment
                $totalRefunded += (float) $tx->totalRefunded();
            }
        }

        $netPaid = max(0.0, round($totalPaid - $totalRefunded, 2));
        $this->amount_paid = $netPaid;
        $this->outstanding_balance = max(0.0, round((float) $this->grand_total - $netPaid, 2));

        // Update status only if not draft or cancelled
        if ($this->status !== 'draft' && $this->status !== 'cancelled') {
            if ($this->amount_paid >= (float) $this->grand_total && (float) $this->grand_total > 0) {
                $this->status = 'paid';
            } elseif ($this->amount_paid > 0) {
                $this->status = 'partially_paid';
            } else {
                if ($this->due_date && Carbon::parse($this->due_date)->endOfDay()->isPast()) {
                    $this->status = 'overdue';
                } else {
                    $this->status = 'issued';
                }
            }
        }

        $this->save();
        return $this;
    }

    /**
     * Snapshot practitioner's business details for document immutability.
     */
    public function snapshotProfessionalDetails(Admin $admin, ?ProfessionalDocumentSetting $settings = null): self
    {
        $s = $settings ?? ProfessionalDocumentSetting::getOrCreateForAdmin($admin);
        $this->professional_details_snapshot = [
            'business_name' => $s->business_name ?: $admin->name,
            'designation' => $s->designation ?: ($admin->role ?? 'Spiritual Consultant'),
            'address' => $s->business_address,
            'phone' => $s->phone ?: $admin->phone,
            'email' => $s->email ?: $admin->email,
            'website' => $s->website,
            'logo_url' => $s->logo_url,
            'gstin' => $s->gstin,
            'pan' => $s->pan,
            'place_of_supply' => $s->place_of_supply,
        ];
        return $this;
    }

    /**
     * Snapshot client details for document immutability.
     */
    public function snapshotJajmanDetails(?Jajman $jajman, array $fallback = []): self
    {
        if ($jajman) {
            $this->jajman_details_snapshot = [
                'name' => $jajman->name,
                'phone' => $jajman->phone,
                'email' => $jajman->email,
                'city' => $jajman->city,
                'address' => $jajman->address,
            ];
        } else {
            $this->jajman_details_snapshot = [
                'name' => $fallback['name'] ?? 'Direct Client',
                'phone' => $fallback['phone'] ?? null,
                'email' => $fallback['email'] ?? null,
                'city' => $fallback['city'] ?? null,
                'address' => $fallback['address'] ?? null,
            ];
        }
        return $this;
    }
}
