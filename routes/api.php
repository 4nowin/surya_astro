<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\AstrologerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PoojaController;
use App\Http\Controllers\Api\HoroscopeController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\PaymentController;

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
Route::get('{id}/poojas', [PoojaController::class, "index"]);
Route::get('{lang?}/horoscopes', [HoroscopeController::class, 'index']);

Route::get('{lang?}/astrologers', [AstrologerController::class, 'index']);
Route::get('/astrologers/{id}/reviews', [ReviewController::class, 'index']);

Route::post('/razorpay/create-pooja-order', [PaymentController::class, 'createPoojaOrder']);
Route::post('/razorpay/create-donation-order', [PaymentController::class, 'createDonationOrder']);
Route::post('/razorpay/add-wallet-money', [PaymentController::class, 'addWalletMoney']);
Route::post('/razorpay/verify', [PaymentController::class, 'verifySignature']);
Route::post('/payment/save-wallet', [PaymentController::class, 'saveWallet']);
Route::post('/payment/mark-cancelled', [PaymentController::class, 'markPaymentCancelled']);
Route::post('/razorpay/mark-failed', [PaymentController::class, 'markPaymentFailed']);

Route::post('{lang?}/login', [AuthController::class, "login"]);
Route::post('{lang?}/auth/google', [AuthController::class, 'loginWithGoogle']);
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->post('/update-profile', [UserController::class, 'updateProfile']);
