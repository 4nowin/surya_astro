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
        'jajman_id',
        'title',
        'description',
        'appointment_type',
        'booking_source',
        'status',
        'start_at',
        'end_at',
        'timezone',
        'location',
        'meeting_link',
        'service_id',
        'service_name_snapshot',
        'agreed_price_snapshot',
        'currency_snapshot',
        'duration_minutes_snapshot',
        'delivery_method_snapshot',
        'payment_status',
        'consultation_id',
        'cancellation_reason',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'agreed_price_snapshot' => 'float',
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
