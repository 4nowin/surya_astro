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
use Carbon\Carbon;

class ApiController extends Controller
{

  public function inquiry()
  {
    $inquiries = Inquiry::orderBy('id', 'DESC')->paginate(10);
    return Inquiry::all();
  }

  public function pooja($id = 'en')
  {
    // Validate the language parameter (optional but recommended)
    $validLanguages = ['en', 'hi']; // Add supported languages here
    if (!in_array($id, $validLanguages)) {
      return response()->json(['error' => 'Invalid language selected'], 400);
    }

    // Fetch active pooja records for the selected language
    $data = Pooja::where([
      ['active', 1],
      ['language', $id]
    ])->get()->map(function ($item) use ($id) {
      $locale = ($id === 'hi') ? 'hi' : 'en'; // Set locale dynamically
  
      $item->start_date = Carbon::parse($item->start_date)
          ->locale($locale)
          ->translatedFormat('d F Y'); // Example: "26 फरवरी 2025" for Hindi
  
      $item->end_date = Carbon::parse($item->end_date)
          ->locale($locale)
          ->translatedFormat('d F Y'); 
  
      return $item;
  });

    // Check if data exists
    if ($data->isEmpty()) {
      return response()->json(['message' => 'No data found'], 404);
    }

    return response()->json($data, 200);
  }
}
