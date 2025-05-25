<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pooja;
use Carbon\Carbon;

class PoojaController extends Controller
{

  public function index($lang = 'en')
  {
    // Validate the language parameter (optional but recommended)
    $validLanguages = ['en', 'hi']; // Add supported languages here
    if (!in_array($lang, $validLanguages)) {
      return response()->json(['error' => 'Invalid language selected'], 400);
    }

    // Fetch active pooja records for the selected language
    $data = Pooja::where([
      ['active', 1],
      ['language', $lang]
    ])->get()->map(function ($item) use ($lang) {
      $locale = ($lang === 'hi') ? 'hi' : 'en'; // Set locale dynamically

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
