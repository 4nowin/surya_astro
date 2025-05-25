<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Astrologer;

class NotificationController extends Controller
{

  public function saveToken(Request $request)
  {
    $request->validate([
      'astrologer_id' => 'required|string',
      'fcm_token' => 'required|string',
    ]);

    Astrologer::updateOrInsert(
      ['id' => $request->astrologer_id],
      ['fcm_token' => $request->fcm_token, 'updated_at' => now()]
    );

    return response()->json(['status' => 'success']);
  }

  
}
