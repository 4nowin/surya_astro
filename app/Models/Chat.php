<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_session_id',
        'user_id',
        'admin_id',
        'astrologer_id',
        'message',
        'sender',
        'client_nonce',
        'billing_type',
        'base_price',
        'platform_markup',
        'customer_price',
        'sent_at',
    ];

    protected $casts = [
        'base_price' => 'float',
        'platform_markup' => 'float',
        'customer_price' => 'float',
        'sent_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function astrologer()
    {
        return $this->belongsTo(Astrologer::class);
    }
}
