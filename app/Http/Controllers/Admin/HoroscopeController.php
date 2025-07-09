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
            1 => 'Aries',
            2 => 'Taurus',
            3 => 'Gemini',
            4 => 'Cancer',
            5 => 'Leo',
            6 => 'Virgo',
            7 => 'Libra',
            8 => 'Scorpio',
            9 => 'Sagittarius',
            10 => 'Capricorn',
            11 => 'Aquarius',
            12 => 'Pisces',
        ];

        $tagTypes = [
            1 => 'Daily',
            2 => 'Weekly',
            3 => 'Monthly',
            4 => 'Yearly',
        ];

        // Get the zodiac sign based on language
        $language = $request->language ?? 'en'; // Default to English if language is not provided
        $zodiacSign = $zodiacSigns[$request->zodiac_sign][$language] ?? null;
        $tagTypes = $tagTypes[$request->horoscope_type][$language] ?? null;

        // Merge the zodiac sign into the request
        $request->merge(['zodiac' => $zodiacSign, 'tag' => $tagTypes, 'image' => $image]);

        Horoscope::create($request->only([
            'image',
            'zodiac_sign',
            'horoscope_type',
            'zodiac',
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
            1 => 'Aries',
            2 => 'Taurus',
            3 => 'Gemini',
            4 => 'Cancer',
            5 => 'Leo',
            6 => 'Virgo',
            7 => 'Libra',
            8 => 'Scorpio',
            9 => 'Sagittarius',
            10 => 'Capricorn',
            11 => 'Aquarius',
            12 => 'Pisces',
        ];

        $tagTypes = [
            1 => 'Daily',
            2 => 'Weekly',
            3 => 'Monthly',
            4 => 'Yearly',
        ];

        // Get the zodiac sign based on language
        $language = $request->language ?? 'en'; // Default to English if language is not provided
        $zodiacSign = $zodiacSigns[$request->zodiac_sign][$language] ?? $horoscope->zodiac;
        $tagTypes = $tagTypes[$request->horoscope_type][$language] ?? $horoscope->tag;

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
            'love',
            'career',
            'money',
            'health',
            'travel',
            'lucky_number',
            'lucky_color',
            'start_date',
            'lucky_time',
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
