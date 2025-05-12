<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\WebController;
use App\Http\Controllers\Web\InquiryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\Admin\AdminsController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\PoojaController;
use App\Http\Controllers\Admin\HoroscopeController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\AstrologerController;
use App\Http\Controllers\Admin\CancellationPolicyController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\FileManagerController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\HomeController as AdminHomeController;
use App\Http\Controllers\Admin\LocationsController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PermissionsController;
use App\Http\Controllers\Admin\PromotersController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Models\Horoscope;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('lang/{locale}', function ($locale) {
    if (!in_array($locale, ['en', 'hi'])) {
        abort(400);
    }

    session(['locale' => $locale]);
    return redirect()->back();
})->name('lang.switch');

Route::post('lang/{locale}/switch', function ($locale) {
    if (!in_array($locale, ['en', 'hi'])) {
        abort(400);
    }

    session(['locale' => $locale]);
    return redirect()->back();
})->name('lang.switch.post');

Route::get('/', function () {
    return view('welcome')->with( ['id' => "Navgarah"] );
});

Route::get('/pay_online', function () {
    return view('Frontend/pay_online')->with( ['id' => "Pay Online"] );
});

Route::get('/payment_cancelled', function () {
    return view('Frontend/payment/cancelled')->with( ['id' => "Cancelled"] );
});

Route::get('/payment_failed', function () {
    return view('Frontend/payment/failed')->with( ['id' => "Failed"] );
});

Route::post('/inquiry', [InquiryController::class, 'saveInquiry']);

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get("/admin/login", function () {
    return view("admin.auth.login");
})->name("admin.login");

Route::post("/admin/login", [App\Http\Controllers\Admin\AuthController::class, 'login']);

//payment related routes
Route::group(['prefix' => "/payment"], function () {
    Route::post('/razorpay/checkout', [RazorpayController::class, 'checkout'])->name("payment:razorpay:checkout");
    Route::post('/razorpay/complete', [RazorpayController::class, 'complete'])->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])->name("payment:razorpay:complete");
    Route::post('/razorpay/webhook', [RazorpayController::class, 'webhook'])->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])->name("payment:razorpay:webhook");
});

Route::group([
    'middleware' => ['auth:admin'],
    'prefix' => "/admin"
], function () {
    Route::get('/', [AdminHomeController::class, "index"]);
    Route::resource('roles', RolesController::class);
    Route::resource('users', UsersController::class);
    Route::resource('admins', AdminsController::class);
    Route::post('/auth/check-user-discount', [RazorpayController::class, 'checkUserDiscount']);
    Route::resource('permissions', PermissionsController::class);
    Route::resource('guru', GuruController::class);
    Route::resource('cancellation', CancellationPolicyController::class);
    Route::resource('gallery', GalleryController::class);
    Route::resource('banner', BannerController::class);
    Route::resource('pooja', PoojaController::class);
    Route::post('pooja_active/{pooja}', [PoojaController::class, 'active'])->name("pooja.active");
    Route::resource('horoscope', HoroscopeController::class);
    Route::post('horoscope_active/{horoscope}', [HoroscopeController::class, 'active'])->name("horoscope.active");
    Route::resource('product', ProductController::class);
    Route::resource('astrologer', AstrologerController::class);
    Route::post('product_active/{product}', [ProductController::class, 'active'])->name("product.active");
    Route::post('astrologer_active/{astrologer}', [AstrologerController::class, 'active'])->name("astrologer.active");
    Route::resource('promoters', PromotersController::class);
    Route::resource('locations', LocationsController::class);
    Route::resource('coupon', CouponController::class);

    Route::get('enquiry/completed',  [InquiryController::class, "completed"])->name('enquiry.completed');
    Route::resource('enquiry', InquiryController::class);
    //files
    Route::controller(FileManagerController::class)->group(function () {
        Route::get('files',  "index")->name("admin.files");
        Route::post('files/folder',  "newFolder");
        Route::post('files/file',  "newFile");
        Route::post('files/ck-upload',  "ckUpload");
        Route::post('files/uploader',  "uploader");
    });


    Route::post('settings/inline-edit', [SettingsController::class, "inlineEdit"]);
});

Route::get('/clear-cache', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize');
    return "Cache cleared!";
});

require __DIR__.'/auth.php';

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::resource('/know-{id}', WebController::class);
