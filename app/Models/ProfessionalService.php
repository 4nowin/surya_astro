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
        'pricing_model',
        'price',
        'currency',
        'duration_minutes',
        'delivery_methods',
        'is_active',
        'preparation_instructions',
        'additional_info',
    ];

    protected $casts = [
        'price' => 'float',
        'duration_minutes' => 'integer',
        'delivery_methods' => 'array',
        'is_active' => 'boolean',
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
     * Check if service has been used in any appointment or consultation.
     */
    public function hasHistoricalUsage(): bool
    {
        return $this->appointments()->exists() || $this->consultations()->exists();
    }
}
