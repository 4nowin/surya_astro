<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Astrologer;
use App\Models\AstrologerApplication;
use App\Models\ProfessionalPublicProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AstroSetuListingApiController extends Controller
{
    /**
     * Get the authenticated astrologer's listing application status and published data.
     */
    public function getStatus(Request $request)
    {
        $admin = $request->user();

        // 1. Fetch current application
        $application = AstrologerApplication::where('admin_id', $admin->id)
            ->latest('id')
            ->first();

        // 2. Fetch existing published astrologers rows for this admin
        $publishedListings = Astrologer::where('admin_id', $admin->id)->get();
        $isPublished = $publishedListings->where('active', 1)->isNotEmpty();

        // 3. Build sensible prefill data if application doesn't exist
        $publicProfile = ProfessionalPublicProfile::where('admin_id', $admin->id)->first();
        $firstPublished = $publishedListings->first();

        $prefill = [
            'en_name' => $application->en_name ?? $publicProfile->display_name ?? $firstPublished->name ?? $admin->name,
            'en_expertise' => $application->en_expertise ?? (is_array($publicProfile?->specializations) ? implode(', ', $publicProfile->specializations) : ($firstPublished->expertise ?? 'Vedic Astrology, Kundli')),
            'en_languages' => $application->en_languages ?? (is_array($publicProfile?->languages) ? implode(', ', $publicProfile->languages) : ($firstPublished->astrologer_language ?? 'English, Hindi')),
            'en_excerpt' => $application->en_excerpt ?? $publicProfile->headline ?? $firstPublished->excerpt ?? '',
            'en_description' => $application->en_description ?? $publicProfile->about ?? $firstPublished->description ?? '',
            'hi_name' => $application->hi_name ?? '',
            'hi_expertise' => $application->hi_expertise ?? '',
            'hi_languages' => $application->hi_languages ?? 'हिंदी, अंग्रेजी',
            'hi_excerpt' => $application->hi_excerpt ?? '',
            'hi_description' => $application->hi_description ?? '',
            'image' => $application->image ?? $publicProfile->profile_photo_url ?? $firstPublished->image ?? '',
            'experience' => $application->experience ?? ($publicProfile?->years_of_experience ? "{$publicProfile->years_of_experience} years" : ($firstPublished->experience ?? '5 years')),
            'price' => $application->price ?? $firstPublished->price ?? 15,
            'call_price' => $application->call_price ?? $firstPublished->call_price ?? 20,
            'original_price' => $application->original_price ?? $firstPublished->original_price ?? 25,
            'chat_minutes' => $application->chat_minutes ?? $firstPublished->chat_minutes ?? 0,
            'call_minutes' => $application->call_minutes ?? $firstPublished->call_minutes ?? 0,
        ];

        return response()->json([
            'success' => true,
            'status' => $application ? $application->status : 'not_applied',
            'has_application' => $application !== null,
            'application' => $application,
            'is_published' => $isPublished,
            'published_listings' => $publishedListings,
            'prefill' => $prefill,
        ]);
    }

    /**
     * Submit or resubmit an application for Astro Setu listing.
     */
    public function submitApplication(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'en_name' => 'required|string|max:191',
            'en_expertise' => 'required|string|max:255',
            'en_languages' => 'required|string|max:255',
            'en_excerpt' => 'nullable|string|max:500',
            'en_description' => 'nullable|string',
            'hi_name' => 'required|string|max:191',
            'hi_expertise' => 'required|string|max:255',
            'hi_languages' => 'required|string|max:255',
            'hi_excerpt' => 'nullable|string|max:500',
            'hi_description' => 'nullable|string',
            'experience' => 'required|string|max:100',
            'price' => 'required|integer|min:0',
            'call_price' => 'required|integer|min:0',
            'original_price' => 'nullable|integer|min:0',
            'image' => 'nullable|string|max:500',
            'chat_minutes' => 'nullable|integer|min:0',
            'call_minutes' => 'nullable|integer|min:0',
        ]);

        // Handle image upload if a file was passed
        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $filename = 'astrologer_' . $admin->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('astrologers', $filename, 'public');
            $validated['image'] = '/storage/' . $path;
        }

        $application = AstrologerApplication::updateOrCreate(
            ['admin_id' => $admin->id],
            [
                'status' => 'pending',
                'image' => $validated['image'] ?? null,
                'experience' => $validated['experience'],
                'price' => $validated['price'],
                'call_price' => $validated['call_price'],
                'original_price' => $validated['original_price'] ?? 0,
                'chat_minutes' => $validated['chat_minutes'] ?? 0,
                'call_minutes' => $validated['call_minutes'] ?? 0,
                'en_name' => $validated['en_name'],
                'en_expertise' => $validated['en_expertise'],
                'en_languages' => $validated['en_languages'],
                'en_excerpt' => $validated['en_excerpt'] ?? null,
                'en_description' => $validated['en_description'] ?? null,
                'hi_name' => $validated['hi_name'],
                'hi_expertise' => $validated['hi_expertise'],
                'hi_languages' => $validated['hi_languages'],
                'hi_excerpt' => $validated['hi_excerpt'] ?? null,
                'hi_description' => $validated['hi_description'] ?? null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'submitted_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Your application for Astro Setu listing has been submitted for review.',
            'application' => $application,
            'status' => 'pending',
        ], 201);
    }
}
