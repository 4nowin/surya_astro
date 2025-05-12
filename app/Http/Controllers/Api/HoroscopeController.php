<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Horoscope;
use Carbon\Carbon;

class HoroscopeController extends Controller
{

  public function index($lang = 'en')
  {
    $date = Carbon::today();
    $startOfWeek = $date->copy()->startOfWeek();
    $startOfMonth = $date->copy()->startOfMonth();
    $startOfYear = $date->copy()->startOfYear();
    $locale = ($lang === 'hi') ? 'hi' : 'en';

    $horoscopes = Horoscope::where('language', $lang)
      ->whereIn('horoscope_type', ['daily', 'weekly', 'monthly', 'yearly'])
      ->where(function ($query) use ($date, $startOfWeek, $startOfMonth, $startOfYear) {
        $query->where(function ($q) use ($date) {
          $q->where('horoscope_type', 'daily')->where('start_date', $date);
        })->orWhere(function ($q) use ($startOfWeek) {
          $q->where('horoscope_type', 'weekly')->where('start_date', $startOfWeek);
        })->orWhere(function ($q) use ($startOfMonth) {
          $q->where('horoscope_type', 'monthly')->where('start_date', $startOfMonth);
        })->orWhere(function ($q) use ($startOfYear) {
          $q->where('horoscope_type', 'yearly')->where('start_date', $startOfYear);
        });
      })
      ->orderByRaw("FIELD(zodiac_sign, 'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces')")
      ->get()
      ->groupBy('horoscope_type'); // Group by horoscope_type


    // Check if data exists
    if ($horoscopes->isEmpty()) {
      return response()->json(['message' => 'No data found'], 404);
    }

    return response()->json([
      'date' =>  Carbon::parse($date->toDateString())
        ->locale($locale)
        ->translatedFormat('d F Y'),
      'daily' => $horoscopes->get('daily', []),
      'weekly' => $horoscopes->get('weekly', []),
      'monthly' => $horoscopes->get('monthly', []),
      'yearly' => $horoscopes->get('yearly', [])
    ], 200);
  }

}
