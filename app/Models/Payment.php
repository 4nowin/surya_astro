<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'rzp_payment_id',
    'rzp_order_id',
    'payment_method',
    'rzp_signature',
    'order_id',
    'phone',
    'email',
    'wallet',
    'cancel_reason',
    'amount',
    'status',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
