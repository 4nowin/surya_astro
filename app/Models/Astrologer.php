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
        'has_active_chat',
    ];

    public function getHasActiveChatAttribute(): bool
    {
        if (array_key_exists('has_active_chat', $this->attributes)) {
            return (bool) $this->attributes['has_active_chat'];
        }

        if (!$this->admin_id) {
            return false;
        }

        if ($this->relationLoaded('activeChatService')) {
            return $this->activeChatService !== null;
        }

        if ($this->relationLoaded('chatService')) {
            return $this->chatService !== null && (bool) $this->chatService->is_active;
        }

        return $this->activeChatService()->exists();
    }

    public function getNameAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->relationLoaded('admin') && $this->admin?->relationLoaded('publicProfile')) {
            return $this->admin?->publicProfile?->display_name ?: $value;
        }

        return $value;
    }

    public function getImageAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->relationLoaded('admin') && $this->admin?->relationLoaded('publicProfile')) {
            return $this->admin?->publicProfile?->profile_photo_url ?: $value;
        }

        return $value;
    }

    public function getExcerptAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->relationLoaded('admin') && $this->admin?->relationLoaded('publicProfile')) {
            return $this->admin?->publicProfile?->headline ?: $value;
        }

        return $value;
    }

    public function getExperienceAttribute($value): ?string
    {
        if (!empty($value)) {
            return (string) $value;
        }

        if ($this->relationLoaded('admin') && $this->admin?->relationLoaded('publicProfile')) {
            $years = $this->admin?->publicProfile?->years_of_experience;
            if ($years !== null) {
                return $years . ($years === 1 ? ' year' : ' years');
            }
        }

        return $value;
    }

    public function getAstrologerLanguageAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->relationLoaded('admin') && $this->admin?->relationLoaded('publicProfile')) {
            $langs = $this->admin?->publicProfile?->languages;
            if (is_array($langs) && !empty($langs)) {
                return implode(', ', $langs);
            }
        }

        return $value;
    }

    public function getExpertiseAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->relationLoaded('admin') && $this->admin?->relationLoaded('publicProfile')) {
            $specs = $this->admin?->publicProfile?->specializations;
            if (is_array($specs) && !empty($specs)) {
                return implode(', ', $specs);
            }
        }

        return $value;
    }

    public function getDescriptionAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->relationLoaded('admin') && $this->admin?->relationLoaded('publicProfile')) {
            return $this->admin?->publicProfile?->about ?: $value;
        }

        return $value;
    }

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
        $img = $this->image;
        if (empty($img) && $this->relationLoaded('admin') && $this->admin?->relationLoaded('publicProfile')) {
            $img = $this->admin?->publicProfile?->profile_photo_url;
        }

        if (empty($img)) {
            return null;
        }

        if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://')) {
            return $img;
        }

        $cleanPath = ltrim(preg_replace('#^/?storage/#', '', $img), '/');
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
                $q->where('offering_type', 'chat')
                  ->orWhere('pricing_model', 'per_message')
                  ->orWhereJsonContains('delivery_methods', 'chat');
            });
    }

    public function activeChatService()
    {
        return $this->hasOne(ProfessionalService::class, 'admin_id', 'admin_id')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('offering_type', 'chat')
                  ->orWhere('pricing_model', 'per_message')
                  ->orWhereJsonContains('delivery_methods', 'chat');
            });
    }

    public function professionalServices()
    {
        return $this->hasMany(ProfessionalService::class, 'admin_id', 'admin_id');
    }
}
