<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inquiry;
use App\Models\Pooja;
use App\Models\Horoscope;
use App\Models\Product;
use App\Models\OrderDetail;
use App\Models\Order;
use Razorpay\Api\Api;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ApiController extends Controller
{

  public function login(Request $request, $id)
  {
    // Validate the request
    $request->validate([
      'email' => 'required|string',
      'password' => 'required|string',
    ]);

    // Check if user exists
    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
      return response()->json([
        'success' => false,
        'message' => 'Invalid credentials'
      ], 401);
    }

    // Generate API token for the user
    $token = $user->createToken('authToken')->plainTextToken;

    return response()->json([
      'success' => true,
      'message' => 'Login successful',
      'token' => $token,
      'user' => [
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'role' => $user->role,
      ]
    ], 200);
  }

  public function inquiry()
  {
    $inquiries = Inquiry::orderBy('id', 'DESC')->paginate(10);
    return Inquiry::all();
  }

  public function horoscopes($lang = 'en')
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

    return response()->json([
      'date' =>  Carbon::parse($date->toDateString())
        ->locale($locale)
        ->translatedFormat('d F Y'),
      'daily' => $horoscopes->get('daily', []),
      'weekly' => $horoscopes->get('weekly', []),
      'monthly' => $horoscopes->get('monthly', []),
      'yearly' => $horoscopes->get('yearly', [])
    ]);
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

  public function products($id = 'en')
  {
    $validLanguages = ['en', 'hi'];
    if (!in_array($id, $validLanguages)) {
      return response()->json(['error' => 'Invalid language selected'], 400);
    }

    $data = Product::where([
      ['active', 1],
      ['language', $id]
    ])->get();

    if ($data->isEmpty()) {
      return response()->json(['message' => 'No data found'], 404);
    }

    return response()->json($data, 200);
  }
}
