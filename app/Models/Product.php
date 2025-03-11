<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'image',
        'tag',
        'language',
        'excerpt',
        'description',
        'price',
        'original_price',
        'active',
    ];
}
