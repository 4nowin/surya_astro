<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Review;
use App\Models\Admin;
use App\Models\ChatSession;
use App\Models\Chat;

class Astrologer extends Model
{
    use HasFactory;
    protected $fillable = [
        'admin_id',
        'image',
        'name',
        'language',
        'astrologer_language',
        'expertise',
        'experience',
        'excerpt',
        'description',
        'chat_minutes',
        'call_minutes',
        'price',
        'call_price',
        'original_price',
        'is_online',
        'is_typing',
        'active',
        'fcm_token',
    ];

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function averageRating()
    {
        return $this->reviews()->avg('rating');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class);
    }

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }

    public function chatPayments()
    {
        return $this->hasMany(ChatPayment::class);
    }
}
