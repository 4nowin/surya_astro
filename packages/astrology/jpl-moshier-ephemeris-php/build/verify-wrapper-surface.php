<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$nativeRoot = getenv('JME_SOURCE_PATH') ?: null;
if ($nativeRoot === null || $nativeRoot === '') {
    foreach ([
        $root . '/../JPL-Moshier-Ephemeris',
        $root . '/../jpl-ephemeris-',
        $root . '/../jpl-ephemeris',
        $root . '/../../jpl-ephemeris-',
        $root . '/../../jpl-ephemeris',
    ] as $candidate) {
        if (is_dir($candidate)) {
            $nativeRoot = $candidate;
            break;
        }
    }
    $nativeRoot ??= $root . '/../../jpl-ephemeris-';
}
$wrapperPath = $root . '/src/FFI/JmeEphFFI.php';
$apiTrackingPath = $nativeRoot . '/docs/API_REFERENCE.md';
$headerPaths = [
    $nativeRoot . '/include/jme/jme.h',
    $nativeRoot . '/include/jme/jme_extended.h',
];

foreach (array_merge([$wrapperPath, $apiTrackingPath], $headerPaths) as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Required file not found: {$path}\n");
        exit(1);
    }
}

$wrapperText = file_get_contents($wrapperPath);
$apiTracking = file_get_contents($apiTrackingPath);
$headerText = implode("\n", array_map('file_get_contents', $headerPaths));

preg_match_all('/\|\s*\d+\s*\|\s*`(jme_[A-Za-z0-9_]+)`\s*\|/', $apiTracking, $trackedFunctionMatches);
$trackedFunctions = array_values(array_unique($trackedFunctionMatches[1]));

preg_match('/\$cdef = <<<' . "'CDEF'" . '\R(.*?)\RCDEF;/s', $wrapperText, $cdefMatch);
if (! isset($cdefMatch[1])) {
    fwrite(STDERR, "Could not locate cdef block in wrapper.\n");
    exit(1);
}

$normalizeDeclaration = static function (string $declaration): string {
    $declaration = preg_replace('!/\*.*?\*/!s', ' ', $declaration);
    $declaration = preg_replace('/\s+/', ' ', trim($declaration));
    $declaration = preg_replace('/\s*([(),;])\s*/', '$1', $declaration);
    $declaration = preg_replace('/\s*\*\s*/', ' *', $declaration);
    $declaration = preg_replace('/\bconst \*/', 'const*', $declaration);
    $declaration = str_replace('const*', 'const *', $declaration);
    return trim($declaration);
};

$extractDeclarations = static function (string $text) use ($normalizeDeclaration): array {
    preg_match_all('/((?:const\s+char\s*\*|char\s*\*|int|double|void)\s*jme_[A-Za-z0-9_]+\s*\([^;]*\);)/s', $text, $matches);
    $out = [];
    foreach ($matches[1] as $declaration) {
        if (! preg_match('/\b(jme_[A-Za-z0-9_]+)\s*\(/', $declaration, $nameMatch)) {
            continue;
        }
        $out[$nameMatch[1]] = $normalizeDeclaration($declaration);
    }
    return $out;
};

$headerDeclarations = $extractDeclarations($headerText);
$cdefDeclarations = $extractDeclarations($cdefMatch[1]);

$prototypeErrors = [];
foreach ($trackedFunctions as $functionName) {
    if (! isset($headerDeclarations[$functionName])) {
        $prototypeErrors[] = "Missing header declaration for {$functionName}";
        continue;
    }
    if (! isset($cdefDeclarations[$functionName])) {
        $prototypeErrors[] = "Missing cdef declaration for {$functionName}";
        continue;
    }
    if ($headerDeclarations[$functionName] !== $cdefDeclarations[$functionName]) {
        $prototypeErrors[] = "Prototype mismatch for {$functionName}\nHEADER: {$headerDeclarations[$functionName]}\nCDEF:   {$cdefDeclarations[$functionName]}";
    }
}

