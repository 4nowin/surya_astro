<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pooja;
use Carbon\Carbon;

class PoojaController extends Controller
{

  public function index($lang = 'en')
  {
    $validLanguages = ['en', 'hi'];
    if (!in_array($lang, $validLanguages)) {
      return response()->json(['error' => 'Invalid language selected'], 400);
    }

    $locale = $lang === 'hi' ? 'hi' : 'en';
    $fallbackText = $locale === 'hi' ? 'अनुरोध पर' : 'On Demand';
    $today = Carbon::today();

    $data = Pooja::with(['admin.astrologers', 'admin.publicProfile'])
      ->where('active', 1)
      ->where('language', $lang)
      ->whereNull('home_priority')
      ->latest()
      ->get()
      ->filter(function ($item) use ($today) {
        return empty($item->end_date) || Carbon::parse($item->end_date)->gte($today);
      })
      ->map(function ($item) use ($locale, $fallbackText, $lang) {
        $item->pooja_start_date = !empty($item->start_date)
          ? Carbon::parse($item->start_date)->locale($locale)->translatedFormat('d F Y')
          : $fallbackText;

        $item->pooja_end_date = !empty($item->end_date)
          ? Carbon::parse($item->end_date)->locale($locale)->translatedFormat('d F Y')
          : $fallbackText;

        $item->provider_name = $item->resolveProviderName($lang);

        return $item;
      });

    if ($data->isEmpty()) {
      return response()->json(['message' => 'No data found'], 404);
    }

    return response()->json($data->values(), 200); // ensure proper re-indexing
  }

  public function homePoojas($lang = 'en')
  {
    $validLanguages = ['en', 'hi'];
    if (!in_array($lang, $validLanguages)) {
      return response()->json(['error' => 'Invalid language selected'], 400);
    }

    $locale = $lang === 'hi' ? 'hi' : 'en';
    $fallbackText = $locale === 'hi' ? 'अनुरोध पर' : 'On Demand';
    $today = Carbon::today();

    $poojas = Pooja::with(['admin.astrologers', 'admin.publicProfile'])
      ->whereNotNull('home_priority')
      ->where('active', true)
      ->where('language', $lang)
      ->latest()
      ->get()
      ->filter(function ($item) use ($today) {
        return empty($item->end_date) || Carbon::parse($item->end_date)->gte($today);
      })
      ->sortBy('home_priority')
      ->take(3);

    // Fallback: latest 3 if nothing from home_priority is valid
    if ($poojas->isEmpty()) {
      $poojas = Pooja::with(['admin.astrologers', 'admin.publicProfile'])
        ->where('active', true)
        ->where('language', $lang)
        ->latest()
        ->get()
        ->filter(function ($item) use ($today) {
          return empty($item->end_date) || Carbon::parse($item->end_date)->gte($today);
        })
        ->sortByDesc('created_at')
        ->take(3);
    }

    $poojas = $poojas->map(function ($item) use ($locale, $fallbackText, $lang) {
      $formattedStart = !empty($item->start_date)
        ? Carbon::parse($item->start_date)->locale($locale)->translatedFormat('d F Y')
        : $fallbackText;

      $formattedEnd = !empty($item->end_date)
        ? Carbon::parse($item->end_date)->locale($locale)->translatedFormat('d F Y')
        : $fallbackText;

      $item->start_date = $formattedStart;
      $item->end_date = $formattedEnd;
      $item->pooja_start_date = $formattedStart;
      $item->pooja_end_date = $formattedEnd;

      $item->provider_name = $item->resolveProviderName($lang);

      return $item;
    });

    return response()->json($poojas->values(), 200); // ensure clean JSON indexing
  }
}
