<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Backward compatibility wrapper delegating static calls to FirebaseNotificationService.
 */
class FcmService
{
    public static function sendNotification($token, $title, $body, $data = [])
    {
        /** @var FirebaseNotificationService $service */
        $service = app(FirebaseNotificationService::class);
        return $service->sendToToken((string) $token, (string) $title, (string) $body, is_array($data) ? $data : []);
    }

    public static function sendToTopic($topic, $title, $body, $data = [])
    {
        /** @var FirebaseNotificationService $service */
        $service = app(FirebaseNotificationService::class);
        return $service->sendToTopic((string) $topic, (string) $title, (string) $body, is_array($data) ? $data : []);
    }
}
