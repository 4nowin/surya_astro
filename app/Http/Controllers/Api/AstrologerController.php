<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Astrologer;
use App\Models\Review;

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
}
