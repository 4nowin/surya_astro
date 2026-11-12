<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfessionalTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'professional_transactions';

    protected $fillable = [
        'admin_id',
        'jajman_id',
        'appointment_id',
        'consultation_id',
        'parent_transaction_id',
        'transaction_type',
        'payment_method',
        'payment_status',
        'amount',
        'currency',
        'transaction_at',
        'reference_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'float',
        'transaction_at' => 'datetime',
    ];

    /**
     * Practitioner who owns this transaction.
     */
    public function professional()
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
     * Linked appointment.
     */
    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /**
     * Linked consultation.
     */
    public function consultation()
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }

    /**
     * Parent transaction (for refunds).
     */
    public function parentTransaction()
    {
        return $this->belongsTo(ProfessionalTransaction::class, 'parent_transaction_id');
    }

    /**
     * Refunds issued against this payment.
     */
    public function refunds()
    {
        return $this->hasMany(ProfessionalTransaction::class, 'parent_transaction_id');
    }

    /**
     * Calculate total completed refunds against this transaction.
     */
    public function totalRefunded(): float
    {
        return (float) $this->refunds()
            ->where('payment_status', 'completed')
            ->sum('amount');
    }

    /**
     * Calculate maximum amount that can be refunded.
     */
    public function refundableBalance(): float
    {
        if ($this->transaction_type === 'refund') {
            return 0.0;
        }

        if (!in_array($this->payment_status, ['completed', 'partially_refunded'])) {
            return 0.0;
        }

        $refunded = $this->totalRefunded();
        return max(0.0, round($this->amount - $refunded, 2));
    }
}
