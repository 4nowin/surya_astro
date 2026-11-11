<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use App\Models\ProfessionalAvailability;
use App\Models\ProfessionalAvailabilityException;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AppointmentConflictService
{
    /**
     * Check if a proposed time slot conflicts with existing appointments.
     *
     * @param int $adminId
     * @param Carbon $startUtc
     * @param Carbon $endUtc
     * @param int|null $excludeAppointmentId
     * @return Appointment|null
     */
    public function findConflictingAppointment(
        int $adminId,
        Carbon $startUtc,
        Carbon $endUtc,
        ?int $excludeAppointmentId = null
    ): ?Appointment {
        $query = Appointment::where('admin_id', $adminId)
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) use ($startUtc, $endUtc) {
                $q->where('start_at', '<', $endUtc)
                  ->where('end_at', '>', $startUtc);
            });

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->first();
    }

    /**
     * Validate whether the appointment slot is within practitioner's configured working hours.
     *
     * @param int $adminId
     * @param Carbon $startUtc
     * @param Carbon $endUtc
     * @param string $timezone
     * @return array [bool $isAvailable, string|null $reason]
     */
    public function checkPractitionerAvailability(
        int $adminId,
        Carbon $startUtc,
        Carbon $endUtc,
        string $timezone = 'Asia/Kolkata'
    ): array {
        // Convert to practitioner's local time
        $localStart = $startUtc->copy()->setTimezone($timezone);
        $localEnd = $endUtc->copy()->setTimezone($timezone);

        $localDate = $localStart->toDateString(); // YYYY-MM-DD
        $localStartTime = $localStart->format('H:i');
        $localEndTime = $localEnd->format('H:i');
        $dayOfWeek = (int) $localStart->dayOfWeek; // 0 = Sunday, 1 = Monday, ... 6 = Saturday

        // 1. Check Date-Specific Exception first (Leave, Holiday, Custom Hours)
        $exception = ProfessionalAvailabilityException::where('admin_id', $adminId)
            ->where('date', $localDate)
            ->first();

        if ($exception) {
            if ($exception->type === 'leave') {
                return [false, "Practitioner is on leave on {$localDate}" . ($exception->reason ? " ({$exception->reason})" : '')];
            }
            if ($exception->type === 'holiday') {
                return [false, "Practitioner has marked {$localDate} as a holiday" . ($exception->reason ? " ({$exception->reason})" : '')];
            }
            if ($exception->type === 'custom_hours' && !empty($exception->slots)) {
                $fitsCustom = $this->isTimeRangeWithinSlots($localStartTime, $localEndTime, $exception->slots);
                if (!$fitsCustom) {
                    return [false, "Requested time {$localStartTime}-{$localEndTime} is outside practitioner's special hours on {$localDate}"];
                }
                return [true, null];
            }
        }

        // 2. Check Weekly Recurring Schedule
        $weekly = ProfessionalAvailability::where('admin_id', $adminId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        // If no schedule configured yet, allow by default
        if (!$weekly) {
            return [true, null];
        }

        if (!$weekly->is_enabled) {
            $dayName = $localStart->format('l');
            return [false, "Practitioner does not take appointments on {$dayName}s"];
        }

        if (!empty($weekly->slots)) {
            $fits = $this->isTimeRangeWithinSlots($localStartTime, $localEndTime, $weekly->slots);
            if (!$fits) {
                return [false, "Requested time {$localStartTime}-{$localEndTime} is outside practitioner's working intervals or falls in a break"];
            }
        }

        return [true, null];
    }

    /**
     * Helper to verify if [startTime, endTime] fits inside at least one slot.
     */
    protected function isTimeRangeWithinSlots(string $startTime, string $endTime, array $slots): bool
    {
        foreach ($slots as $slot) {
            $slotStart = $slot['start'] ?? null;
            $slotEnd = $slot['end'] ?? null;

            if ($slotStart && $slotEnd) {
                if ($startTime >= $slotStart && $endTime <= $slotEnd) {
                    return true;
                }
            }
        }

        return false;
    }
}
