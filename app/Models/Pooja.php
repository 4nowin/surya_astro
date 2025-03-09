<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pooja extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'image',
        'tag',
        'language',
        'excerpt',
        'description',
        'start_date',
        'end_date',
        'price',
        'original_price',
        'active',
    ];
}
