<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'appointments';

    protected $fillable = [
        'admin_id',
        'user_id',
        'jajman_id',
        'title',
        'description',
        'appointment_type',
        'booking_source',
        'status',
        'start_at',
        'end_at',
        'acceptance_deadline_at',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'no_show_reported_at',
        'no_show_reported_by',
        'no_show_notes',
        'no_show_review_status',
        'timezone',
        'location',
        'meeting_link',
        'service_id',
        'service_name_snapshot',
        'agreed_price_snapshot',
        'base_price',
        'platform_markup',
        'customer_price',
        'currency_snapshot',
        'duration_minutes_snapshot',
        'delivery_method_snapshot',
        'payment_status',
        'escrow_transaction_id',
        'client_nonce',
        'consultation_id',
        'cancellation_reason',
        'cancellation_review_status',
        'resolved_by_admin_id',
        'resolution_notes',
        'resolved_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'acceptance_deadline_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'no_show_reported_at' => 'datetime',
        'agreed_price_snapshot' => 'float',
        'base_price' => 'decimal:2',
        'platform_markup' => 'decimal:2',
        'customer_price' => 'decimal:2',
        'duration_minutes_snapshot' => 'integer',
    ];

    /**
     * Practitioner who owns this appointment.
     */
    public function professional()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * Consumer user who booked this appointment.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Linked Jajman CRM record.
     */
    public function jajman()
    {
        return $this->belongsTo(Jajman::class, 'jajman_id');
    }

    /**
     * Linked professional service catalogue item.
     */
    public function service()
    {
        return $this->belongsTo(ProfessionalService::class, 'service_id');
    }

    /**
     * Financial transactions linked to this appointment.
     */
    public function transactions()
    {
        return $this->hasMany(ProfessionalTransaction::class, 'appointment_id');
    }

    /**
     * Escrow transaction linked to this appointment.
     */
    public function escrowTransaction()
    {
        return $this->belongsTo(ProfessionalTransaction::class, 'escrow_transaction_id');
    }

    /**
     * User wallet transactions linked to this appointment.
     */
    public function walletTransactions()
    {
        return $this->hasMany(UserWalletTransaction::class, 'appointment_id');
    }

    /**
     * Admin who resolved this cancellation or no-show.
     */
    public function resolver()
    {
        return $this->belongsTo(Admin::class, 'resolved_by_admin_id');
    }

    /**
     * Check if appointment is pending practitioner acceptance.
     */
    public function isPendingAcceptance(): bool
    {
        return in_array($this->status, ['pending', 'pending_confirmation'], true);
    }

    /**
     * Check if 24-hour practitioner acceptance deadline has expired.
     */
    public function isAcceptanceExpired(): bool
    {
        return $this->isPendingAcceptance()
            && $this->acceptance_deadline_at !== null
            && now()->greaterThan($this->acceptance_deadline_at);
    }

    /**
     * Check if cancellation is outside the 12-hour cutoff (free / allowed cancel).
     */
    public function isCancellationAllowed(int $cutoffHours = 12): bool
    {
        if (!$this->start_at) {
            return true;
        }

        return now()->diffInHours($this->start_at, false) >= $cutoffHours;
    }

    /**
     * Check if cancellation is inside the 12-hour cutoff (requires admin review / penalty policy).
     */
    public function isInsideCancellationCutoff(int $cutoffHours = 12): bool
    {
        return !$this->isCancellationAllowed($cutoffHours);
    }

    /**
     * Check if appointment has an unresolved no-show reported.
     */
    public function isNoShowPendingReview(): bool
    {
        return $this->no_show_review_status === 'pending_review';
    }

    /**
     * Authoritative financial calculation for this appointment.
     */
    public function calculateFinancialStatus(): array
    {
        $obligation = (float) ($this->agreed_price_snapshot ?? ($this->service?->price ?? 0.0));

        $completedPayments = (float) $this->transactions()
            ->where('transaction_type', '!=', 'refund')
            ->where('payment_status', 'completed')
            ->sum('amount');

        $completedRefunds = (float) $this->transactions()
            ->where('transaction_type', 'refund')
            ->where('payment_status', 'completed')
            ->sum('amount');

        $netReceived = round($completedPayments - $completedRefunds, 2);
        $outstanding = max(0.0, round($obligation - $netReceived, 2));

        $status = 'unpaid';
        if ($obligation <= 0) {
            $status = $netReceived > 0 ? 'paid' : 'unpaid';
        } elseif ($netReceived >= $obligation) {
            $status = 'paid';
        } elseif ($netReceived > 0) {
            $status = 'partially_paid';
        } elseif ($completedRefunds > 0) {
            $status = 'refunded';
        }

        return [
            'total_agreed' => $obligation,
            'total_received' => $completedPayments,
            'total_refunded' => $completedRefunds,
            'net_received' => $netReceived,
            'outstanding_balance' => $outstanding,
            'payment_status' => $status,
        ];
    }

    /**
     * Linked completed consultation record if generated.
     */
    public function consultation()
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }
}
