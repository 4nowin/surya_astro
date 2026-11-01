<?php

declare(strict_types=1);

namespace App\Services\Astrology;

use RuntimeException;

/**
 * Authoritative native library resolver for Astro Setu's ephemeris engine.
 *
 * Ensures direct calculations, Panchang calculations, and background queue workers
 * all load the exact verified native shared library for the host platform.
 *
 * On Linux (Hostinger), strictly resolves to the glibc 2.28-compatible binary
 * and refuses silent fallback to incompatible Ubuntu 22/24 binaries.
 */
final class NativeEphemerisResolver
{
    private static ?string $customDefaultPath = null;

    /**
     * Set an explicit runtime override path.
     */
    public static function setCustomDefaultPath(?string $path): void
    {
        self::$customDefaultPath = $path;
    }

    /**
     * Resolve the absolute path to the appropriate native ephemeris library.
     *
     * @param string|null $overridePath Explicit path override from CLI argument or caller
     * @param bool $useCalceph Whether to select the CALCEPH-enabled build (Option A) on Linux
     * @return string Absolute file path to the verified native shared library
     * @throws RuntimeException If the required compatible library does not exist
     */
    public static function resolve(?string $overridePath = null, bool $useCalceph = false): string
    {
        // 1. Caller-supplied override
        if ($overridePath !== null && trim($overridePath) !== '') {
            $path = trim($overridePath);
            if (!file_exists($path)) {
                throw new RuntimeException("Explicitly specified native ephemeris library not found at: {$path}");
            }
            return realpath($path) ?: $path;
        }

        // 2. Runtime override set via class
        if (self::$customDefaultPath !== null && self::$customDefaultPath !== '') {
            if (!file_exists(self::$customDefaultPath)) {
                throw new RuntimeException("Custom native ephemeris library path not found at: " . self::$customDefaultPath);
            }
            return realpath(self::$customDefaultPath) ?: self::$customDefaultPath;
        }

        // 3. Environment variable override
        $envPath = getenv('JME_LIBRARY_PATH') ?: ($_ENV['JME_LIBRARY_PATH'] ?? null);
        if (is_string($envPath) && trim($envPath) !== '') {
            $path = trim($envPath);
            if (!file_exists($path)) {
                throw new RuntimeException("Environment variable JME_LIBRARY_PATH points to non-existent file: {$path}");
            }
            return realpath($path) ?: $path;
        }

        // 4. Laravel configuration override
        if (function_exists('config')) {
            $configPath = config('panchang.library_path') ?? config('jmeeph.library_path');
            if (is_string($configPath) && trim($configPath) !== '') {
                $path = trim($configPath);
                if (!file_exists($path)) {
                    throw new RuntimeException("Configuration 'panchang.library_path' points to non-existent file: {$path}");
                }
                return realpath($path) ?: $path;
            }
        }

        // 5. Automatic platform-specific resolution
        $libsBase = self::getLibsBasePath();
        $family = PHP_OS_FAMILY;

        if ($family === 'Windows') {
            $winPath = $libsBase . '/jme-windows-x64-2022/jme.dll';
            if (!file_exists($winPath)) {
                throw new RuntimeException("Windows native ephemeris library not found at: {$winPath}");
            }
            return realpath($winPath) ?: $winPath;
        }

        if ($family === 'Darwin') {
            $arch = strtolower(php_uname('m'));
            if (in_array($arch, ['arm64', 'aarch64'], true)) {
                $macPath = $libsBase . '/jme-macos-arm64-15/libjme.dylib';
                if (!file_exists($macPath)) {
                    $macPath = $libsBase . '/jme-macos-arm64-14/libjme.dylib';
                }
            } else {
                $macPath = $libsBase . '/jme-macos-x64-15/libjme.dylib';
            }

            if (!file_exists($macPath)) {
                throw new RuntimeException("macOS native ephemeris library not found at: {$macPath}");
            }
            return realpath($macPath) ?: $macPath;
        }

        // Linux platform resolution
        $arch = strtolower(php_uname('m'));
        $isX64 = in_array($arch, ['x86_64', 'amd64'], true);

        if (!$isX64) {
            throw new RuntimeException("Unsupported Linux architecture: {$arch}. Only x86_64 is currently packaged.");
        }

        if ($useCalceph) {
            $calcephPath = $libsBase . '/jme-linux-x64-glibc228-calceph/libjme.so';
            if (!file_exists($calcephPath)) {
                throw new RuntimeException(
                    "CALCEPH build requested (--calceph) but binary not found at: {$calcephPath}. " .
                    "Compile it targeting glibc 2.28 per docs/ASTROLOGY_NATIVE_BUILD_GUIDE.md."
                );
            }
            return realpath($calcephPath) ?: $calcephPath;
        }

        // Default: Hostinger glibc 2.28 standalone Moshier (Option B)
        $glibc228Path = $libsBase . '/jme-linux-x64-glibc228/libjme.so';
        if (!file_exists($glibc228Path)) {
            throw new RuntimeException(
                "Hostinger glibc 2.28 compatible standalone Moshier library not found at: {$glibc228Path}. " .
                "Refusing silent fallback to Ubuntu 22/24 binaries because they require glibc 2.35+ and will crash on Hostinger glibc 2.28. " .
                "Build the library using the instructions in docs/ASTROLOGY_NATIVE_BUILD_GUIDE.md."
            );
        }

        return realpath($glibc228Path) ?: $glibc228Path;
    }

    /**
     * Get the base directory containing native libraries.
     */
    public static function getLibsBasePath(): string
    {
        if (function_exists('base_path')) {
            $base = base_path('packages/astrology/jpl-moshier-ephemeris-php/libs');
            if (is_dir($base)) {
                return realpath($base) ?: $base;
            }
        }

        $base = dirname(__DIR__, 3) . '/packages/astrology/jpl-moshier-ephemeris-php/libs';
        if (is_dir($base)) {
            return realpath($base) ?: $base;
        }

        return $base;
    }
}
