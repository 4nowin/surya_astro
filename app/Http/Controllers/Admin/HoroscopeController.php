<?php

namespace App\Http\Controllers\Admin;

use App\Models\Horoscope;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class HoroscopeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Horoscope::where('language', app()->getLocale());

        // Optional date filter
        if ($request->filled('date')) {
            $query->whereDate('start_date', $request->date);
        }

        // Optional horoscope_type filter
        if ($request->filled('horoscope_type')) {
            $query->where('horoscope_type', $request->horoscope_type);
        }

        $horoscopes = $query->latest()->paginate(10);

        return view('admin.horoscope.index', compact('horoscopes'));
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $horoscopes = Horoscope::get();
        return view('admin.horoscope.create', compact('horoscopes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $image = "";
        if ($request->file('image')) {
            $file = $request->file('image');
            $filename = date('YmdHi') . '.' . $file->extension();
            $file->move(public_path('storage/uploads'), $filename);
            $image = $filename;
        }

        // Define zodiac signs in both English and Hindi
        $zodiacSigns = [
            1 => ['en' => 'aries', 'hi' => 'मेष'],
            2 => ['en' => 'taurus', 'hi' => 'वृषभ'],
            3 => ['en' => 'gemini', 'hi' => 'मिथुन'],
            4 => ['en' => 'cancer', 'hi' => 'कर्क'],
            5 => ['en' => 'leo', 'hi' => 'सिंह'],
            6 => ['en' => 'virgo', 'hi' => 'कन्या'],
            7 => ['en' => 'libra', 'hi' => 'तुला'],
            8 => ['en' => 'scorpio', 'hi' => 'वृश्चिक'],
            9 => ['en' => 'sagittarius', 'hi' => 'धनु'],
            10 => ['en' => 'capricorn', 'hi' => 'मकर'],
            11 => ['en' => 'aquarius', 'hi' => 'कुंभ'],
            12 => ['en' => 'pisces', 'hi' => 'मीन'],
        ];

        $tagTypes = [
            1 => ['en' => 'daily', 'hi' => 'दैनिक'],
            2 => ['en' => 'weekly', 'hi' => 'साप्ताहिक'],
            3 => ['en' => 'monthly', 'hi' => 'मासिक'],
            4 => ['en' => 'yearly', 'hi' => 'वर्षिक'],
        ];

        // Get the zodiac sign based on language
        $language = $request->language ?? 'en'; // Default to English if language is not provided
        $zodiacSign = $zodiacSigns[$request->zodiac_sign]['en'] ?? null;
        $tagTypes = $tagTypes[$request->horoscope_type]['en'] ?? null;

        // Merge the zodiac sign into the request
        $request->merge(['zodiac' => $zodiacSign, 'tag' => $tagTypes, 'image' => $image]);

        Horoscope::create($request->only([
            'image',
            'zodiac_sign',
            'horoscope_type',
            'zodiac',
            'mood',
            'tag',
            'language',
            'context',
            'love',
            'career',
            'money',
            'health',
            'travel',
            'lucky_number',
            'lucky_color',
            'lucky_time',
            'start_date',
            'end_date'
        ]));

        return redirect()->route('horoscope.index')
            ->withSuccess(__('Horoscope created successfully.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Horoscope $horoscope)
    {
        return view('admin.horoscope.show', [
            'horoscope' => $horoscope
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Horoscope $horoscope)
    {
        return view('admin.horoscope.edit', [
            'horoscope' => $horoscope
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Horoscope $horoscope)
    {
        // Handle image upload if a new file is provided
        if ($request->file('image')) {
            $file = $request->file('image');
            $filename = date('YmdHi') . '.' . $file->extension();
            $file->move(public_path('storage/uploads'), $filename);
            $request->merge(['image' => $filename]);
        }

        // Define zodiac signs in both English and Hindi
        $zodiacSigns = [
            1 => ['en' => 'aries', 'hi' => 'मेष'],
            2 => ['en' => 'taurus', 'hi' => 'वृषभ'],
            3 => ['en' => 'gemini', 'hi' => 'मिथुन'],
            4 => ['en' => 'cancer', 'hi' => 'कर्क'],
            5 => ['en' => 'leo', 'hi' => 'सिंह'],
            6 => ['en' => 'virgo', 'hi' => 'कन्या'],
            7 => ['en' => 'libra', 'hi' => 'तुला'],
            8 => ['en' => 'scorpio', 'hi' => 'वृश्चिक'],
            9 => ['en' => 'sagittarius', 'hi' => 'धनु'],
            10 => ['en' => 'capricorn', 'hi' => 'मकर'],
            11 => ['en' => 'aquarius', 'hi' => 'कुंभ'],
            12 => ['en' => 'pisces', 'hi' => 'मीन'],
        ];

        $tagTypes = [
            1 => ['en' => 'daily', 'hi' => 'दैनिक'],
            2 => ['en' => 'weekly', 'hi' => 'साप्ताहिक'],
            3 => ['en' => 'monthly', 'hi' => 'मासिक'],
            4 => ['en' => 'yearly', 'hi' => 'वर्षिक'],
        ];

        // Get the zodiac sign based on language
        $language = $request->language ?? 'en'; // Default to English if language is not provided
        $zodiacSign = $zodiacSigns[$request->zodiac_sign]['en'] ?? $horoscope->zodiac;
        $tagTypes = $tagTypes[$request->horoscope_type]['en'] ?? $horoscope->tag;

        // Merge the updated zodiac sign into the request
        $request->merge(['zodiac' => $zodiacSign, 'tag' => $tagTypes]);

        // Update the horoscope record
        $horoscope->update($request->only([
            'image',
            'zodiac_sign',
            'horoscope_type',
            'zodiac',
            'tag',
            'language',
            'context',
            'mood',
            'love',
            'career',
            'money',
            'health',
            'travel',
            'lucky_number',
            'lucky_color',
            'lucky_time',
            'start_date',
            'end_date'
        ]));

        return redirect()->route('horoscope.index')
            ->withSuccess(__('Horoscope updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function active(Request $request, $horoscope_id)
    {
        $horoscope = Horoscope::findOrFail($horoscope_id);
        $horoscope->update(['active' => $request->active_horoscope]);

        return redirect()->route('horoscope.index')->withSuccess(__('Horoscope updated successfully.'));
    }

    public function destroy(Horoscope $horoscope)
    {
        $horoscope->delete();

        return redirect()->route('horoscope.index')
            ->withSuccess(__('Horoscope deleted successfully.'));
    }
}
