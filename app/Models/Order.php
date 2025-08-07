<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Pooja;
use App\Models\Payment;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'mobile',
        'email',
        'inquiry_id',
        'payment_id',
        'total_price',
        'status',
        'is_checked_in',
        'promoter_id',
    ];

    public function pooja()
    {
        return $this->belongsTo(Pooja::class, 'inquiry_id', 'id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'id');
    }

    public function user()
    {
        return $this->payment->user ?? null;
    }
}
