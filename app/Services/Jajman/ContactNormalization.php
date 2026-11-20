<?php

declare(strict_types=1);

namespace App\Services\Jajman;

class ContactNormalization
{
    /**
     * Normalize a phone number to its canonical last 10 digits.
     * Returns null if the phone is null, empty, or has fewer than 7 numeric digits.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) < 7) {
            return null;
        }

        if (strlen($digits) >= 10) {
            return substr($digits, -10);
        }

        return $digits;
    }

    /**
     * Normalize an email address by trimming and lowercasing.
     * Returns null if empty or invalid email format.
     */
    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $trimmed = strtolower(trim($email));
        if ($trimmed === '' || !filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * Check if two phone numbers match under canonical normalization.
     */
    public static function phonesMatch(?string $phoneA, ?string $phoneB): bool
    {
        $normA = self::normalizePhone($phoneA);
        $normB = self::normalizePhone($phoneB);

        if ($normA === null || $normB === null) {
            return false;
        }

        return $normA === $normB;
    }

    /**
     * Check if two emails match under canonical normalization.
     */
    public static function emailsMatch(?string $emailA, ?string $emailB): bool
    {
        $normA = self::normalizeEmail($emailA);
        $normB = self::normalizeEmail($emailB);

        if ($normA === null || $normB === null) {
            return false;
        }

        return $normA === $normB;
    }
}
