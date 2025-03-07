<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inquiry;
use App\Models\Pooja;
use App\Models\Promoter;
use App\Models\Coupon;
use App\Models\OrderDetail;
use App\Models\Order;
use Razorpay\Api\Api;

class ApiController extends Controller
{
  
  public function inquiry()
  {
    $inquiries = Inquiry::orderBy('id', 'DESC')->paginate(10);
    return Inquiry::all();
  }

  public function pooja()
  {
    $inquiries = Pooja::orderBy('id', 'DESC')->paginate(10);
    return Pooja::all();
  }

}
