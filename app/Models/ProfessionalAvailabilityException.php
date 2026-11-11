<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalAvailabilityException extends Model
{
    use HasFactory;

    protected $table = 'professional_availability_exceptions';

    protected $fillable = [
        'admin_id',
        'date',
        'type',
        'slots',
        'reason',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'slots' => 'array',
    ];

    public function professional()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
