<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Astrologer;
use App\Models\User;

class Review extends Model
{
    use HasFactory;
    protected $fillable = ['astrologer_id', 'user_id', 'rating', 'comment'];

    public function astrologer()
    {
        return $this->belongsTo(Astrologer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
