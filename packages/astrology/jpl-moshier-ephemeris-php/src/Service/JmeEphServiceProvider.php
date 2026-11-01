<?php

declare(strict_types=1);

namespace JmeEph\Service;

use Illuminate\Support\ServiceProvider;
use JmeEph\FFI\JmeEphFFI;
use Override;

/**
 * Laravel Service Provider for JPL Moshier Ephemeris FFI.
 *
 * Registers JmeEphFFI as a singleton in the Laravel service container,
 * ensuring the JPL Moshier Ephemeris library is loaded once and shared across
 * the entire application lifecycle.
 *
 * Features:
 * - Singleton registration (one instance per request)
 * - Automatic configuration merging
 * - Publishable config and native library files
 * - Facade alias support
 *
 * @author Jayesh Patel <jayeshmepani777@gmail.com>
 *
 * @see \JmeEph\FFI\JmeEphFFI
 */
final class JmeEphServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     *
     * Merges package configuration and registers JmeEphFFI as singleton.
     * The singleton ensures only one FFI instance exists per request,
     * preventing multiple library loads and memory waste.
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/jmeeph.php',
            'jmeeph'
        );

        $this->app->singleton('jmeeph', function ($app) {
            $libraryPath = $app->make('config')->get('jmeeph.library_path');
            $ffi = new JmeEphFFI($libraryPath);
            $ephemerisPath = $app->make('config')->get('jmeeph.ephemeris_path');
            $engine = strtoupper((string) $app->make('config')->get('jmeeph.engine', $app->make('config')->get('jmeeph.calculation_path', 'AUTO')));

            $ffi->configureEngine($engine, is_string($ephemerisPath) ? $ephemerisPath : null);

            return $ffi;
        });

        $this->app->singleton(JmeService::class, fn($app) => new JmeService(
            $app->make(JmeEphFFI::class),
            $app->make('config')->get('jmeeph.engine', $app->make('config')->get('jmeeph.calculation_path', 'AUTO')),
            $app->make('config')->get('jmeeph.ephemeris_path')
        ));

        $this->app->alias('jmeeph', JmeEphFFI::class);
    }

    /**
     * Bootstrap application services.
     *
     * Registers publishable resources:
     * - config/jmeeph.php → config_path('jmeeph.php')
     * - current platform native library → storage_path('app/jmeeph/<library>')
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/jmeeph.php' => config_path('jmeeph.php'),
        ], 'jmeeph-config');

        $library = $this->findPublishableLibrary();
        if ($library !== null) {
            $this->publishes([
                $library => storage_path('app/jmeeph/' . basename($library)),
            ], 'jmeeph-library');
        }
    }

    /**
     * Get provided services.
     *
     * @return array<int, string>
     */
    #[Override]
    public function provides(): array
    {
        return ['jmeeph', JmeEphFFI::class, JmeService::class];
    }

    private function findPublishableLibrary(): ?string
    {
        $family = PHP_OS_FAMILY;
        $arch = strtolower(php_uname('m'));
        $arch = match (true) {
            in_array($arch, ['x86_64', 'amd64'], true) => 'x64',
            in_array($arch, ['aarch64', 'arm64'], true) => 'arm64',
            default => $arch,
        };

        $file = match ($family) {
            'Windows' => 'jme.dll',
            'Darwin' => 'libjme.dylib',
            default => 'libjme.so',
        };

        $dir = '';
        if ($family === 'Windows') {
            $dir = 'jme-windows-x64-2022';
        } elseif ($family === 'Darwin') {
            if ($arch === 'arm64') {
                $darwinRelease = php_uname('r');
                $majorDarwin = (int) explode('.', $darwinRelease)[0];
                $version = ($majorDarwin >= 24) ? '15' : '14';
                $dir = 'jme-macos-arm64-' . $version;
                $libsDir = dirname(__DIR__, 2) . '/libs';
                if (!file_exists($libsDir . '/' . $dir)) {
                    $dir = ($version === '15') ? 'jme-macos-arm64-14' : 'jme-macos-arm64-15';
                }
            } else {
                $dir = 'jme-macos-x64-15';
            }
        } else {
            // Linux / other Unix
            $glibcVersion = null;
            if (function_exists('shell_exec')) {
                $output = @shell_exec('getconf GNU_LIBC_VERSION 2>&1');
                if ($output && preg_match('/glibc\s+(\d+\.\d+)/i', $output, $matches)) {
                    $glibcVersion = (float) $matches[1];
                }
            }
            if ($glibcVersion === null && file_exists('/etc/os-release')) {
                $lines = @file('/etc/os-release', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines !== false) {
                    $osRelease = [];
                    foreach ($lines as $line) {
                        if (str_contains($line, '=')) {
                            [$key, $val] = explode('=', $line, 2);
                            $osRelease[trim($key)] = trim($val, '"\'');
                        }
                    }
                    $id = $osRelease['ID'] ?? '';
                    $versionId = $osRelease['VERSION_ID'] ?? '';
                    if ($id === 'ubuntu' || ($osRelease['ID_LIKE'] ?? '') === 'ubuntu') {
                        if (floatval($versionId) >= 24.0) {
                            $glibcVersion = 2.39;
                        }
                    } elseif ($id === 'debian') {
                        if (floatval($versionId) >= 13.0) {
                            $glibcVersion = 2.39;
                        }
                    }
                }
            }

            $suffix = ($glibcVersion !== null && $glibcVersion >= 2.39) ? 'ubuntu24' : 'ubuntu22';
            $dir = 'jme-linux-' . $arch . '-' . $suffix;

            // Check fallback if dir does not exist
            $libsDir = dirname(__DIR__, 2) . '/libs';
            if (!file_exists($libsDir . '/' . $dir)) {
                $dir = 'jme-linux-' . $arch . '-' . ($suffix === 'ubuntu24' ? 'ubuntu22' : 'ubuntu24');
            }
        }

        $candidateDirs = [
            dirname(__DIR__, 2) . '/libs/' . $dir,
            dirname(__DIR__, 2) . '/build',
        ];

        foreach ($candidateDirs as $candidateDir) {
            $path = $candidateDir . '/' . $file;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
