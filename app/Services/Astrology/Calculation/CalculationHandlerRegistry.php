<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation;

use InvalidArgumentException;

class CalculationHandlerRegistry
{
    /**
     * @var array<string, CalculationHandlerInterface>
     */
    protected array $handlers = [];

    /**
     * Register a calculation handler.
     */
    public function register(CalculationHandlerInterface $handler): void
    {
        $this->handlers[$handler->getCalculationType()] = $handler;
    }

    /**
     * Check if a handler is registered for a calculation type.
     */
    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    /**
     * Get the handler registered for a calculation type.
     *
     * @throws InvalidArgumentException
     */
    public function get(string $type): CalculationHandlerInterface
    {
        if (!$this->has($type)) {
            throw new InvalidArgumentException("No astrology calculation handler registered for type: '{$type}'");
        }

        return $this->handlers[$type];
    }

    /**
     * Get all registered calculation types.
     *
     * @return array<string>
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->handlers);
    }
}
