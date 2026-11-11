<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\AstrologerController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AppointmentApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityApiController;
use App\Http\Controllers\Api\ConsultationApiController;
use App\Http\Controllers\Api\JajmanApiController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PoojaController;
use App\Http\Controllers\Api\HoroscopeController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ChatApiController;
use App\Http\Controllers\Api\AstrologerChatController;
use App\Http\Controllers\Api\FirebaseChatController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SendNotificationController;
use App\Http\Controllers\Api\AstrologyProfileController;
use App\Http\Controllers\Api\CalculationStatusController;
use App\Http\Controllers\Api\DailyPanchangController;
use App\Http\Controllers\Api\DailyHoroscopeApiController;
use App\Http\Controllers\Api\KundliApiController;
use App\Http\Controllers\Api\KundliReportDeliveryController;
use App\Http\Controllers\Api\PersonalizedInsightsApiController;
use App\Http\Controllers\Api\GeoController;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Legacy broadcast routes secured behind authentication
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('{lang?}/horoscope-notification', [SendNotificationController::class, "sendHoroscopeNotification"]);
    Route::post('{lang?}/pooja-notification', [SendNotificationController::class, "sendPoojaNotification"]);
    Route::post('{lang?}/astrologer-notification', [SendNotificationController::class, "sendAstrologerNotification"]);
    Route::post('{lang?}/donation-notification', [SendNotificationController::class, "sendDonationNotification"]);
});


Route::get('/enquiry', [ApiController::class, "inquiry"]);
Route::get('{id}/products', [ApiController::class, "products"]);
Route::get('{lang?}/poojas', [PoojaController::class, "index"]);
Route::get('{lang?}/home-poojas', [PoojaController::class, 'homePoojas']);
Route::get('{lang?}/horoscopes', [HoroscopeController::class, 'index']);
Route::get('{lang?}/home-horoscopes', [HoroscopeController::class, 'homeHoroscope']);

Route::get('{lang?}/astrologers', [AstrologerController::class, 'index']);
Route::get('/astrologers/{id}/reviews', [ReviewController::class, 'index']);


Route::prefix('astrologer')->group(function () {
    // 🔐 Public Auth Routes
    Route::post('/astro-register', [AdminAuthController::class, 'register']);
    Route::post('/astro-login', [AdminAuthController::class, 'login']);

    Route::post('/save-token', [NotificationController::class, 'saveToken']);

    // 🔐 Protected Routes for Astrologers
    Route::middleware(['auth:sanctum', 'role:Astrologer'])->group(function () {
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);

        Route::get('/chat-sessions', [AstrologerChatController::class, 'chatSessions']);
        Route::get('/get-status', [AstrologerChatController::class, 'getStatus']);
        Route::post('/set-online', [AstrologerChatController::class, 'setOnline']);
        Route::post('/toggle-online', [AstrologerChatController::class, 'toggleOnline']);
        Route::get('/chat/astrologer/history', [AstrologerChatController::class, 'getAstrologerChats']);
        Route::post('/chat/ably-token', [ChatApiController::class, 'createAblyToken']);
        Route::post('/chat/message/send', [ChatApiController::class, 'sendMessage']);
        Route::get('/chat/sessions/{sessionId}/messages', [ChatApiController::class, 'getMessages']);
        Route::post('/chat/session/end', [ChatApiController::class, 'endSession']);
        Route::get('/booked-poojas', [AstrologerController::class, 'getBookedPoojas']);
        Route::get('/donations', [AstrologerController::class, 'getDonations']);

        // 📇 Pandit Setu - Jajman CRM Routes
        Route::apiResource('/jajmans', JajmanApiController::class);
        Route::get('/jajmans/{id}/birth-details', [JajmanApiController::class, 'getBirthDetails']);
        Route::post('/jajmans/{id}/birth-details', [JajmanApiController::class, 'storeBirthDetail']);
        Route::put('/jajmans/{id}/birth-details/{birthDetailId}', [JajmanApiController::class, 'updateBirthDetail']);
        Route::delete('/jajmans/{id}/birth-details/{birthDetailId}', [JajmanApiController::class, 'destroyBirthDetail']);

        Route::get('/jajmans/{id}/family', [JajmanApiController::class, 'getFamily']);
        Route::post('/jajmans/{id}/family', [JajmanApiController::class, 'storeFamily']);
        Route::delete('/jajmans/{id}/family/{relationshipId}', [JajmanApiController::class, 'destroyFamily']);

        Route::get('/jajmans/{id}/notes', [JajmanApiController::class, 'getNotes']);
        Route::post('/jajmans/{id}/notes', [JajmanApiController::class, 'storeNote']);
        Route::put('/jajmans/{id}/notes/{noteId}', [JajmanApiController::class, 'updateNote']);
        Route::delete('/jajmans/{id}/notes/{noteId}', [JajmanApiController::class, 'destroyNote']);

        // 💬 Pandit Setu - Consultations & Conversations
        Route::get('/conversations', [ConsultationApiController::class, 'getConversations']);
        Route::get('/consultations', [ConsultationApiController::class, 'index']);
        Route::post('/consultations', [ConsultationApiController::class, 'store']);

        // 📅 Pandit Setu - Appointments & Calendar Management
        Route::get('/appointments', [AppointmentApiController::class, 'index']);
        Route::post('/appointments', [AppointmentApiController::class, 'store']);
        Route::get('/appointments/{id}', [AppointmentApiController::class, 'show']);
        Route::put('/appointments/{id}', [AppointmentApiController::class, 'update']);
        Route::post('/appointments/{id}/reschedule', [AppointmentApiController::class, 'reschedule']);
        Route::post('/appointments/{id}/cancel', [AppointmentApiController::class, 'cancel']);
        Route::post('/appointments/{id}/complete', [AppointmentApiController::class, 'complete']);
        Route::post('/appointments/{id}/no-show', [AppointmentApiController::class, 'noShow']);
        Route::get('/calendar', [AppointmentApiController::class, 'calendar']);

        // ⏰ Pandit Setu - Practitioner Availability
        Route::get('/availability', [AvailabilityApiController::class, 'getWeekly']);
        Route::put('/availability', [AvailabilityApiController::class, 'updateWeekly']);
        Route::get('/availability/exceptions', [AvailabilityApiController::class, 'listExceptions']);
        Route::post('/availability/exceptions', [AvailabilityApiController::class, 'storeException']);
        Route::put('/availability/exceptions/{id}', [AvailabilityApiController::class, 'updateException']);
        Route::delete('/availability/exceptions/{id}', [AvailabilityApiController::class, 'destroyException']);
    });
});

