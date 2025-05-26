<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\FcmService;

class NotificationController extends Controller
{

  public function create()
  {
    return view('admin.notification.send');
  }

  public function send(Request $request)
  {
    $request->validate([
      'token' => 'required|string',
      'title' => 'required|string',
      'body' => 'required|string',
      'data' => 'nullable|array',
    ]);

    // Ensure all keys in data are strings (required by FCM)
    $data = collect($request->input('data', []))
      ->filter(fn($value, $key) => is_string($key))
      ->map(fn($value) => (string) $value)
      ->toArray();

    $response = FcmService::sendNotification(
      $request->token,
      $request->title,
      $request->body,
      $data
    );

    return back()->with('status', json_encode($response));
  }
}
