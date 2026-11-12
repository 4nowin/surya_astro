<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JajmanBirthDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'jajman_id',
        'date_of_birth',
        'birth_time',
        'time_unknown',
        'birth_place',
        'latitude',
        'longitude',
        'timezone',
        'is_primary',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'time_unknown' => 'boolean',
        'is_primary' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function jajman()
    {
        return $this->belongsTo(Jajman::class, 'jajman_id');
    }
}
