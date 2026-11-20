<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfessionalService extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'professional_services';

    protected $fillable = [
        'admin_id',
        'name',
        'description',
        'category',
        'specialization',
        'offering_type',
        'pricing_model',
        'price',
        'currency',
        'duration_minutes',
        'duration_value',
        'duration_unit',
        'delivery_methods',
        'has_video_proof',
        'has_prasad_delivery',
        'attendance_mode',
        'is_active',
        'is_public',
        'public_description',
        'preparation_instructions',
        'additional_info',
        'is_free_chat',
        'max_message_characters',
        'delivery_timeframe_days',
    ];

    protected $casts = [
        'price' => 'float',
        'duration_minutes' => 'integer',
        'duration_value' => 'integer',
        'has_video_proof' => 'boolean',
        'has_prasad_delivery' => 'boolean',
        'delivery_methods' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_free_chat' => 'boolean',
        'max_message_characters' => 'integer',
        'delivery_timeframe_days' => 'integer',
    ];

    /**
     * Professional who owns this service.
     */
    public function professional()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * Appointments scheduled using this service.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }

    /**
     * Orders fulfilled using this deliverable service.
     */
    public function orders()
    {
        return $this->hasMany(ProfessionalServiceOrder::class, 'service_id');
    }

    /**
     * Consultations recorded using this service.
     */
    public function consultations()
    {
        return $this->hasMany(Consultation::class, 'service_id');
    }

    /**
     * Scope query to only active services.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to a specific category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope query to a specific offering type (chat, product, puja).
     */
    public function scopeByOfferingType($query, string $type)
    {
        return $query->where('offering_type', $type);
    }

    /**
     * Scope query to only active and publicly visible services.
     */
    public function scopePubliclyVisible($query)
    {
        return $query->where('is_active', true)->where('is_public', true);
    }

    public function getVideoProofAvailableAttribute(): bool
    {
        return (bool) $this->has_video_proof;
    }

    public function setVideoProofAvailableAttribute($value): void
    {
        $this->attributes['has_video_proof'] = (bool) $value;
    }

    public function getPrasadCourierAvailableAttribute(): bool
    {
        return (bool) $this->has_prasad_delivery;
    }

    public function setPrasadCourierAvailableAttribute($value): void
    {
        $this->attributes['has_prasad_delivery'] = (bool) $value;
    }

    /**
     * Public-safe service representation without internal preparation instructions.
     */
    public function toPublicArray(): array
    {
        $basePrice = (float) $this->price;
        $pricing = app(\App\Services\Pricing\PricingService::class)->calculateCommercialPricing($basePrice);
        $astro = $this->relationLoaded('professional') ? $this->professional?->astrologer : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'public_description' => $this->public_description ?: $this->description,
            'category' => $this->category,
            'specialization' => $this->specialization,
            'offering_type' => $this->offering_type ?? 'product',
            'pricing_model' => $this->pricing_model,
            'price' => $basePrice,
            'base_price' => $pricing['astrologer_base_price'],
            'platform_markup' => $pricing['platform_markup'],
            'customer_price' => $pricing['customer_price'],
            'pricing' => $pricing,
            'currency' => $this->currency,
            'duration_minutes' => $this->duration_minutes,
            'duration_value' => $this->duration_value,
            'duration_unit' => $this->duration_unit ?? 'minutes',
            'has_video_proof' => (bool) $this->has_video_proof,
            'video_proof_available' => (bool) $this->has_video_proof,
            'has_prasad_delivery' => (bool) $this->has_prasad_delivery,
            'prasad_courier_available' => (bool) $this->has_prasad_delivery,
            'attendance_mode' => $this->attendance_mode ?? 'both',
            'delivery_timeframe_days' => (int) ($this->delivery_timeframe_days ?? 3),
            'delivery_methods' => $this->delivery_methods ?? ['online'],
            'is_active' => (bool) $this->is_active,
            'is_public' => (bool) $this->is_public,
            'is_free_chat' => (bool) $this->is_free_chat,
            'max_message_characters' => (int) ($this->max_message_characters ?? 1000),
            'astrologer' => $astro ? [
                'id' => $astro->id,
                'name' => $astro->name,
                'image' => $astro->image_url,
                'expertise' => $astro->expertise,
                'experience' => $astro->experience,
                'rating' => (float) ($astro->rating ?? 5.0),
            ] : null,
        ];
    }

    /**
     * Check if service has been used in any appointment or consultation.
     */
    public function hasHistoricalUsage(): bool
    {
        return $this->appointments()->exists() || $this->consultations()->exists();
    }
}
