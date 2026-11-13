<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Astrologer;
use App\Models\AstrologerApplication;
use App\Models\Jajman;
use App\Models\ProfessionalInvoice;
use App\Models\ProfessionalService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index()
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        if ($admin && $admin->isSuperAdmin()) {
            return view('admin.home', [
                'isSuperAdmin' => true,
                'stats' => [
                    'practitioners_count' => Admin::role('Astrologer', 'admin')->count(),
                    'pending_applications_count' => AstrologerApplication::where('status', 'pending')->count(),
                    'approved_astrologers_count' => Astrologer::count(),
                    'users_count' => User::count(),
                ],
                'pendingApplications' => AstrologerApplication::where('status', 'pending')
                    ->latest()
                    ->take(5)
                    ->get(),
                'recentAstrologers' => Astrologer::latest()
                    ->take(5)
                    ->get(),
            ]);
        }

        // Practitioner workspace view
        $adminId = $admin ? $admin->id : 0;
        $application = AstrologerApplication::where('admin_id', $adminId)->latest()->first();
        $publishedListing = Astrologer::where('admin_id', $adminId)->first();

        return view('admin.home', [
            'isSuperAdmin' => false,
            'isPractitioner' => true,
            'application' => $application,
            'publishedListing' => $publishedListing,
            'stats' => [
                'services_count' => ProfessionalService::where('admin_id', $adminId)->count(),
                'jajmans_count' => Jajman::whereHas('professionals', function ($q) use ($adminId) {
                    $q->where('admin_id', $adminId);
                })->count(),
                'invoices_count' => ProfessionalInvoice::where('admin_id', $adminId)->count(),
                'appointments_count' => Appointment::where('admin_id', $adminId)->count(),
            ],
        ]);
    }
}
