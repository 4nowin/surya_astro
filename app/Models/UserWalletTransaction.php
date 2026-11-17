<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWalletTransaction extends Model
{
    use HasFactory;

    protected $table = 'user_wallet_transactions';

    protected $fillable = [
        'user_id',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'chat_id',
        'chat_session_id',
        'appointment_id',
        'service_order_id',
        'service_id',
        'astrologer_id',
        'reference_id',
        'description',
        'status',
    ];

    protected $casts = [
        'amount' => 'float',
        'balance_before' => 'float',
        'balance_after' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }

    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function serviceOrder()
    {
        return $this->belongsTo(ProfessionalServiceOrder::class, 'service_order_id');
    }

    public function service()
    {
        return $this->belongsTo(ProfessionalService::class, 'service_id');
    }

    public function astrologer()
    {
        return $this->belongsTo(Astrologer::class, 'astrologer_id');
    }
}
