<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Inquiry;

class Order extends Model
{
    use HasFactory;

    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class, 'inquiry_id', 'id');
    }
}
