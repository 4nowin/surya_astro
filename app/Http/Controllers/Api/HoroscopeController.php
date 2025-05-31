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
    $today = Carbon::today();
    $tomorrow = Carbon::tomorrow();
    $startOfWeek = $today->copy()->startOfWeek();
    $startOfMonth = $today->copy()->startOfMonth();
    $startOfYear = $today->copy()->startOfYear();
    $locale = ($lang === 'hi') ? 'hi' : 'en';

    $horoscopes = Horoscope::where('language', $lang)
      ->whereIn('horoscope_type', ['daily', 'weekly', 'monthly', 'yearly'])
      ->where(function ($query) use ($today, $tomorrow, $startOfWeek, $startOfMonth, $startOfYear) {
        $query->where(function ($q) use ($today) {
          $q->where('horoscope_type', 'daily')->where('start_date', $today);
        })->orWhere(function ($q) use ($tomorrow) {
          $q->where('horoscope_type', 'daily')->where('start_date', $tomorrow);
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
      ->groupBy(function ($item) {
        $startDate = Carbon::parse($item->start_date);

        if ($item->horoscope_type == 'daily') {
          if ($startDate->isToday()) {
            return 'today';
          } elseif ($startDate->isTomorrow()) {
            return 'tomorrow';
          }
        }

        return $item->horoscope_type;
      });

    if ($horoscopes->isEmpty()) {
      return response()->json(['message' => 'No data found'], 404);
    }

    return response()->json([
      'date' => $today->locale($locale)->translatedFormat('d F Y'),
      'today' => $horoscopes->get('today', []),
      'tomorrow' => $horoscopes->get('tomorrow', []),
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
