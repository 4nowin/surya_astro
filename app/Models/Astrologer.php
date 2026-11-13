<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
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
        'status',
        'active',
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        $cleanPath = ltrim(preg_replace('#^/?storage/#', '', $this->image), '/');
        return Storage::disk('public')->url($cleanPath);
    }

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
