<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Astrologer;
use Illuminate\Support\Facades\Auth;
use App\Models\Review;
use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ChatSession;

class AstrologerController extends Controller
{

  public function index($lang = 'en')
  {
    $validLanguages = ['en', 'hi']; // Add supported languages here
    if (!in_array($lang, $validLanguages)) {
      return response()->json(['error' => 'Invalid language selected'], 400);
    }

    $astrologers  = Astrologer::where([
      ['active', 1],
      ['language', $lang]
    ])->get();

    if ($astrologers->isEmpty()) {
      return response()->json(['message' => 'No Astrologer found'], 404);
    }

    // Append reviews and average rating
    $data = $astrologers->map(function ($astrologer) {
      $reviews = $astrologer->reviews()->with('user')->get();
      $averageRating = round($reviews->avg('rating'), 1);

      return [
        'astrologer' => $astrologer,
        'reviews' => $reviews,
        'average_rating' => $averageRating,
      ];
    });

    return response()->json($data, 200);
  }

  public function updateFcmToken(Request $request)
  {
    $request->validate([
      'fcm_token' => 'required|string',
    ]);

    $astrologer = Auth::user();

    $astrologer->fcm_token = $request->fcm_token;
    $astrologer->save();

    return response()->json(['status' => 'token_updated']);
  }

  public function getBookedPoojas()
  {
    $orders = Order::with(['payment.user', 'payment'])
      ->whereHas('payment', function ($query) {
        $query->where('payment_type', 'Pooja')
          ->where('status', 'CONFIRMED');
      })->get();

    return response()->json(['data' => $orders]);
  }

  public function getDonations()
  {
    $donations = Payment::with(['user'])->where('payment_type', 'Donation')->where('status', 'CONFIRMED')->get();

    return response()->json(['data' => $donations]);
  }

}
