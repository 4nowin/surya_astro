<?php

declare(strict_types=1);

namespace {
    if (! function_exists('env')) {
        function env(string $key, mixed $default = null): mixed
        {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

            return $value === false ? $default : $value;
        }
    }

    if (! function_exists('storage_path')) {
        function storage_path(string $path = ''): string
        {
            $base = sys_get_temp_dir() . '/jmeeph-test-storage';

            return $path === ''
                ? $base
                : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        }
    }
}

namespace JmeEph\Tests {
    use Illuminate\Container\Container;
    use Illuminate\Foundation\Application;
    use JmeEph\FFI\JmeEphFFI;
    use PHPUnit\Framework\TestCase;
    use RuntimeException;

    final class JmeConfigIntegrationTest extends TestCase
    {
        public function testCheckedInConfigSelectsAUsableEngine(): void
        {
            $app = new Application(dirname(__DIR__));
            $app->useStoragePath(sys_get_temp_dir() . '/jmeeph-test-storage');
            Container::setInstance($app);

            /** @var array{library_path:mixed, engine:mixed, ephemeris_path:mixed} $config */
            $config = require dirname(__DIR__) . '/config/jmeeph.php';

            $libraryPath = is_string($config['library_path']) && $config['library_path'] !== ''
                ? $config['library_path']
                : null;
            $engine = strtoupper((string) ($config['engine'] ?? 'AUTO'));
            $ephemerisPath = is_string($config['ephemeris_path']) && $config['ephemeris_path'] !== ''
                ? $config['ephemeris_path']
                : null;

            $ffi = new JmeEphFFI($libraryPath);

            try {
                $ffi->configureEngine($engine, $ephemerisPath);
                $this->addToAssertionCount(1);
            } catch (RuntimeException $e) {
                self::fail(
                    sprintf(
                        'Configured engine "%s" is not usable with ephemeris_path "%s": %s',
                        $engine,
                        $ephemerisPath ?? '',
                        $e->getMessage()
                    )
                );
            }
        }
    }
}
