<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Astrologer;
use App\Models\ProfessionalService;

class ChatServiceProvisioner
{
    public const DEFAULT_BASE_PRICE = 0.10;
    public const DEFAULT_MAX_CHARS = 1000;

    /**
     * Ensure the admin/astrologer has a default Chat Consultation service configured.
     * Idempotent: Does not create duplicates if one already exists.
     */
    public function provisionForAdmin(int $adminId, bool $force = false): ProfessionalService
    {
        $existing = ProfessionalService::where('admin_id', $adminId)
            ->where(function ($q) {
                $q->where('offering_type', 'chat')
                  ->orWhere('pricing_model', 'per_message')
                  ->orWhereJsonContains('delivery_methods', 'chat');
            })
            ->first();

        if ($existing && !$force) {
            return $existing;
        }

        if ($existing && $force) {
            $existing->update([
                'offering_type' => 'chat',
                'pricing_model' => 'per_message',
                'price' => self::DEFAULT_BASE_PRICE,
                'delivery_methods' => ['chat', 'online'],
                'is_active' => true,
                'is_public' => true,
                'is_free_chat' => false,
                'max_message_characters' => self::DEFAULT_MAX_CHARS,
            ]);
            return $existing;
        }

        return ProfessionalService::create([
            'admin_id' => $adminId,
            'name' => 'Chat Consultation',
            'description' => 'Direct real-time Vedic astrological consultation via chat.',
            'category' => 'astrologer',
            'specialization' => 'Vedic Astrology Consultation',
            'offering_type' => 'chat',
            'pricing_model' => 'per_message',
            'price' => self::DEFAULT_BASE_PRICE,
            'currency' => 'INR',
            'duration_minutes' => 30,
            'delivery_methods' => ['chat', 'online'],
            'is_active' => true,
            'is_public' => true,
            'is_free_chat' => false,
            'max_message_characters' => self::DEFAULT_MAX_CHARS,
            'preparation_instructions' => 'Please have your birth date, time, and birthplace ready before initiating the chat.',
        ]);
    }

    /**
     * Convenience method to provision default chat consultation for an Astrologer model.
     */
    public function provisionForAstrologer(Astrologer $astrologer, bool $force = false): ProfessionalService
    {
        return $this->provisionForAdmin((int) $astrologer->admin_id, $force);
    }

    /**
     * Backfill all active astrologers with a default Chat Consultation service.
     */
    public function provisionAllActiveAstrologers(): int
    {
        $astrologers = Astrologer::whereNotNull('admin_id')
            ->where('active', 1)
            ->get();

        $count = 0;
        foreach ($astrologers as $astro) {
            $service = $this->provisionForAdmin((int) $astro->admin_id);
            if ($service->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }
}
