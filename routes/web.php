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
use App\Http\Controllers\Admin\DailyInsightRuleController;
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
use App\Http\Controllers\Admin\NotificationController;

use Kreait\Firebase\Factory;

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
    return view('welcome')->with(['id' => "Astro Setu"]);
});

Route::get('/pay_online', function () {
    return view('Frontend/pay_online')->with(['id' => "Pay Online"]);
});

Route::get('/payment_cancelled', function () {
    return view('Frontend/payment/cancelled')->with(['id' => "Cancelled"]);
});

Route::get('/payment_failed', function () {
    return view('Frontend/payment/failed')->with(['id' => "Failed"]);
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
    Route::get('/', [AdminHomeController::class, "index"])->name('admin.home');

    // Super Admin platform administration group
    Route::group(['middleware' => ['super_admin']], function () {
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
        Route::resource('daily-insight-rules', DailyInsightRuleController::class);
        Route::post('daily-insight-rules/{rule}/toggle-active', [DailyInsightRuleController::class, 'toggleActive'])->name("daily-insight-rules.toggle-active");
        Route::resource('product', ProductController::class);
        Route::resource('astrologer', AstrologerController::class);
        Route::post('product_active/{product}', [ProductController::class, 'active'])->name("product.active");
        Route::post('astrologer_active/{astrologer}', [AstrologerController::class, 'active'])->name("astrologer.active");

        // Astro Setu Listing Applications & Approval
        Route::get('/astrologer-applications', [\App\Http\Controllers\Admin\AstrologerApplicationController::class, 'index'])->name('admin.astrologer-applications.index');
        Route::get('/astrologer-applications/{astrologerApplication}', [\App\Http\Controllers\Admin\AstrologerApplicationController::class, 'show'])->name('admin.astrologer-applications.show');
        Route::post('/astrologer-applications/{astrologerApplication}/approve', [\App\Http\Controllers\Admin\AstrologerApplicationController::class, 'approve'])->name('admin.astrologer-applications.approve');
        Route::post('/astrologer-applications/{astrologerApplication}/reject', [\App\Http\Controllers\Admin\AstrologerApplicationController::class, 'reject'])->name('admin.astrologer-applications.reject');

        Route::resource('promoters', PromotersController::class);
        Route::resource('locations', LocationsController::class);
        Route::resource('coupon', CouponController::class);

        Route::get('/fcm/send', [NotificationController::class, 'create'])->name('fcm.create');
        Route::post('/fcm/send', [NotificationController::class, 'send'])->name('fcm.send');

        Route::post('settings/inline-edit', [SettingsController::class, "inlineEdit"]);

        // Protected Diagnostics
        Route::get('/diagnostics/astrology-engine', [App\Http\Controllers\Admin\AstrologyDiagnosticController::class, 'index'])->name('admin.diagnostics.astrology');
        Route::get('/diagnostics/astrology-engine/export', [App\Http\Controllers\Admin\AstrologyDiagnosticController::class, 'export'])->name('admin.diagnostics.astrology.export');
        Route::get('/diagnostics/ffi-compatibility', [App\Http\Controllers\Admin\AstrologyDiagnosticController::class, 'ffiCompatibilityTest'])->name('admin.diagnostics.ffi_compatibility');
    });

    // General / Workspace routes for authenticated admin users (Practitioners & Super Admins)
    Route::get('enquiry/completed',  [InquiryController::class, "completed"])->name('enquiry.completed');
    Route::resource('enquiry', InquiryController::class);

    // Files / FileManager
    Route::controller(FileManagerController::class)->group(function () {
        Route::get('files',  "index")->name("admin.files");
        Route::post('files/folder',  "newFolder");
        Route::post('files/file',  "newFile");
        Route::post('files/ck-upload',  "ckUpload");
        Route::post('files/uploader',  "uploader");
    });
});

Route::get('/clear-cache', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize');
    return "Cache cleared!";
});

Route::get('/fetch-token', function () {
    require 'vendor/autoload.php';

    $credentialsPath = config('services.firebase.credentials_path');

    $client = new Google_Client();
    $client->setAuthConfig($credentialsPath);
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
    $client->fetchAccessTokenWithAssertion();

    echo $client->getAccessToken()['access_token'];
});


require __DIR__ . '/auth.php';

Auth::routes(['confirm' => false]);

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/test-firebase', function () {
    $path = storage_path('app/firebase/navgarah-bba2c-firebase-adminsdk-fbsvc-a2292dc6bc.json');

    if (!file_exists($path)) {
        return response()->json(['error' => 'File not found', 'path' => $path], 404);
    }

    try {
        $factory = (new Factory)->withServiceAccount($path);
        $firestore = $factory->createFirestore()->database();

        return response()->json(['success' => true, 'message' => 'Credentials loaded and Firestore client initialized.']);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

Route::get('/test-env', function () {
    return config('services.firebase.credentials_path', 'not found');
});

Route::get('/know-{id}', [WebController::class, 'index'])
    ->where('id', '[^\-\/]+');

Route::get('/delete-account-request', function () {
    return view('Frontend.delete_account_request');
});

Route::post('/submit-delete-request', [InquiryController::class, 'submitDeleteRequest'])->name('submit-delete-request');

/*
|--------------------------------------------------------------------------
| Phase 10: Pandit Setu - Public Practitioner Profiles & Client Booking
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Web\PublicBookingWebController;

Route::get('/p/{slug}', [PublicBookingWebController::class, 'showProfile'])->name('public.practitioner.profile');
Route::post('/p/{slug}/book', [PublicBookingWebController::class, 'submitBooking'])->name('public.booking.submit');
Route::get('/p/{slug}/booking/{reference}', [PublicBookingWebController::class, 'bookingConfirmation'])->name('public.booking.confirmation');
