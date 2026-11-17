<?php

namespace App\Http\Resources\Services;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $astrologer = $this->professional?->astrologer;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'admin_id' => $this->admin_id,
            'service_id' => $this->service_id,
            'title' => $this->title,
            'description' => $this->description,
            'appointment_type' => $this->appointment_type,
            'booking_source' => $this->booking_source,
            'status' => $this->status,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'timezone' => $this->timezone ?? 'Asia/Kolkata',
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            'acceptance_deadline_at' => $this->acceptance_deadline_at?->toIso8601String(),
            'is_acceptance_expired' => $this->isAcceptanceExpired(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'cancelled_by' => $this->cancelled_by,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'is_cancellation_allowed' => $this->isCancellationAllowed(12),
            'is_inside_cancellation_cutoff' => $this->isInsideCancellationCutoff(12),
            'no_show_reported_at' => $this->no_show_reported_at?->toIso8601String(),
            'no_show_reported_by' => $this->no_show_reported_by,
            'no_show_notes' => $this->no_show_notes,
            'no_show_review_status' => $this->no_show_review_status ?? 'none',
            'base_price' => (float) ($this->base_price ?? $this->agreed_price_snapshot ?? 0),
            'platform_markup' => (float) ($this->platform_markup ?? 0),
            'customer_price' => (float) ($this->customer_price ?? 0),
            'currency' => $this->currency_snapshot ?? 'INR',
            'duration_minutes' => (int) ($this->duration_minutes_snapshot ?? 30),
            'astrologer_name' => $astrologer?->name ?? $this->professional?->name,
            'astrologer_avatar' => $astrologer?->profile_image_url ?? $astrologer?->avatar,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
