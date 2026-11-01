<?php

declare(strict_types=1);

namespace Tests\Unit\Astrology;

use App\Services\Astrology\NativeEphemerisResolver;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use JmeEph\FFI\JmeEphFFI;
use RuntimeException;
use Tests\TestCase;

class NativeEphemerisResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(JmeEphFFI::class)) {
            $loader = new \Composer\Autoload\ClassLoader();
            $loader->addPsr4('JmeEph\\', base_path('packages/astrology/jpl-moshier-ephemeris-php/src/'));
            $loader->addPsr4('JayeshMepani\\PanchangCore\\', base_path('packages/astrology/panchang-core/src/'));
            $loader->register();
        }
    }

    protected function tearDown(): void
    {
        NativeEphemerisResolver::setCustomDefaultPath(null);
        config(['panchang.library_path' => null, 'jmeeph.library_path' => null]);
        parent::tearDown();
    }

    public function test_explicit_override_path_is_respected(): void
    {
        $validPath = NativeEphemerisResolver::getLibsBasePath() . '/jme-windows-x64-2022/jme.dll';
        if (file_exists($validPath)) {
            $resolved = NativeEphemerisResolver::resolve($validPath);
            $this->assertSame(realpath($validPath), $resolved);
        }

        // Non-existent path must throw RuntimeException containing attempted path
        $nonExistent = '/tmp/non_existent_libjme.so';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Explicitly specified native ephemeris library not found at: {$nonExistent}");
        NativeEphemerisResolver::resolve($nonExistent);
    }

    public function test_config_override_is_respected(): void
    {
        $validPath = NativeEphemerisResolver::getLibsBasePath() . '/jme-linux-x64-glibc228/libjme.so';
        if (file_exists($validPath)) {
            config(['panchang.library_path' => $validPath]);
            $resolved = NativeEphemerisResolver::resolve();
            $this->assertSame(realpath($validPath), $resolved);
        }
    }

    public function test_glibc228_binary_exists_at_canonical_location(): void
    {
        $libs = NativeEphemerisResolver::getLibsBasePath();
        $glibc228 = $libs . '/jme-linux-x64-glibc228/libjme.so';

        $this->assertFileExists($glibc228, 'Standalone Moshier glibc 2.28 binary must exist in libs/jme-linux-x64-glibc228/libjme.so');
        $this->assertGreaterThan(1000000, filesize($glibc228));
    }

    public function test_ubuntu22_is_never_returned_by_default_on_linux(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('Linux platform test skipped on ' . PHP_OS_FAMILY);
        }

        $resolved = NativeEphemerisResolver::resolve();
        $this->assertStringNotContainsString('ubuntu22', $resolved, 'Ubuntu 22 binary must NEVER be selected on Linux Hostinger!');
        $this->assertStringContainsString('jme-linux-x64-glibc228', $resolved);
        $this->assertFileExists($resolved);
    }

    public function test_windows_resolution_returns_valid_dll_on_windows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows platform test skipped on ' . PHP_OS_FAMILY);
        }

        $resolved = NativeEphemerisResolver::resolve();
        $this->assertStringContainsString('jme-windows-x64-2022', $resolved);
        $this->assertStringEndsWith('jme.dll', $resolved);
        $this->assertFileExists($resolved);
    }

    public function test_panchang_service_provider_preserves_bound_instance(): void
    {
        $dummy = $this->createMock(JmeEphFFI::class);
        $this->app->instance(JmeEphFFI::class, $dummy);

        $provider = new PanchangServiceProvider($this->app);
        $provider->register();

        $resolvedFromContainer = $this->app->make(JmeEphFFI::class);
        $this->assertSame($dummy, $resolvedFromContainer, 'PanchangServiceProvider must not overwrite an already bound JmeEphFFI instance.');
    }
}
