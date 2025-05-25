<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'astrologer_id',
        'started_at',
        'ended_at',
        'total_minutes',
        'total_fee'
    ];

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function astrologer()
    {
        return $this->belongsTo(Astrologer::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(Chat::class)->latest();
    }
}
