<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$nativeRoot = getenv('JME_SOURCE_PATH') ?: null;
if ($nativeRoot === null || $nativeRoot === '') {
    foreach ([$root . '/../JPL-Moshier-Ephemeris', $root . '/../jpl-ephemeris'] as $candidate) {
        if (is_dir($candidate)) {
            $nativeRoot = $candidate;
            break;
        }
    }
    $nativeRoot ??= $root . '/../jpl-ephemeris';
}
$apiTrackingPath = $nativeRoot . '/docs/API_REFERENCE.md';
$headerPaths = [
    $nativeRoot . '/include/jme/jme.h',
    $nativeRoot . '/include/jme/jme_extended.h',
];

foreach (array_merge([$apiTrackingPath], $headerPaths) as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Required native source file not found: {$path}\n");
        exit(1);
    }
}

$apiTracking = file_get_contents($apiTrackingPath);
preg_match_all('/\|\s*\d+\s*\|\s*`(jme_[A-Za-z0-9_]+)`\s*\|/', $apiTracking, $functionMatches);
$trackedFunctions = array_values(array_unique($functionMatches[1]));

$headers = [];
foreach ($headerPaths as $path) {
    $headers[$path] = file_get_contents($path);
}

$functionDeclarations = [];
foreach ($headers as $header) {
    $normalized = preg_replace('!/\*.*?\*/!s', '', $header);
    $normalized = preg_replace('/^\s*#.*$/m', '', $normalized);
    $chunks = explode(';', $normalized);
    foreach ($chunks as $chunk) {
        if (! str_contains($chunk, 'jme_') || ! str_contains($chunk, '(')) {
            continue;
        }
        $chunk = trim($chunk);
        if ($chunk === '' || str_starts_with($chunk, 'typedef')) {
            continue;
        }
        if (! preg_match('/\b(jme_[A-Za-z0-9_]+)\s*\(/', $chunk, $match)) {
            continue;
        }
        $name = $match[1];
        $chunk = preg_replace('/\s+/', ' ', $chunk);
        $functionDeclarations[$name] = trim($chunk) . ';';
    }
}

$orderedDeclarations = [];
$missingFunctions = [];
foreach ($trackedFunctions as $functionName) {
    if (! isset($functionDeclarations[$functionName])) {
        $missingFunctions[] = $functionName;
        continue;
    }
    $orderedDeclarations[] = $functionDeclarations[$functionName];
}

if ($missingFunctions !== []) {
    fwrite(STDERR, "Missing declarations for tracked functions: " . implode(', ', $missingFunctions) . "\n");
    exit(1);
}

$constantOrder = [];
$constantValues = [];

$evaluate = static function (string $expression) use (&$constantValues) {
    $expression = trim($expression);
    if ($expression === '') {
        return 1;
    }
    if (preg_match('/^"(.*)"$/s', $expression, $match)) {
        return stripcslashes($match[1]);
    }

    $resolved = preg_replace_callback(
        '/\bJME_[A-Z0-9_]+\b/',
        static function (array $match) use (&$constantValues) {
            $name = $match[0];
            if (! array_key_exists($name, $constantValues)) {
                throw new RuntimeException("Unknown constant reference: {$name}");
            }
            return is_string($constantValues[$name]) ? var_export($constantValues[$name], true) : (string) $constantValues[$name];
        },
        $expression
    );

    if (! preg_match('~^[0-9A-Za-z_+\-*/%<>&|(). "\']+$~', $resolved)) {
        throw new RuntimeException("Unsafe expression: {$expression}");
    }

    return eval('return ' . $resolved . ';');
};

foreach ($headers as $header) {
    if (preg_match_all('/^\s*#define\s+(JME_[A-Z0-9_]+)(?:[ \t]+([^\r\n]+))?\s*$/m', $header, $defineMatches, PREG_SET_ORDER)) {
        foreach ($defineMatches as $define) {
            $name = $define[1];
            $value = isset($define[2]) ? trim($define[2]) : '';
            $constantValues[$name] = $evaluate($value);
            if (! in_array($name, $constantOrder, true)) {
                $constantOrder[] = $name;
            }
        }
    }

    if (preg_match_all('/typedef\s+enum\s+[^{]*\{(.*?)\}\s*[A-Za-z0-9_]+\s*;/s', $header, $enumMatches, PREG_SET_ORDER)) {
        foreach ($enumMatches as $enumMatch) {
            $entries = explode(',', (string) preg_replace('!/\*.*?\*/!s', '', $enumMatch[1]));
            $nextValue = null;
            foreach ($entries as $entry) {
                $entry = trim(preg_replace('!/\*.*?\*/!s', '', $entry));
                if ($entry === '') {
                    continue;
                }
                if (! preg_match('/^(JME_[A-Z0-9_]+)\s*(?:=\s*(.+))?$/s', $entry, $enumEntryMatch)) {
                    continue;
                }

                $name = $enumEntryMatch[1];
                if (isset($enumEntryMatch[2]) && trim($enumEntryMatch[2]) !== '') {
                    $nextValue = $evaluate(trim($enumEntryMatch[2]));
                } elseif ($nextValue === null) {
                    $nextValue = 0;
                } else {
                    $nextValue++;
                }

                $constantValues[$name] = $nextValue;
                if (! in_array($name, $constantOrder, true)) {
                    $constantOrder[] = $name;
                }
            }
        }
    }
}

