<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Astrologer;
use App\Models\Review;
use App\Models\Admin;
use Illuminate\Http\Request;

class AstrologerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $astrologers = Astrologer::where('language', app()->getLocale())->latest()->paginate(10);
        return view('admin.astrologer.index', compact('astrologers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $admins = Admin::all();
        $astrologers = Astrologer::get();
        return view('admin.astrologer.create', compact('astrologers', 'admins'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Handle image upload
        $image = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = now()->format('YmdHi') . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('storage/uploads'), $filename);
            $image = $filename;
        }

        // Build data array
        $data = [
            'image' => $image,
            'name' => $request->name,
            'language' => is_array($request->language) ? implode(', ', $request->language) : $request->language,
            'astrologer_language' => is_array($request->astrologer_language) ? implode(', ', $request->astrologer_language) : $request->astrologer_language,
            'expertise' => is_array($request->expertise) ? implode(', ', $request->expertise) : $request->expertise,
            'experience' => $request->experience,
            'excerpt' => $request->excerpt,
            'description' => $request->description,
            'chat_minutes' => $request->chat_minutes,
            'call_minutes' => $request->call_minutes,
            'price' => $request->price,
            'original_price' => $request->original_price,
        ];

        Astrologer::create($data);

        return redirect()->route('astrologer.index')
            ->withSuccess(__('Astrologer created successfully.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Astrologer $astrologer)
    {
        $reviews = Review::with('user')->where('astrologer_id', $astrologer->id)->get();
        $averageRating = round($reviews->avg('rating'), 1); // 1 decimal place

        return view('admin.astrologer.show', [
            'astrologer' => $astrologer,
            'reviews' => $reviews,
            'averageRating' => $averageRating
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Astrologer $astrologer)
    {
        $admins = Admin::all();
        // Explode the stored comma-separated string into an array
        $astrologer->astrologer_language = explode(',', $astrologer->astrologer_language);
        // Convert expertise string to array
        $astrologer->expertise = $astrologer->expertise
            ? explode(',', $astrologer->expertise)
            : [];
        return view('admin.astrologer.edit', [
            'astrologer' => $astrologer,
            'admins' => $admins
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Astrologer $astrologer)
    {
        // Handle image update if new image is uploaded
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = date('YmdHi') . '.' . $file->extension();
            $file->move(public_path('storage/uploads'), $filename);
            $astrologer->image = $filename;
        }

        // Build clean update data
        $data = [
            'name' => $request->name,
            'language' => is_array($request->language) ? implode(', ', $request->language) : $request->language,
            'astrologer_language' => is_array($request->astrologer_language) ? implode(', ', $request->astrologer_language) : $request->astrologer_language,
            'expertise' => is_array($request->expertise) ? implode(', ', $request->expertise) : $request->expertise,
            'experience' => $request->experience,
            'excerpt' => $request->excerpt,
            'description' => $request->description,
            'chat_minutes' => $request->chat_minutes,
            'call_minutes' => $request->call_minutes,
            'price' => $request->price,
            'original_price' => $request->original_price,
        ];

        $astrologer->update($data);

        return redirect()->route('astrologer.index')
            ->withSuccess(__('Astrologer updated successfully.'));
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Astrologer $astrologer)
    {
        $astrologer->delete();

        return redirect()->route('astrologer.index')
            ->withSuccess(__('Astrologer deleted successfully.'));
    }

    public function active(Request $request, $astrologer_id)
    {
        $astrologer = Astrologer::findOrFail($astrologer_id);
        $astrologer->update(['active' => $request->active_astrologer]);

        return redirect()->route('astrologer.index')->withSuccess(__('Astrologer updated successfully.'));
    }
}
