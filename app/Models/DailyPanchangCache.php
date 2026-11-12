<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyPanchangCache extends Model
{
    use HasFactory;

    protected $table = 'daily_panchang_cache';

    protected $fillable = [
        'canonical_key',
        'date',
        'latitude',
        'longitude',
        'timezone',
        'elevation',
        'result',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'latitude' => 'float',
        'longitude' => 'float',
        'elevation' => 'float',
        'result' => 'array',
    ];
}
