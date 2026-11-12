<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatBroadcastOutbox extends Model
{
    use HasFactory;

    protected $table = 'chat_broadcast_outbox';

    protected $fillable = [
        'chat_id',
        'chat_session_id',
        'payload',
        'attempts',
        'status',
        'last_attempt_at',
        'delivered_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'last_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
