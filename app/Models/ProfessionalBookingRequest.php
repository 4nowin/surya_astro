<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProfessionalBookingRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'professional_booking_requests';

    protected $fillable = [
        'admin_id',
        'booking_reference',
        'access_token',
        'service_id',
        'service_name_snapshot',
        'agreed_price_snapshot',
        'currency_snapshot',
        'duration_minutes_snapshot',
        'delivery_method',
        'requested_start_at',
        'requested_end_at',
        'timezone',
        'client_name',
        'client_phone',
        'client_email',
        'location',
        'client_message',
        'status',
        'rejection_reason',
        'alternative_proposed_start_at',
        'alternative_proposed_end_at',
        'alternative_notes',
        'jajman_id',
        'appointment_id',
        'confirmed_at',
        'rejected_at',
    ];

    protected $casts = [
        'agreed_price_snapshot' => 'float',
        'duration_minutes_snapshot' => 'integer',
        'requested_start_at' => 'datetime',
        'requested_end_at' => 'datetime',
        'alternative_proposed_start_at' => 'datetime',
        'alternative_proposed_end_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function service()
    {
        return $this->belongsTo(ProfessionalService::class, 'service_id');
    }

    public function jajman()
    {
        return $this->belongsTo(Jajman::class, 'jajman_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /**
     * Generate an alphanumeric booking reference: BK-2026-XXXXXX
     */
    public static function generateBookingReference(): string
    {
        $year = date('Y');
        do {
            $random = strtoupper(Str::random(6));
            $ref = "BK-{$year}-{$random}";
        } while (static::where('booking_reference', $ref)->exists());

        return $ref;
    }

    /**
     * Generate secure client access token for read/cancel authorization.
     */
    public static function generateAccessToken(): string
    {
        return Str::random(32);
    }

    /**
     * Sanitize request representation for public client status tracking.
     */
    public function toPublicArray(): array
    {
        return [
            'booking_reference' => $this->booking_reference,
            'status' => $this->status,
            'client_name' => $this->client_name,
            'service_name' => $this->service_name_snapshot,
            'agreed_price' => $this->agreed_price_snapshot,
            'currency' => $this->currency_snapshot,
            'duration_minutes' => $this->duration_minutes_snapshot,
            'delivery_method' => $this->delivery_method,
            'requested_start_at' => $this->requested_start_at?->toIso8601String(),
            'requested_end_at' => $this->requested_end_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'location' => $this->location,
            'alternative_proposed_start_at' => $this->alternative_proposed_start_at?->toIso8601String(),
            'alternative_proposed_end_at' => $this->alternative_proposed_end_at?->toIso8601String(),
            'alternative_notes' => $this->alternative_notes,
            'rejection_reason' => $this->status === 'rejected' ? $this->rejection_reason : null,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'practitioner' => [
                'display_name' => $this->admin?->publicProfile?->display_name ?? $this->admin?->name ?? 'Practitioner',
                'headline' => $this->admin?->publicProfile?->headline,
                'city' => $this->admin?->publicProfile?->city,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
