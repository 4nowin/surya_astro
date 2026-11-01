<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withPhpSets(
        php83: true,
    )
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/node_modules',
        __DIR__ . '/storage',
        __DIR__ . '/build',
        __DIR__ . '/bootstrap/cache',
    ])
    ->withDeadCodeLevel(68)
    ->withCodeQualityLevel(76)
    ->withTypeCoverageLevel(5)
    ->withRules([
        ClassPropertyAssignToConstructorPromotionRector::class,
        AddOverrideAttributeToOverriddenMethodsRector::class,
    ])
    ->withParallel()
    ->withImportNames(
        importNames: true,
        removeUnusedImports: true,
    );
