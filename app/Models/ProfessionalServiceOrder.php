<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfessionalServiceOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'professional_service_orders';

    protected $fillable = [
        'order_number',
        'user_id',
        'admin_id',
        'astrologer_id',
        'service_id',
        'order_type',
        'status',
        'base_price',
        'platform_markup',
        'customer_price',
        'currency',
        'delivery_timeframe_days',
        'delivery_deadline_at',
        'input_payload',
        'deliverable_text',
        'deliverable_attachments',
        'delivered_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'disputed_at',
        'dispute_reason',
        'dispute_status',
        'dispute_resolved_at',
        'dispute_resolution_notes',
        'resolved_by_admin_id',
        'escrow_transaction_id',
        'client_nonce',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'platform_markup' => 'decimal:2',
        'customer_price' => 'decimal:2',
        'delivery_timeframe_days' => 'integer',
        'delivery_deadline_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'disputed_at' => 'datetime',
        'dispute_resolved_at' => 'datetime',
        'input_payload' => 'array',
        'deliverable_attachments' => 'array',
    ];

    /**
     * Consumer who purchased and owns the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Professional (Admin) fulfilling this order.
     */
    public function professional()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * Astrologer profile linked to the admin.
     */
    public function astrologer()
    {
        return $this->belongsTo(Astrologer::class, 'astrologer_id');
    }

    /**
     * Catalogue service item.
     */
    public function service()
    {
        return $this->belongsTo(ProfessionalService::class, 'service_id');
    }

    /**
     * Financial transaction in escrow.
     */
    public function escrowTransaction()
    {
        return $this->belongsTo(ProfessionalTransaction::class, 'escrow_transaction_id');
    }

    /**
     * All professional financial transactions linked to this order.
     */
    public function transactions()
    {
        return $this->hasMany(ProfessionalTransaction::class, 'service_order_id');
    }

    /**
     * Consumer wallet ledger entries linked to this order.
     */
    public function walletTransactions()
    {
        return $this->hasMany(UserWalletTransaction::class, 'service_order_id');
    }

    /**
     * Admin who resolved this order dispute.
     */
    public function resolver()
    {
        return $this->belongsTo(Admin::class, 'resolved_by_admin_id');
    }

    /* ---------------- Scopes ---------------- */

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeDisputed($query)
    {
        return $query->where('status', 'disputed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /* ---------------- Status Helpers ---------------- */

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isDisputed(): bool
    {
        return $this->status === 'disputed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isOverdue(): bool
    {
        return $this->delivery_deadline_at !== null
            && now()->greaterThan($this->delivery_deadline_at)
            && in_array($this->status, ['submitted', 'in_progress'], true);
    }

    public function canBeDelivered(): bool
    {
        return in_array($this->status, ['submitted', 'in_progress'], true);
    }

    public function canBeConfirmedBy(User $user): bool
    {
        return $this->user_id === $user->id && $this->status === 'delivered';
    }

    public function canBeDisputedBy(User $user): bool
    {
        return $this->user_id === $user->id && in_array($this->status, ['submitted', 'in_progress', 'delivered'], true);
    }

    public function canBeCancelledBy(User $user): bool
    {
        return $this->user_id === $user->id && in_array($this->status, ['pending_payment', 'submitted'], true);
    }
}
