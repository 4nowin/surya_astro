<?php

namespace App\Http\Resources\Services;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalServiceOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isClient = $user && $this->user_id === $user->id;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'user_id' => $this->user_id,
            'admin_id' => $this->admin_id,
            'astrologer_id' => $this->astrologer_id,
            'service_id' => $this->service_id,
            'service_name' => $this->service?->name ?? 'Custom Service',
            'service_category' => $this->service?->category,
            'order_type' => $this->order_type,
            'status' => $this->status,
            'base_price' => (float) $this->base_price,
            'platform_markup' => (float) $this->platform_markup,
            'customer_price' => (float) $this->customer_price,
            'currency' => $this->currency ?? 'INR',
            'delivery_timeframe_days' => (int) $this->delivery_timeframe_days,
            'delivery_deadline_at' => $this->delivery_deadline_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'input_payload' => $this->input_payload,
            'deliverable_text' => $this->deliverable_text,
            'deliverable_attachments' => $this->deliverable_attachments ?? [],
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->cancelled_by,
            'cancellation_reason' => $this->cancellation_reason,
            'disputed_at' => $this->disputed_at?->toIso8601String(),
            'dispute_reason' => $this->dispute_reason,
            'dispute_status' => $this->dispute_status,
            'astrologer_name' => $this->astrologer?->name ?? $this->professional?->name,
            'astrologer_avatar' => $this->astrologer?->profile_image_url ?? $this->astrologer?->avatar,
            'can_confirm' => $isClient && $this->status === 'delivered',
            'can_dispute' => $isClient && in_array($this->status, ['submitted', 'in_progress', 'delivered'], true),
            'can_cancel' => $isClient && in_array($this->status, ['pending_payment', 'submitted'], true),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
