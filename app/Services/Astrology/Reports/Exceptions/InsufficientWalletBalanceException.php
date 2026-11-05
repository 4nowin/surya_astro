<?php

declare(strict_types=1);

namespace App\Services\Astrology\Reports\Exceptions;

use RuntimeException;

class InsufficientWalletBalanceException extends RuntimeException
{
    public function __construct(
        public readonly float $currentBalance,
        public readonly float $requiredAmount,
        string $message = 'Insufficient wallet balance to purchase Kundli report.'
    ) {
        parent::__construct($message);
    }
}