// A wrapper may declare additive APIs that are not in the currently checked-out
// core yet. Every API tracked by that core is verified exactly above; do not
// reject a forward-compatible wrapper merely because it exposes newer symbols.

if ($prototypeErrors !== []) {
    fwrite(STDERR, implode("\n\n", $prototypeErrors) . "\n");
    exit(1);
}

require_once $wrapperPath;
$reflection = new ReflectionClass(\JmeEph\FFI\JmeEphFFI::class);
$phpConstants = $reflection->getConstants();

preg_match_all('/\bJME_[A-Z0-9_]+\b/', $headerText, $constantMatches);
$trackedConstants = array_values(array_unique($constantMatches[0]));

// The tracked count is derived from these headers. The core API may grow, so
// keep validating every name and value instead of freezing the count here.

$probeCode = [];
$probeCode[] = '#include <stdio.h>';
$probeCode[] = '#include "jme/jme.h"';
$probeCode[] = 'int main(void) {';
foreach ($trackedConstants as $name) {
    if ($name === 'JME_VERSION') {
        $probeCode[] = '    printf("' . $name . '\\tstring\\t%s\\n", ' . $name . ');';
        continue;
    }
    if (in_array($name, ['JME_AU_KM', 'JME_SPEED_OF_LIGHT_KM_PER_SEC', 'JME_SECONDS_PER_DAY'], true)) {
        $probeCode[] = '    printf("' . $name . '\\tdouble\\t%.17g\\t%a\\n", (double)(' . $name . '), (double)(' . $name . '));';
        continue;
    }
    if (in_array($name, ['JME_JME_H', 'JME_EXTENDED_H'], true)) {
        $probeCode[] = '    printf("' . $name . '\\tint\\t1\\t0x1\\n");';
        continue;
    }
    $probeCode[] = '    printf("' . $name . '\\tint\\t%lld\\t0x%llx\\n", (long long)(' . $name . '), (unsigned long long)(' . $name . '));';
}
$probeCode[] = '    return 0;';
$probeCode[] = '}';

$tmpDir = sys_get_temp_dir() . '/jme_php_verify_' . getmypid();
@mkdir($tmpDir, 0775, true);
$probeC = $tmpDir . '/constants_probe.c';
$probeBin = $tmpDir . '/constants_probe' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
file_put_contents($probeC, implode("\n", $probeCode) . "\n");

$compileCommand = null;
if (PHP_OS_FAMILY === 'Windows') {
    $cc = getenv('CC') ?: null;
    if (is_string($cc) && $cc !== '') {
        $compileCommand = sprintf(
            '"%s" /nologo /I"%s" "%s" /Fe:"%s" 2>&1',
            $cc,
            $nativeRoot . '/include',
            $probeC,
            $probeBin
        );
    } elseif ((getenv('INCLUDE') ?: '') !== '') {
        $compileCommand = sprintf(
            'cl /nologo /I"%s" "%s" /Fe:"%s" 2>&1',
            $nativeRoot . '/include',
            $probeC,
            $probeBin
        );
    }
} else {
    $compileCommand = sprintf(
        'cc -I%s %s -o %s 2>&1',
        escapeshellarg($nativeRoot . '/include'),
        escapeshellarg($probeC),
        escapeshellarg($probeBin)
    );
}

$compileOutput = [];
$compileExit = 1;
if ($compileCommand !== null) {
    exec($compileCommand, $compileOutput, $compileExit);
}

