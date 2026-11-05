<?php

declare(strict_types=1);

namespace App\Services\Astrology\Reports\Exceptions;

use RuntimeException;

class InvalidPurchaseException extends RuntimeException
{
    public function __construct(string $message = 'Invalid report purchase transaction.')
    {
        parent::__construct($message);
    }
}
