<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Product;

class ApiController extends Controller
{

  public function inquiry()
  {
    $inquiries = Inquiry::orderBy('id', 'DESC')->paginate(10);
    return Inquiry::all();
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