$cValues = [];
if ($compileExit === 0) {
    $probeOutput = [];
    $probeExit = 0;
    exec(escapeshellarg($probeBin), $probeOutput, $probeExit);
    if ($probeExit !== 0) {
        fwrite(STDERR, "Constant probe execution failed.\n");
        exit(1);
    }

    foreach ($probeOutput as $line) {
        [$name, $type, $value, $aux] = array_pad(explode("\t", $line), 4, '');
        $cValues[$name] = ['type' => $type, 'value' => $value, 'aux' => $aux];
    }
} else {
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

    foreach ($headerPaths as $headerPath) {
        $header = file_get_contents($headerPath);
        if (preg_match_all('/^\s*#define\s+(JME_[A-Z0-9_]+)(?:[ \t]+([^\r\n]+))?\s*$/m', $header, $defineMatches, PREG_SET_ORDER)) {
            foreach ($defineMatches as $define) {
                $constantValues[$define[1]] = $evaluate(isset($define[2]) ? trim($define[2]) : '');
            }
        }

        if (preg_match_all('/typedef\s+enum\s+[^{]*\{(.*?)\}\s*[A-Za-z0-9_]+\s*;/s', $header, $enumMatches, PREG_SET_ORDER)) {
            foreach ($enumMatches as $enumMatch) {
                $nextValue = null;
                foreach (explode(',', (string) preg_replace('!/\*.*?\*/!s', '', $enumMatch[1])) as $entry) {
                    $entry = trim(preg_replace('!/\*.*?\*/!s', '', $entry));
                    if ($entry === '' || ! preg_match('/^(JME_[A-Z0-9_]+)\s*(?:=\s*(.+))?$/s', $entry, $enumEntryMatch)) {
                        continue;
                    }

                    if (isset($enumEntryMatch[2]) && trim($enumEntryMatch[2]) !== '') {
                        $nextValue = $evaluate(trim($enumEntryMatch[2]));
                    } elseif ($nextValue === null) {
                        $nextValue = 0;
                    } else {
                        $nextValue++;
                    }

                    $constantValues[$enumEntryMatch[1]] = $nextValue;
                }
            }
        }
    }

    foreach ($trackedConstants as $name) {
        if (! array_key_exists($name, $constantValues)) {
            continue;
        }

        $value = $constantValues[$name];
        if (is_string($value)) {
            $cValues[$name] = ['type' => 'string', 'value' => $value, 'aux' => ''];
        } elseif (is_float($value)) {
            $cValues[$name] = ['type' => 'double', 'value' => sprintf('%.17g', $value), 'aux' => ''];
        } else {
            $cValues[$name] = ['type' => 'int', 'value' => (string) (int) $value, 'aux' => ''];
        }
    }
}

$constantErrors = [];
foreach ($trackedConstants as $name) {
    if (! array_key_exists($name, $phpConstants)) {
        $constantErrors[] = "Missing PHP constant {$name}";
        continue;
    }
    if (! isset($cValues[$name])) {
        $constantErrors[] = "Missing C probe value for {$name}";
        continue;
    }
    $phpValue = $phpConstants[$name];
    $cValue = $cValues[$name];

    if ($cValue['type'] === 'string') {
        if ((string) $phpValue !== $cValue['value']) {
            $constantErrors[] = "String constant mismatch for {$name}: PHP=" . var_export($phpValue, true) . ' C=' . var_export($cValue['value'], true);
        }
        continue;
    }

    if ($cValue['type'] === 'double') {
        $phpString = sprintf('%.17g', (float) $phpValue);
        if ($phpString !== $cValue['value']) {
            $constantErrors[] = "Double constant mismatch for {$name}: PHP={$phpString} C={$cValue['value']}";
        }
        continue;
    }

    $phpInt = (string) (int) $phpValue;
    if ($phpInt !== $cValue['value']) {
        $constantErrors[] = "Integer constant mismatch for {$name}: PHP={$phpInt} C={$cValue['value']}";
    }
}

if (count($phpConstants) < count($trackedConstants)) {
    $constantErrors[] = 'PHP constant inventory is smaller than the checked-out core: expected at least '
        . count($trackedConstants) . ', got ' . count($phpConstants);
}

if ($constantErrors !== []) {
    fwrite(STDERR, implode("\n", $constantErrors) . "\n");
    exit(1);
}

echo 'verified_functions=' . count($trackedFunctions) . PHP_EOL;
echo 'verified_constants=' . count($trackedConstants) . PHP_EOL;
echo 'verified_cdef_declarations=' . count($cdefDeclarations) . PHP_EOL;
