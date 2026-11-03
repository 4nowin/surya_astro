<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Backward compatibility wrapper delegating to FirebaseNotificationService.
 */
class FirebaseService
{
    protected FirebaseNotificationService $service;

    public function __construct(?FirebaseNotificationService $service = null)
    {
        $this->service = $service ?? app(FirebaseNotificationService::class);
    }

    public function sendNotification($token, $title, $body)
    {
        return $this->service->sendToToken((string) $token, (string) $title, (string) $body);
    }

    public function sendToTopic($topic, $title, $body, $data = [])
    {
        return $this->service->sendToTopic((string) $topic, (string) $title, (string) $body, is_array($data) ? $data : []);
    }

    public function sendToToken($token, $title, $body, $data = [])
    {
        return $this->service->sendToToken((string) $token, (string) $title, (string) $body, is_array($data) ? $data : []);
    }
}
