<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\AstrologerController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PoojaController;
use App\Http\Controllers\Api\HoroscopeController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AstrologerChatController;
use App\Http\Controllers\Api\FirebaseChatController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SendNotificationController;
use App\Http\Controllers\Api\AstrologyProfileController;
use App\Http\Controllers\Api\CalculationStatusController;
use App\Http\Controllers\Api\DailyPanchangController;
use App\Http\Controllers\Api\DailyHoroscopeApiController;
use App\Http\Controllers\Api\KundliApiController;
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

Route::post('{lang?}/horoscope-notification', [SendNotificationController::class, "sendHoroscopeNotification"]);
Route::post('{lang?}/pooja-notification', [SendNotificationController::class, "sendPoojaNotification"]);
Route::post('{lang?}/astrologer-notification', [SendNotificationController::class, "sendAstrologerNotification"]);
Route::post('{lang?}/donation-notification', [SendNotificationController::class, "sendDonationNotification"]);


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
        Route::get('/booked-poojas', [AstrologerController::class, 'getBookedPoojas']);
        Route::get('/donations', [AstrologerController::class, 'getDonations']);
    });
});

Route::post('{lang?}/login', [AuthController::class, "login"]);
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('/astrology/profiles', AstrologyProfileController::class);
        Route::get('/astrology/personalized-insights/today', [PersonalizedInsightsApiController::class, 'today']);
        Route::post('/astrology/personalized-insights/calculate', [PersonalizedInsightsApiController::class, 'calculate']);
        Route::post('/kundli/generate', [KundliApiController::class, 'generate']);
        Route::get('/astrology/kundli', [KundliApiController::class, 'show']);
        Route::post('/astrology/kundli/calculate', [KundliApiController::class, 'calculate']);
    });
});
