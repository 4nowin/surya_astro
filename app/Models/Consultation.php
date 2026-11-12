<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    use HasFactory;

    protected $table = 'consultations';

    protected $fillable = [
        'admin_id',
        'jajman_id',
        'service_id',
        'service_name_snapshot',
        'chat_session_id',
        'client_name',
        'type',
        'status',
        'consultation_date',
        'duration_minutes',
        'fee_charged',
        'payment_status',
        'summary',
    ];

    protected $casts = [
        'consultation_date' => 'datetime',
        'fee_charged' => 'float',
        'duration_minutes' => 'integer',
    ];

    public function professional()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function jajman()
    {
        return $this->belongsTo(Jajman::class, 'jajman_id');
    }

    public function service()
    {
        return $this->belongsTo(ProfessionalService::class, 'service_id');
    }

    public function transactions()
    {
        return $this->hasMany(ProfessionalTransaction::class, 'consultation_id');
    }

    public function calculateFinancialStatus(): array
    {
        $obligation = (float) ($this->fee_charged ?? 0.0);

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
            'fee_charged' => $obligation,
            'total_received' => $completedPayments,
            'total_refunded' => $completedRefunds,
            'net_received' => $netReceived,
            'outstanding_balance' => $outstanding,
            'payment_status' => $status,
        ];
    }

    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function appointment()
    {
        return $this->hasOne(Appointment::class, 'consultation_id');
    }
}
