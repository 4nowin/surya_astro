<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalAvailability;
use App\Models\ProfessionalAvailabilityException;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AvailabilityApiController extends Controller
{
    /**
     * 1. GET /api/astrologer/availability
     * Get practitioner's weekly recurring availability (Sunday = 0 to Saturday = 6).
     */
    public function getWeekly(Request $request)
    {
        $admin = $request->user();

        $availabilities = ProfessionalAvailability::where('admin_id', $admin->id)
            ->orderBy('day_of_week', 'asc')
            ->get();

        // If not configured yet, generate default 7-day schedule representation
        if ($availabilities->isEmpty()) {
            $default = [];
            for ($d = 0; $d <= 6; $d++) {
                $isWorkDay = ($d >= 1 && $d <= 6); // Mon - Sat default enabled
                $default[] = [
                    'day_of_week' => $d,
                    'is_enabled' => $isWorkDay,
                    'timezone' => 'Asia/Kolkata',
                    'slots' => $isWorkDay ? [
                        ['start' => '09:00', 'end' => '13:00'],
                        ['start' => '14:00', 'end' => '18:00'], // Lunch break between 13:00 and 14:00
                    ] : [],
                ];
            }

            return response()->json([
                'success' => true,
                'is_default' => true,
                'data' => $default,
            ]);
        }

        return response()->json([
            'success' => true,
            'is_default' => false,
            'data' => $availabilities,
        ]);
    }

    /**
     * 2. PUT /api/astrologer/availability
     * Update/upsert the entire weekly availability schedule.
     */
    public function updateWeekly(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'timezone' => 'nullable|string|max:64',
            'days' => 'required|array|min:1|max:7',
            'days.*.day_of_week' => 'required|integer|between:0,6',
            'days.*.is_enabled' => 'required|boolean',
            'days.*.slots' => 'nullable|array',
            'days.*.slots.*.start' => 'required_with:days.*.slots|date_format:H:i',
            'days.*.slots.*.end' => 'required_with:days.*.slots|date_format:H:i|after:days.*.slots.*.start',
        ]);

        $tzName = $validated['timezone'] ?? 'Asia/Kolkata';

        DB::transaction(function () use ($admin, $validated, $tzName) {
            foreach ($validated['days'] as $dayData) {
                ProfessionalAvailability::updateOrCreate(
                    [
                        'admin_id' => $admin->id,
                        'day_of_week' => $dayData['day_of_week'],
                    ],
                    [
                        'is_enabled' => (bool) $dayData['is_enabled'],
                        'slots' => $dayData['slots'] ?? [],
                        'timezone' => $tzName,
                    ]
                );
            }
        });

        $updated = ProfessionalAvailability::where('admin_id', $admin->id)
            ->orderBy('day_of_week', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Weekly availability saved successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * 3. GET /api/astrologer/availability/exceptions
     * List date-specific exceptions (leaves, holidays, custom hours).
     */
    public function listExceptions(Request $request)
    {
        $admin = $request->user();

        $query = ProfessionalAvailabilityException::where('admin_id', $admin->id);

        if ($from = $request->input('from')) {
            $query->where('date', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->where('date', '<=', $to);
        }

        $exceptions = $query->orderBy('date', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $exceptions,
        ]);
    }

    /**
     * 4. POST /api/astrologer/availability/exceptions
     * Add a date-specific availability exception.
     */
    public function storeException(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'type' => ['required', Rule::in(['leave', 'holiday', 'custom_hours'])],
            'reason' => 'nullable|string|max:191',
            'slots' => 'nullable|array',
            'slots.*.start' => 'required_with:slots|date_format:H:i',
            'slots.*.end' => 'required_with:slots|date_format:H:i|after:slots.*.start',
        ]);

        $exception = ProfessionalAvailabilityException::updateOrCreate(
            [
                'admin_id' => $admin->id,
                'date' => $validated['date'],
            ],
            [
                'type' => $validated['type'],
                'reason' => $validated['reason'] ?? null,
                'slots' => $validated['slots'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Availability exception recorded.',
            'data' => $exception,
        ], 201);
    }

    /**
     * 5. PUT /api/astrologer/availability/exceptions/{id}
     */
    public function updateException(Request $request, $id)
    {
        $admin = $request->user();

        $exception = ProfessionalAvailabilityException::where('admin_id', $admin->id)->find($id);
        if (!$exception) {
            return response()->json(['success' => false, 'message' => 'Exception not found.'], 404);
        }

        $validated = $request->validate([
            'type' => ['sometimes', 'required', Rule::in(['leave', 'holiday', 'custom_hours'])],
            'reason' => 'nullable|string|max:191',
            'slots' => 'nullable|array',
            'slots.*.start' => 'required_with:slots|date_format:H:i',
            'slots.*.end' => 'required_with:slots|date_format:H:i|after:slots.*.start',
        ]);

        $exception->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Exception updated.',
            'data' => $exception->fresh(),
        ]);
    }

    /**
     * 6. DELETE /api/astrologer/availability/exceptions/{id}
     */
    public function destroyException(Request $request, $id)
    {
        $admin = $request->user();

        $exception = ProfessionalAvailabilityException::where('admin_id', $admin->id)->find($id);
        if (!$exception) {
            return response()->json(['success' => false, 'message' => 'Exception not found.'], 404);
        }

        $exception->delete();

        return response()->json([
            'success' => true,
            'message' => 'Availability exception removed.',
        ]);
    }
}
