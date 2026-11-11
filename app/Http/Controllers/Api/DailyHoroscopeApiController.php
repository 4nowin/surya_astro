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
     * Retrieve horoscope for all signs or a specific sign across Daily, Weekly, Monthly, Yearly.
     *
     * GET /api/v1/horoscope?period={daily|weekly|monthly|yearly}&date=YYYY-MM-DD&sign={sign}&lang={en|hi}
     * GET /api/v1/horoscope/daily?date=YYYY-MM-DD&sign={sign}&lang={en|hi}
     */
    public function daily(DailyHoroscopeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $date = $validated['date'] ?? Carbon::today()->toDateString();
        $sign = $validated['sign'] ?? null;
        $lang = $validated['lang'] ?? ($request->header('X-App-Locale') === 'hi' ? 'hi' : 'en');
        $period = $validated['period'] ?? $request->route('period') ?? 'daily';

        $data = $this->horoscopeService->getHoroscope(
            date: $date,
            signKey: $sign,
            lang: $lang,
            period: $period
        );

        $message = ($period === 'daily')
            ? 'Daily horoscope retrieved successfully'
            : ucfirst($period) . ' horoscope retrieved successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 200, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Retrieve horoscope for a specific zodiac sign path parameter.
     *
     * GET /api/v1/horoscope/daily/{sign}?date=YYYY-MM-DD&lang={en|hi}
     * GET /api/v1/horoscope/{period}/{sign}?date=YYYY-MM-DD&lang={en|hi}
     */
    public function show(Request $request, string $param1, ?string $param2 = null): JsonResponse
    {
        if ($param2 !== null) {
            $period = strtolower(trim($param1));
            $cleanSign = strtolower(trim($param2));
        } else {
            $period = (string) $request->input('period', $request->route('period', 'daily'));
            $cleanSign = strtolower(trim($param1));
        }

        if (!DailyHoroscopeService::isValidSign($cleanSign)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid zodiac sign [{$cleanSign}]. Valid signs: " . implode(', ', array_keys(DailyHoroscopeService::SIGNS)),
                'errors' => [
                    'sign' => ["The sign '{$cleanSign}' is not a valid zodiac sign."],
                ],
            ], 422);
        }

        // Validate date, lang, and period if present
        $request->merge(['sign' => $cleanSign, 'period' => $period]);
        $validator = validator($request->all(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'lang' => ['nullable', 'string', 'in:en,hi'],
            'period' => ['nullable', 'string', 'in:daily,weekly,monthly,yearly'],
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
        $validPeriod = in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true) ? $period : 'daily';

        $data = $this->horoscopeService->getHoroscope(
            date: $date,
            signKey: $cleanSign,
            lang: $lang,
            period: $validPeriod
        );

        $message = ($validPeriod === 'daily')
            ? "Daily horoscope for {$cleanSign} retrieved successfully"
            : ucfirst($validPeriod) . " horoscope for {$cleanSign} retrieved successfully";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 200, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