$constantLines = [];
foreach ($constantOrder as $name) {
    $value = $constantValues[$name];
    $constantLines[] = '    public const ' . $name . ' = ' . var_export($value, true) . ';';
}

$cdefLines = array_map(
    static fn (string $declaration): string => '            ' . $declaration,
    $orderedDeclarations
);

$outputPath = $root . '/src/FFI/JmeEphFFI.php';
$current = file_get_contents($outputPath);
if ($current === false) {
    throw new RuntimeException("Cannot read {$outputPath}");
}

// The wrapper class is maintained by hand (engine selection, library
// resolution, profiling, typed helpers). Only two regions are derived from
// the native headers and rewritten here: the JME_* constant block and the
// FFI cdef heredoc. Everything else in the file is preserved byte for byte.
$constantBlock = '/^(?:    public const JME_[A-Z0-9_]+ = [^\n]*;\n)+/m';
if (preg_match_all($constantBlock, $current, $blocks) !== 1) {
    throw new RuntimeException('Expected exactly one contiguous JME_* constant block in ' . $outputPath);
}
$current = (string) preg_replace($constantBlock, implode("\n", $constantLines) . "\n", $current, 1);

$cdefBlock = "/(\\\$cdef = <<<'CDEF'\n).*?(\nCDEF;)/s";
if (preg_match_all($cdefBlock, $current) !== 1) {
    throw new RuntimeException('Expected exactly one CDEF heredoc in ' . $outputPath);
}
$current = (string) preg_replace_callback(
    $cdefBlock,
    static fn (array $m): string => $m[1] . implode("\n", $cdefLines) . $m[2],
    $current,
    1
);

file_put_contents($outputPath, $current);

// PHPStan stub: declares every native entry point as a method of FFI so that
// direct $ffi->jme_*() calls are statically checked against the C prototypes.
$phpType = static function (string $cType, bool $isReturn): string {
    $t = trim((string) preg_replace('/\s+/', ' ', $cType));
    $isPointer = str_contains($t, '*');
    $base = trim(str_replace(['const ', '*'], '', $t));
    if (! $isPointer) {
        return match ($base) {
            'double', 'float' => 'float',
            'void' => 'void',
            default => 'int',
        };
    }
    if ($base === 'char') {
        return $isReturn ? 'string|\FFI\CData|null' : 'string|\FFI\CData|null';
    }

    return '\FFI\CData|null';
};
$methodLines = [];
foreach ($orderedDeclarations as $declaration) {
    if (! preg_match('/^(.*?)\b(jme_[a-z0-9_]+)\s*\((.*)\)\s*;$/s', trim($declaration), $parts)) {
        throw new RuntimeException("Cannot parse declaration: {$declaration}");
    }
    $params = [];
    $rawParams = trim($parts[3]);
    if ($rawParams !== '' && $rawParams !== 'void') {
        foreach (explode(',', $rawParams) as $param) {
            if (! preg_match('/^(.*?)([A-Za-z_][A-Za-z0-9_]*)\s*(\[\s*\d*\s*\])?$/s', trim($param), $p)) {
                throw new RuntimeException("Cannot parse parameter: {$param}");
            }
            $cType = $p[1] . (isset($p[3]) && $p[3] !== '' ? '*' : '');
            $params[] = $phpType($cType, false) . ' $' . $p[2];
        }
    }
    $methodLines[] = ' * @method ' . $phpType($parts[1], true) . ' ' . $parts[2] . '(' . implode(', ', $params) . ')';
}
$stub = "<?php\n\n// Generated by build/generate-wrapper.php from the native JME headers.\n\n/**\n"
    . implode("\n", $methodLines) . "\n */\nfinal class FFI\n{\n}\n";
file_put_contents($root . '/stubs/FFI.stub', $stub);

echo "Generated wrapper: {$outputPath}\n";
echo "Functions: " . count($trackedFunctions) . "\n";
echo "Constants: " . count($constantOrder) . "\n";
