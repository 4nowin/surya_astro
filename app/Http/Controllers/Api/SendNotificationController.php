<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\FirebaseService;

class SendNotificationController extends Controller
{
    public function sendHiHoroscopeNotification(Request $request)
    {
        $firebase = new FirebaseService();
        return $firebase->sendToTopic(
            'horoscope-hi',
            'आज का राशिफल 🔮',
            'मेष राशि के लिए शुभ दिन है!',
            ['type' => 'horoscope']
        );
    }
}
