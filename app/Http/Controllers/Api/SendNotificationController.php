<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\FirebaseService;

class SendNotificationController extends Controller
{
    public function sendHoroscopeNotification($lang = 'en', Request $request)
    {
        $firebase = new FirebaseService();
        return $firebase->sendToTopic(
            'horoscope-'.$lang,
            $request->title,
            $request->description,
            ['type' => 'horoscope']
        );
    }

    public function sendPoojaNotification($lang = 'en', Request $request)
    {
        $firebase = new FirebaseService();
        return $firebase->sendToTopic(
            'pooja-'.$lang,
            $request->title,
            $request->description,
            ['type' => 'pooja']
        );
    }

    public function sendAstrologerNotification($lang = 'en', Request $request)
    {
        $firebase = new FirebaseService();
        return $firebase->sendToTopic(
            'astrologers-'.$lang,
            $request->title,
            $request->description,
            ['type' => 'astrologers']
        );
    }

    public function sendDonationNotification($lang = 'en', Request $request)
    {
        $firebase = new FirebaseService();
        return $firebase->sendToTopic(
            'donation-'.$lang,
            $request->title,
            $request->description,
            ['type' => 'donation']
        );
    }
}
