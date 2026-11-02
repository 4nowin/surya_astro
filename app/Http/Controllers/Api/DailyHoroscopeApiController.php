<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyHoroscopeRequest;
use App\Services\Horoscope\DailyHoroscopeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyHoroscopeApiController extends Controller
{
    public function __construct(
        protected DailyHoroscopeService $horoscopeService
    ) {}

    /**
     * Retrieve daily horoscope for all signs or a specific sign.
     *
     * GET /api/v1/horoscope/daily?date=YYYY-MM-DD&sign={sign}&lang={en|hi}
     */
    public function daily(DailyHoroscopeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $date = $validated['date'] ?? Carbon::today()->toDateString();
        $sign = $validated['sign'] ?? null;
        $lang = $validated['lang'] ?? ($request->header('X-App-Locale') === 'hi' ? 'hi' : 'en');

        $data = $this->horoscopeService->getDailyHoroscope(
            date: $date,
            signKey: $sign,
            lang: $lang
        );

        return response()->json([
            'success' => true,
            'message' => 'Daily horoscope retrieved successfully',
            'data' => $data,
        ], 200, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Retrieve daily horoscope for a specific zodiac sign path parameter.
     *
     * GET /api/v1/horoscope/daily/{sign}?date=YYYY-MM-DD&lang={en|hi}
     */
    public function show(Request $request, string $sign): JsonResponse
    {
        $cleanSign = strtolower(trim($sign));

        if (!DailyHoroscopeService::isValidSign($cleanSign)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid zodiac sign [{$sign}]. Valid signs: " . implode(', ', array_keys(DailyHoroscopeService::SIGNS)),
                'errors' => [
                    'sign' => ["The sign '{$sign}' is not a valid zodiac sign."],
                ],
            ], 422);
        }

        // Validate date and lang if present
        $request->merge(['sign' => $cleanSign]);
        $validator = validator($request->all(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'lang' => ['nullable', 'string', 'in:en,hi'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $date = $request->input('date', Carbon::today()->toDateString());
        $lang = $request->input('lang', $request->header('X-App-Locale') === 'hi' ? 'hi' : 'en');

        $data = $this->horoscopeService->getDailyHoroscope(
            date: $date,
            signKey: $cleanSign,
            lang: $lang
        );

        return response()->json([
            'success' => true,
            'message' => "Daily horoscope for {$cleanSign} retrieved successfully",
            'data' => $data,
        ], 200, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
