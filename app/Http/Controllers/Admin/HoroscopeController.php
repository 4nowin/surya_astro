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

    public const REQUIRED_DAILY_FIELDS = [
        'context',
        'career',
        'love',
        'money',
        'health',
        'travel',
    ];

    /**
     * Evaluate a horoscope record's completeness status.
     *
     * @param Horoscope|null $record
     * @return array{status: 'complete'|'incomplete'|'missing', missing_fields: string[], id: int|null}
     */
    public function evaluateRecordStatus(?Horoscope $record): array
    {
        if ($record === null) {
            return [
                'status' => 'missing',
                'missing_fields' => self::REQUIRED_DAILY_FIELDS,
                'id' => null,
            ];
        }

        $missing = [];
        foreach (self::REQUIRED_DAILY_FIELDS as $field) {
            $raw = (string) $record->{$field};
            // Strip HTML tags and decode entities in case rich text editor submitted empty markup e.g. <p><br></p> or &nbsp;
            $clean = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($clean === '') {
                $missing[] = $field;
            }
        }

        return [
            'status' => empty($missing) ? 'complete' : 'incomplete',
            'missing_fields' => $missing,
            'id' => $record->id,
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $selectedDate = $request->filled('date') ? $request->date : now()->toDateString();
        $query = Horoscope::query();

        // Optional date filter
        if ($request->filled('date')) {
            $query->whereDate('start_date', $request->date);
        }

        // Optional horoscope_type filter
        if ($request->filled('horoscope_type')) {
            $query->where('horoscope_type', $request->horoscope_type);
        }

        // Optional language filter
        if ($request->filled('language')) {
            $query->where('language', $request->language);
        }

        $horoscopes = $query->latest()->paginate(12);

        // Daily publication status matrix for the selected date
        $dailyRecords = Horoscope::where('horoscope_type', 'daily')
            ->whereDate('start_date', $selectedDate)
            ->get();

        $signsMatrix = [];
        $publishedCount = 0;
        foreach (DailyHoroscopeService::SIGNS as $key => $meta) {
            $enRecord = $dailyRecords->first(function ($r) use ($key) {
                return strtolower((string) ($r->zodiac ?: $r->zodiac_sign)) === $key && strtolower((string) $r->language) === 'en';
            });
            $hiRecord = $dailyRecords->first(function ($r) use ($key) {
                return strtolower((string) ($r->zodiac ?: $r->zodiac_sign)) === $key && strtolower((string) $r->language) === 'hi';
            });

            $enEval = $this->evaluateRecordStatus($enRecord);
            $hiEval = $this->evaluateRecordStatus($hiRecord);

            $isComplete = ($enEval['status'] === 'complete' && $hiEval['status'] === 'complete');
            if ($isComplete) {
                $publishedCount++;
            }

            $signsMatrix[$key] = [
                'id' => $meta['id'],
                'sign' => $meta['sign'],
                'enum' => $meta['enum'],
                'name_en' => $meta['name_en'],
                'name_hi' => $meta['name_hi'],
                'vedic_name' => $meta['vedic_name'],
                'en_status' => $enEval['status'],
                'en_missing' => $enEval['missing_fields'],
                'en_id' => $enEval['id'],
                'hi_status' => $hiEval['status'],
                'hi_missing' => $hiEval['missing_fields'],
                'hi_id' => $hiEval['id'],
                'is_complete' => $isComplete,
            ];
        }

        return view('admin.horoscope.index', compact('horoscopes', 'signsMatrix', 'selectedDate', 'publishedCount'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $prefill = [
            'zodiac_sign' => $request->query('zodiac_sign'),
            'language' => $request->query('language', config('app.locale', 'en')),
            'start_date' => $request->query('start_date', now()->toDateString()),
            'horoscope_type' => $request->query('horoscope_type', 'daily'),
        ];
        return view('admin.horoscope.create', compact('prefill'));
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

