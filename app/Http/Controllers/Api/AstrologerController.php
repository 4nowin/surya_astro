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
  public function index(Request $request, $lang = 'en')
  {
    if ($request->route('lang')) {
      $lang = $request->route('lang');
    }

    $validLanguages = ['en', 'hi']; // Add supported languages here
    if (!in_array($lang, $validLanguages)) {
      return response()->json(['error' => 'Invalid language selected'], 400);
    }

    $query = Astrologer::where([
      ['active', 1],
      ['language', $lang]
    ]);

    // Optional specialisation / expertise filter
    $expertise = $request->query('expertise') ?? $request->query('specialisation');
    if (!empty($expertise)) {
      $expertiseStr = trim($expertise);

      // Supported taxonomy mapping (canonical => aliases)
      $supportedTaxonomy = [
        'kundli' => ['kundli', 'कुंडली', 'horoscope'],
        'muhurat' => ['muhurat', 'मुहूर्त', 'panchang', 'पंचांग'],
        'vastu' => ['vastu', 'vastu shastra', 'वास्तु', 'वास्तु शास्त्र'],
        'vedic astrology' => ['vedic astrology', 'वैदिक ज्योतिष'],
        'numerology' => ['numerology', 'अंक ज्योतिष'],
        'tarot reading' => ['tarot reading', 'टैरो रीडिंग'],
        'palmistry' => ['palmistry', 'हस्तरेखा शास्त्र'],
        'face reading' => ['face reading', 'फेस रीडिंग'],
        'astrology' => ['astrology', 'ज्योतिष'],
        'western astrology' => ['western astrology', 'पश्चिमी ज्योतिष'],
        'kp astrology' => ['kp astrology', 'के.पी. ज्योतिष'],
        'lal kitab' => ['lal kitab', 'लाल किताब'],
        'nadi astrology' => ['nadi astrology', 'नाड़ी ज्योतिष'],
      ];

      $lowerInput = mb_strtolower($expertiseStr, 'UTF-8');

      // Find matching group
      $searchKeywords = [];
      foreach ($supportedTaxonomy as $canonical => $aliases) {
        foreach ($aliases as $alias) {
          if (mb_stripos($alias, $lowerInput) !== false || mb_stripos($lowerInput, $alias) !== false) {
            $searchKeywords = array_merge($searchKeywords, $aliases);
            break;
          }
        }
      }

      if (empty($searchKeywords)) {
        return response()->json(['error' => 'Invalid specialisation filter'], 422);
      }

      $searchKeywords = array_unique($searchKeywords);

      $query->where(function ($q) use ($searchKeywords) {
        foreach ($searchKeywords as $keyword) {
          $q->orWhere('expertise', 'LIKE', '%' . $keyword . '%');
        }
      });
    }

    // Show online astrologers first, then order by price ascending
    $astrologers = $query
      ->orderByRaw("CASE WHEN status = 'online' THEN 1 ELSE 2 END")
      ->orderBy('price', 'asc')
      ->get();

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
