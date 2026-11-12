<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    use HasFactory;

    protected $table = 'consultations';

    protected $fillable = [
        'admin_id',
        'jajman_id',
        'service_id',
        'service_name_snapshot',
        'chat_session_id',
        'client_name',
        'type',
        'status',
        'consultation_date',
        'duration_minutes',
        'fee_charged',
        'summary',
    ];

    protected $casts = [
        'consultation_date' => 'datetime',
        'fee_charged' => 'float',
        'duration_minutes' => 'integer',
    ];

    public function professional()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function jajman()
    {
        return $this->belongsTo(Jajman::class, 'jajman_id');
    }

    public function service()
    {
        return $this->belongsTo(ProfessionalService::class, 'service_id');
    }

    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function appointment()
    {
        return $this->hasOne(Appointment::class, 'consultation_id');
    }
}
