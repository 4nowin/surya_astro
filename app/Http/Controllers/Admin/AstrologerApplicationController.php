<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Astrologer;
use App\Models\AstrologerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AstrologerApplicationController extends Controller
{
    /**
     * Enforce that all application review actions require Super Admin privileges.
     */
    public function __construct()
    {
        $this->middleware(['auth:admin', 'super_admin']);
    }

    /**
     * Display listing of Astro Setu listing applications.
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $query = AstrologerApplication::with(['admin', 'reviewer'])->latest('updated_at');

        if ($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $status);
        }

        $applications = $query->paginate(15)->appends($request->query());

        $counts = [
            'pending' => AstrologerApplication::where('status', 'pending')->count(),
            'approved' => AstrologerApplication::where('status', 'approved')->count(),
            'rejected' => AstrologerApplication::where('status', 'rejected')->count(),
            'all' => AstrologerApplication::count(),
        ];

        return view('admin.astrologer_applications.index', compact('applications', 'status', 'counts'));
    }

    /**
     * Display a specific application with English & Hindi previews.
     */
    public function show(AstrologerApplication $astrologerApplication)
    {
        $astrologerApplication->load(['admin', 'reviewer']);
        $publishedListings = Astrologer::where('admin_id', $astrologerApplication->admin_id)->get();

        return view('admin.astrologer_applications.show', [
            'application' => $astrologerApplication,
            'publishedListings' => $publishedListings,
        ]);
    }

    /**
     * Explicit administrator approval action.
     */
    public function approve(Request $request, AstrologerApplication $astrologerApplication)
    {
        if (empty($astrologerApplication->en_name) || empty($astrologerApplication->hi_name)) {
            return redirect()->back()->with('error', 'Both English and Hindi profile entries must be provided.');
        }

        DB::transaction(function () use ($astrologerApplication) {
            $adminId = $astrologerApplication->admin_id;

            // 1. Create or update English row in astrologers table
            Astrologer::updateOrCreate(
                [
                    'admin_id' => $adminId,
                    'language' => 'en',
                ],
                [
                    'name' => $astrologerApplication->en_name,
                    'image' => $astrologerApplication->image,
                    'language' => 'en',
                    'astrologer_language' => $astrologerApplication->en_languages,
                    'expertise' => $astrologerApplication->en_expertise,
                    'experience' => $astrologerApplication->experience,
                    'excerpt' => $astrologerApplication->en_excerpt,
                    'description' => $astrologerApplication->en_description,
                    'price' => $astrologerApplication->price,
                    'call_price' => $astrologerApplication->call_price,
                    'original_price' => $astrologerApplication->original_price,
                    'chat_minutes' => $astrologerApplication->chat_minutes,
                    'call_minutes' => $astrologerApplication->call_minutes,
                    'active' => 1,
                    'status' => 'online',
                ]
            );

            // 2. Create or update Hindi row in astrologers table
            Astrologer::updateOrCreate(
                [
                    'admin_id' => $adminId,
                    'language' => 'hi',
                ],
                [
                    'name' => $astrologerApplication->hi_name,
                    'image' => $astrologerApplication->image,
                    'language' => 'hi',
                    'astrologer_language' => $astrologerApplication->hi_languages,
                    'expertise' => $astrologerApplication->hi_expertise,
                    'experience' => $astrologerApplication->experience,
                    'excerpt' => $astrologerApplication->hi_excerpt,
                    'description' => $astrologerApplication->hi_description,
                    'price' => $astrologerApplication->price,
                    'call_price' => $astrologerApplication->call_price,
                    'original_price' => $astrologerApplication->original_price,
                    'chat_minutes' => $astrologerApplication->chat_minutes,
                    'call_minutes' => $astrologerApplication->call_minutes,
                    'active' => 1,
                    'status' => 'online',
                ]
            );

            // 3. Mark application as approved with audit trail
            $astrologerApplication->update([
                'status' => 'approved',
                'reviewed_by' => Auth::guard('admin')->id(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);
        });

        return redirect()->route('admin.astrologer-applications.show', $astrologerApplication->id)
            ->with('success', 'Astro Setu listing application approved and published successfully for both English and Hindi.');
    }

    /**
     * Explicit administrator rejection action.
     */
    public function reject(Request $request, AstrologerApplication $astrologerApplication)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        DB::transaction(function () use ($astrologerApplication, $validated) {
            $astrologerApplication->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'reviewed_by' => Auth::guard('admin')->id(),
                'reviewed_at' => now(),
            ]);

            // Ensure listing is not published or active
            Astrologer::where('admin_id', $astrologerApplication->admin_id)->update(['active' => 0]);
        });

        return redirect()->route('admin.astrologer-applications.show', $astrologerApplication->id)
            ->with('success', 'Application marked as rejected. Practitioner can view feedback and resubmit.');
    }
}
