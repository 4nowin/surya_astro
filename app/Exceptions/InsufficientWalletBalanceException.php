<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InsufficientWalletBalanceException extends Exception
{
    protected float $requiredAmount;
    protected float $walletBalance;
    protected string $currency;

    public function __construct(float $requiredAmount, float $walletBalance, string $currency = 'INR')
    {
        parent::__construct("Insufficient wallet balance. Required: {$requiredAmount}, Available: {$walletBalance}");
        $this->requiredAmount = $requiredAmount;
        $this->walletBalance = $walletBalance;
        $this->currency = $currency;
    }

    public function getRequiredAmount(): float
    {
        return $this->requiredAmount;
    }

    public function getWalletBalance(): float
    {
        return $this->walletBalance;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
}
