<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Horoscope;
use App\Models\User;
use App\Services\FcmService;
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

  public function homeHoroscope($lang = 'en')
  {
    $date = Carbon::today();
    $locale = ($lang === 'hi') ? 'hi' : 'en';

    // Try to get a horoscope for today
    $todayHoroscope = Horoscope::where('language', $lang)
      ->where('horoscope_type', 'daily')
      ->where('start_date', $date)
      ->orderByRaw("FIELD(zodiac_sign, 'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces')")
      ->first();

    // If no horoscope for today, use a fallback (e.g. latest available)
    if (!$todayHoroscope) {
      $todayHoroscope = Horoscope::where('language', $lang)
        ->where('horoscope_type', 'daily')
        ->orderBy('start_date', 'desc')
        ->first();

      if (!$todayHoroscope) {
        return response()->json(['message' => 'No horoscope available'], 404);
      }
    }

    return response()->json([
      'date' => Carbon::parse($todayHoroscope->start_date)
        ->locale($locale)
        ->translatedFormat('d F Y'),
      'data' => $todayHoroscope
    ], 200);
  }

  public function sendLocalizedHoroscopeNotifications()
  {
    $date = Carbon::today();
    $languages = ['en', 'hi']; // Add all supported language codes here

    foreach ($languages as $lang) {
      $horoscope = Horoscope::where('language', $lang)
        ->where('horoscope_type', 'daily')
        ->where('start_date', $date)
        ->first();

      if ($horoscope) {
        $topic = "horoscope-$lang";

        FcmService::sendToTopic(
          $topic,
          $horoscope->title,
          $horoscope->excerpt,
          [
            'type' => 'update',
            'lang' => $lang,
            'date' => $date->toDateString()
          ]
        );
      }
    }

    return response()->json(['message' => 'Bulk notification sent'], 200);
  }
}
