<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAstrologerChatAllowance extends Model
{
    use HasFactory;

    protected $table = 'user_astrologer_chat_allowances';

    protected $fillable = [
        'user_id',
        'astrologer_id',
        'free_messages_used',
    ];

    protected $casts = [
        'free_messages_used' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function astrologer()
    {
        return $this->belongsTo(Astrologer::class);
    }
}
