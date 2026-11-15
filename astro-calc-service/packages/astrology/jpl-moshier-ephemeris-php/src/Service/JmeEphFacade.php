<?php

declare(strict_types=1);

namespace JmeEph\Service;

use Illuminate\Support\Facades\Facade;
use JmeEph\FFI\JmeEphFFI;

/**
 * Laravel Facade for JPL Moshier Ephemeris FFI.
 *
 * Provides static-like access to JmeEphFFI methods through Laravel's facade pattern.
 * All calls delegate directly to the raw JmeEphFFI singleton instance. The facade
 * does not add convenience wrappers, reshape return values, or hide C output buffers.
 *
 * @author Jayesh Patel <jayeshmepani777@gmail.com>
 *
 * @mixin JmeEphFFI
 *
 * @see \JmeEph\FFI\JmeEphFFI
 */
final class JmeEphFacade extends Facade
{
    /** Get registered name in service container. */
    protected static function getFacadeAccessor(): string
    {
        return 'jmeeph';
    }
}