Route::post('{lang?}/login', [AuthController::class, "login"]);
Route::post('{lang?}/register', [AuthController::class, "register"]);
Route::post('{lang?}/auth/google', [AuthController::class, 'loginWithGoogle']);
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::get('/fcm-token', [NotificationController::class, 'getAccessToken']);
Route::post('send-to-astrologer', [NotificationController::class, 'sendToAstrologer']);


Route::group(['middleware' => ['auth:sanctum'], 'prefix' => "/{lang?}"], function () {
    Route::post('/razorpay/create-donation-order', [PaymentController::class, 'createDonationOrder']);
    Route::post('/razorpay/add-wallet-money', [PaymentController::class, 'addWalletMoney']);
    Route::post('/razorpay/create-pooja-order', [PaymentController::class, 'createPoojaOrder']);
    Route::post('/razorpay/create-premium-order', [PaymentController::class, 'createPremiumUser']);
    Route::post('/razorpay/verify', [PaymentController::class, 'verifySignature']);
    Route::post('/razorpay/payment/save-wallet', [PaymentController::class, 'saveWallet']);
    Route::post('/razorpay/payment/mark-cancelled', [PaymentController::class, 'markPaymentCancelled']);
    Route::post('/razorpay/payment/mark-failed', [PaymentController::class, 'markPaymentFailed']);

    Route::post('/chat/start-session', [ChatController::class, 'startSession']);
    Route::post('/chat/end-session', [ChatController::class, 'endChatSession']);
    Route::post('/chat/ably-token', [ChatApiController::class, 'createAblyToken']);
    Route::post('/chat/message/send', [ChatApiController::class, 'sendMessage']);
    Route::get('/chat/sessions/{sessionId}/messages', [ChatApiController::class, 'getMessages']);
    Route::post('/chat/session/end', [ChatApiController::class, 'endSession']);

    Route::post('/update-user-fcm-token', [UserController::class, 'updateFcmToken']);


    Route::post('/firebase-chat/store', [FirebaseChatController::class, 'store']);
    Route::post('/chat/deduct-fee', [ChatController::class, 'deductFee']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/update-profile', [UserController::class, 'updateProfile']);
    Route::get('/user-details', [UserController::class, 'userProfile']);
    Route::post('/wallet/pay-for-premium', [PaymentController::class, 'payForPremium']);
    Route::get('/auth/check', function (Request $request) {
        $user = $request->user();

        // Check if user is premium and the subscription is over 30 days old
        if ($user->role === 'premium' && $user->updated_at->diffInDays(Carbon::now()) > 30) {
            $user->role = 'free';
            $user->save();
        }
        return response()->json(['success' => true, 'user' => $user]);
    });
});

/*
|--------------------------------------------------------------------------
| Mini-Phase 2: Astrology Profile & Geocoding API Routes (v1)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::get('/geo/cities', [GeoController::class, 'cities']);
    Route::get('/panchang/daily', [DailyPanchangController::class, 'daily']);
    Route::get('/calculations/{jobId}', [CalculationStatusController::class, 'show']);
    Route::get('/horoscope/daily', [DailyHoroscopeApiController::class, 'daily']);
    Route::get('/horoscope/daily/{sign}', [DailyHoroscopeApiController::class, 'show']);
    Route::get('/horoscope', [DailyHoroscopeApiController::class, 'daily']);
    Route::get('/horoscope/{period}', [DailyHoroscopeApiController::class, 'daily'])->where('period', 'daily|weekly|monthly|yearly');
    Route::get('/horoscope/{period}/{sign}', [DailyHoroscopeApiController::class, 'show'])->where('period', 'daily|weekly|monthly|yearly');

    Route::middleware('auth:sanctum')->group(function () {
        Route::match(['post', 'put'], '/astrology/profiles/primary', [AstrologyProfileController::class, 'savePrimary']);
        Route::apiResource('/astrology/profiles', AstrologyProfileController::class);
        Route::get('/astrology/personalized-insights/today', [PersonalizedInsightsApiController::class, 'today']);
        Route::post('/astrology/personalized-insights/calculate', [PersonalizedInsightsApiController::class, 'calculate']);
        Route::post('/kundli/generate', [KundliApiController::class, 'generate']);
        Route::get('/astrology/kundli', [KundliApiController::class, 'show']);
        Route::post('/astrology/kundli/calculate', [KundliApiController::class, 'calculate']);

        // M6: Kundli Details, Dashas, Saved Reports, and PDF Export
        Route::get('/kundli/{profileId}/dashas', [KundliApiController::class, 'dashas']);
        Route::get('/astrology/kundli/{profileId}/dashas', [KundliApiController::class, 'dashas']);
        Route::get('/kundli/saved', [KundliApiController::class, 'listSaved']);
        Route::get('/kundli/saved/{id}', [KundliApiController::class, 'showSaved']);
        Route::post('/kundli/save', [KundliApiController::class, 'saveSnapshot']);
        Route::delete('/kundli/saved/{id}', [KundliApiController::class, 'deleteSaved']);
        Route::get('/kundli/pdf', [KundliApiController::class, 'downloadPdf']);
        Route::get('/astrology/kundli/pdf', [KundliApiController::class, 'downloadPdf']);

        // M8: Daily Notifications & Device Token Management
        Route::post('/notifications/devices/register', [\App\Http\Controllers\Api\NotificationSettingsApiController::class, 'registerDevice']);
        Route::post('/notifications/devices/remove', [\App\Http\Controllers\Api\NotificationSettingsApiController::class, 'removeDevice']);
        Route::get('/notifications/preferences', [\App\Http\Controllers\Api\NotificationSettingsApiController::class, 'getPreferences']);
        Route::put('/notifications/preferences', [\App\Http\Controllers\Api\NotificationSettingsApiController::class, 'updatePreferences']);

        // M9.2 Work Package A4: Detailed Kundli Report Commerce, Lifecycle & Delivery
        Route::post('/kundli/reports/purchase', [KundliReportDeliveryController::class, 'purchase']);
        Route::get('/kundli/reports/{reportUuid}/status', [KundliReportDeliveryController::class, 'status']);
        Route::get('/kundli/reports/{reportUuid}/download-url', [KundliReportDeliveryController::class, 'downloadUrl']);
    });

    // M9.2 Work Package A4: Temporary Signed Download Route (signature-validated)
    Route::get('/kundli/reports/download/{reportUuid}', [KundliReportDeliveryController::class, 'download'])
        ->name('kundli.reports.download');
});
