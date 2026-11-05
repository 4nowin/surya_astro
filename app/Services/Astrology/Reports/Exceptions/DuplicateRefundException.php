<?php

declare(strict_types=1);

namespace App\Services\Astrology\Reports\Exceptions;

use RuntimeException;

class DuplicateRefundException extends RuntimeException
{
    public function __construct(
        public readonly int $originalPurchaseId,
        string $message = 'A compensating refund has already been issued for this purchase.'
    ) {
        parent::__construct($message);
    }
}
