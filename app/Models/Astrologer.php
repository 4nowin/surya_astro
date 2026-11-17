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
        'customer_price',
        'is_free_chat',
    ];

    public function getCustomerPriceAttribute(): float
    {
        if (array_key_exists('customer_price', $this->attributes)) {
            return (float) $this->attributes['customer_price'];
        }

        if ($this->relationLoaded('chatService') && $this->chatService) {
            $service = $this->chatService;
            $breakdown = app(\App\Services\Pricing\PricingService::class)->calculateServicePrice($service->price, (bool) $service->is_free_chat);
            return $breakdown['customer_price'];
        }

        if (isset($this->attributes['price'])) {
            $breakdown = app(\App\Services\Pricing\PricingService::class)->calculateServicePrice((float) $this->attributes['price'], false);
            return $breakdown['customer_price'];
        }

        return 0.12;
    }

    public function getIsFreeChatAttribute(): bool
    {
        if (array_key_exists('is_free_chat', $this->attributes)) {
            return (bool) $this->attributes['is_free_chat'];
        }
        if ($this->relationLoaded('chatService') && $this->chatService) {
            return (bool) $this->chatService->is_free_chat;
        }
        return false;
    }

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

    public function chatService()
    {
        return $this->hasOne(ProfessionalService::class, 'admin_id', 'admin_id')
            ->where(function ($q) {
                $q->where('pricing_model', 'per_message')
                  ->orWhereJsonContains('delivery_methods', 'chat');
            });
    }

    public function professionalServices()
    {
        return $this->hasMany(ProfessionalService::class, 'admin_id', 'admin_id');
    }
}
