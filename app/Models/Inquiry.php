<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    use HasFactory;
    protected $table = 'inquiries';

    protected $fillable = [
        "name",
        "email",
        "phone",
        "gender",
        "country",
        "date_of_birth",
        "place_of_birth",
        "for",
        "type",
        "message",
    ];
}
