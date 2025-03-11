<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Horoscope extends Model
{
    use HasFactory;
    protected $fillable = [
        'image',
        'zodiac_sign',
        'horoscope_type',
        'zodiac',
        'tag',
        'language',
        'context',
        'love',
        'career',
        'money',
        'health',
        'travel',
        'lucky_number',
        'lucky_color',
        'lucky_time',
        'start_date',
        'end_date'
    ];
}
