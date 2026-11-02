<?php

namespace App\Http\Controllers\Admin;

use App\Models\Horoscope;
use App\Services\Horoscope\DailyHoroscopeService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class HoroscopeController extends Controller
{
    /**
     * Map numeric / string zodiac values to database enum and lowercase slug.
     */
    protected const SIGN_MAP = [
        1 => 'Aries', 2 => 'Taurus', 3 => 'Gemini', 4 => 'Cancer',
        5 => 'Leo', 6 => 'Virgo', 7 => 'Libra', 8 => 'Scorpio',
        9 => 'Sagittarius', 10 => 'Capricorn', 11 => 'Aquarius', 12 => 'Pisces',
    ];

    protected const TYPE_MAP = [
        1 => 'daily', 2 => 'weekly', 3 => 'monthly', 4 => 'yearly',
    ];

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

        $horoscopes = $query->latest()->paginate(12);

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

        // Normalize zodiac sign enum and slug
        $rawSign = $request->zodiac_sign;
        $signEnum = is_numeric($rawSign) ? (self::SIGN_MAP[(int)$rawSign] ?? 'Aries') : ucfirst(strtolower((string)$rawSign));
        $signSlug = strtolower($signEnum);

        // Normalize horoscope type enum
        $rawType = $request->horoscope_type;
        $typeEnum = is_numeric($rawType) ? (self::TYPE_MAP[(int)$rawType] ?? 'daily') : strtolower((string)$rawType);

        // Language
        $language = $request->language ?? 'en';

        // Merge normalized attributes into the request
        $request->merge([
            'zodiac_sign' => $signEnum,
            'horoscope_type' => $typeEnum,
            'zodiac' => $signSlug,
            'tag' => $typeEnum,
            'image' => $image,
            'language' => $language,
        ]);

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

        DailyHoroscopeService::invalidateCache();

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

        // Normalize zodiac sign enum and slug
        $rawSign = $request->zodiac_sign ?? $horoscope->zodiac_sign;
        $signEnum = is_numeric($rawSign) ? (self::SIGN_MAP[(int)$rawSign] ?? 'Aries') : ucfirst(strtolower((string)$rawSign));
        $signSlug = strtolower($signEnum);

        // Normalize horoscope type enum
        $rawType = $request->horoscope_type ?? $horoscope->horoscope_type;
        $typeEnum = is_numeric($rawType) ? (self::TYPE_MAP[(int)$rawType] ?? 'daily') : strtolower((string)$rawType);

        // Merge normalized attributes into the request
        $request->merge([
            'zodiac_sign' => $signEnum,
            'horoscope_type' => $typeEnum,
            'zodiac' => $signSlug,
            'tag' => $typeEnum,
        ]);

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

        DailyHoroscopeService::invalidateCache();

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

        DailyHoroscopeService::invalidateCache();

        return redirect()->route('horoscope.index')->withSuccess(__('Horoscope updated successfully.'));
    }

    public function destroy(Horoscope $horoscope)
    {
        $horoscope->delete();

        DailyHoroscopeService::invalidateCache();

        return redirect()->route('horoscope.index')
            ->withSuccess(__('Horoscope deleted successfully.'));
    }
}

