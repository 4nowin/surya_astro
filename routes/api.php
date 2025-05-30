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

Route::get('/enquiry', [ApiController::class, "inquiry"]);
Route::get('{id}/products', [ApiController::class, "products"]);
Route::get('{lang?}/poojas', [PoojaController::class, "index"]);
Route::get('{lang?}/home-poojas', [PoojaController::class, 'homePoojas']);
Route::get('{lang?}/horoscopes', [HoroscopeController::class, 'index']);

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

    });
});

Route::post('{lang?}/login', [AuthController::class, "login"]);
Route::post('{lang?}/auth/google', [AuthController::class, 'loginWithGoogle']);
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);


Route::group(['middleware' => ['auth:sanctum'], 'prefix' => "/{lang?}"], function () {
    Route::post('/razorpay/create-donation-order', [PaymentController::class, 'createDonationOrder']);
    Route::post('/razorpay/add-wallet-money', [PaymentController::class, 'addWalletMoney']);
    Route::post('/razorpay/create-pooja-order', [PaymentController::class, 'createPoojaOrder']);
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
});
