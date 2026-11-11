<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalAvailability extends Model
{
    use HasFactory;

    protected $table = 'professional_availabilities';

    protected $fillable = [
        'admin_id',
        'day_of_week',
        'is_enabled',
        'slots',
        'timezone',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_enabled' => 'boolean',
        'slots' => 'array',
    ];

    public function professional()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
