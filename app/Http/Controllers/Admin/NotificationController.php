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
        ]);

        $response = FcmService::sendNotification(
            $request->token,
            $request->title,
            $request->body,
            $request->data ?? []
        );

        return back()->with('status', json_encode($response));
    }
}
