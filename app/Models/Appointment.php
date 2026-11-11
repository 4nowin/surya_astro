<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'appointments';

    protected $fillable = [
        'admin_id',
        'jajman_id',
        'title',
        'description',
        'appointment_type',
        'booking_source',
        'status',
        'start_at',
        'end_at',
        'timezone',
        'location',
        'meeting_link',
        'consultation_id',
        'cancellation_reason',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    /**
     * Practitioner who owns this appointment.
     */
    public function professional()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * Linked Jajman CRM record.
     */
    public function jajman()
    {
        return $this->belongsTo(Jajman::class, 'jajman_id');
    }

    /**
     * Linked completed consultation record if generated.
     */
    public function consultation()
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }
}
