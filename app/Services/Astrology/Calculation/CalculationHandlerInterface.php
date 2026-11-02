<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation;

interface CalculationHandlerInterface
{
    /**
     * Return the unique calculation type identifier handled by this class.
     * E.g. 'daily_panchang'.
     */
    public function getCalculationType(): string;

    /**
     * Compute or format the canonical deduplication hash from the payload.
     *
     * @param array<string, mixed> $payload
     * @return string 64-character deterministic SHA-256 hash
     */
    public function computeCanonicalKey(array $payload): string;

    /**
     * Perform the actual astrology calculation.
     * Must be invoked strictly in a PHP CLI environment with working FFI/JME.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Authoritative calculation result
     */
    public function calculate(array $payload): array;

    /**
     * Store the calculation result in durable storage / cache.
     *
     * @param string $canonicalKey
     * @param array<string, mixed> $result
     * @param array<string, mixed> $payload
     * @return string The cache key or storage reference
     */
    public function storeResult(string $canonicalKey, array $result, array $payload): string;

    /**
     * Retrieve completed calculation result from cache/storage if available.
     *
     * @param string $canonicalKey
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function getCachedResult(string $canonicalKey, array $payload): ?array;
}
