<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_session_id',
        'user_id',
        'astrologer_id',
        'amount',
        'deducted_at',
    ];

    protected $casts = [
        'deducted_at' => 'datetime',
    ];

    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class);
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
