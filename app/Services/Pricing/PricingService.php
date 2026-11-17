<?php

declare(strict_types=1);

namespace App\Services\Pricing;

class PricingService
{
    /**
     * Platform markup percentage added onto astrologer base price.
     */
    public const PLATFORM_MARKUP_PERCENT = 20;

    /**
     * Convert an INR amount (float, int, or numeric string) into integer paise.
     */
    public static function inrToPaise(float|int|string $inr): int
    {
        return (int) round(((float) $inr) * 100);
    }

    /**
     * Convert integer paise to decimal INR rounded to 2 decimal places.
     */
    public static function paiseToInr(int $paise): float
    {
        return round($paise / 100, 2);
    }

    /**
     * Calculate commercial pricing breakdown using integer paise.
     *
     * @param float|int|string $basePriceInr Astrologer's configured base price
     * @param bool $isFree Whether the service is explicitly marked free
     * @return array{
     *   is_free: bool,
     *   base_price_paise: int,
     *   markup_paise: int,
     *   customer_price_paise: int,
     *   base_price: float,
     *   platform_markup: float,
     *   customer_price: float,
     *   astrologer_earning: float
     * }
     */
    public function calculateServicePrice(float|int|string $basePriceInr, bool $isFree = false): array
    {
        if ($isFree) {
            return [
                'is_free' => true,
                'base_price_paise' => 0,
                'markup_paise' => 0,
                'customer_price_paise' => 0,
                'base_price' => 0.0,
                'platform_markup' => 0.0,
                'customer_price' => 0.0,
                'astrologer_earning' => 0.0,
            ];
        }

        $basePaise = self::inrToPaise($basePriceInr);
        if ($basePaise <= 0) {
            return [
                'is_free' => true,
                'base_price_paise' => 0,
                'markup_paise' => 0,
                'customer_price_paise' => 0,
                'base_price' => 0.0,
                'platform_markup' => 0.0,
                'customer_price' => 0.0,
                'astrologer_earning' => 0.0,
            ];
        }

        // Integer paise calculation with half-up rounding for fractions of a paise
        $markupPaise = (int) round(($basePaise * self::PLATFORM_MARKUP_PERCENT) / 100, 0, PHP_ROUND_HALF_UP);
        $customerPaise = $basePaise + $markupPaise;

        $baseInr = self::paiseToInr($basePaise);
        $markupInr = self::paiseToInr($markupPaise);
        $customerInr = self::paiseToInr($customerPaise);

        return [
            'is_free' => false,
            'base_price_paise' => $basePaise,
            'markup_paise' => $markupPaise,
            'customer_price_paise' => $customerPaise,
            'base_price' => $baseInr,
            'platform_markup' => $markupInr,
            'customer_price' => $customerInr,
            'astrologer_earning' => $baseInr,
        ];
    }
}
