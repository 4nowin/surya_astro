<?php

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
$family = PHP_OS_FAMILY;
$arch = strtolower(php_uname('m'));

$arch = match (true) {
    in_array($arch, ['x86_64', 'amd64'], true) => 'x64',
    in_array($arch, ['aarch64', 'arm64'], true) => 'arm64',
    default => $arch,
};

$platform = match ($family) {
    'Windows' => ['dir' => 'windows-' . $arch, 'file' => 'jme.dll', 'asset' => 'jme-windows-' . $arch . '.zip'],
    'Darwin' => ['dir' => 'macos-' . $arch, 'file' => 'libjme.dylib', 'asset' => 'jme-macos-' . $arch . '.tar.gz'],
    default => ['dir' => 'linux-' . $arch, 'file' => 'libjme.so', 'asset' => 'jme-linux-' . $arch . '.tar.gz'],
};

$outDir = $root . '/libs/' . $platform['dir'];
$outFile = $outDir . '/' . $platform['file'];

if (file_exists($outFile)) {
    echo "Prebuilt library already present: {$outFile}\n";
    exit(0);
}

$localCandidates = array_filter([
    getenv('JME_LIBRARY_PATH') ?: null,
    $nativeRoot . '/build/' . $platform['file'],
]);

foreach ($localCandidates as $candidate) {
    if (is_file($candidate)) {
        @mkdir($outDir, 0775, true);
        copy($candidate, $outFile);
        echo "Prebuilt library copied from local JME build: {$outFile}\n";
        exit(0);
    }
}

$skip = getenv('JME_SKIP_DOWNLOAD');
if (is_string($skip) && $skip !== '') {
    echo "JME_SKIP_DOWNLOAD is set; skipping download.\n";
    exit(0);
}

$repo = getenv('JME_LIBS_REPO') ?: 'jayeshmepani/jpl-moshier-ephemeris-php';
$release = getenv('JME_LIBS_RELEASE') ?: 'latest';
$baseUrl = getenv('JME_LIBS_BASE_URL') ?: "https://github.com/{$repo}/releases/{$release}/download";
$url = rtrim($baseUrl, '/') . '/' . $platform['asset'];

echo "Downloading prebuilt library: {$url}\n";
@mkdir($outDir, 0775, true);

$tmpDir = sys_get_temp_dir();
$tmpFile = $tmpDir . '/' . $platform['asset'];

$data = @file_get_contents($url);
if ($data === false) {
    fwrite(STDERR, "Failed to download prebuilt library. Set JME_LIBRARY_PATH or run composer build.\n");
    exit(1);
}
file_put_contents($tmpFile, $data);

if (str_ends_with($tmpFile, '.zip')) {
    if (!class_exists('ZipArchive')) {
        fwrite(STDERR, "ZipArchive not available. Install ext-zip or extract manually.\n");
        exit(1);
    }
    $zip = new ZipArchive();
    if ($zip->open($tmpFile) !== true) {
        fwrite(STDERR, "Failed to open zip: {$tmpFile}\n");
        exit(1);
    }
    $zip->extractTo($outDir);
    $zip->close();
} else {
    try {
        $phar = new PharData($tmpFile);
        $phar->extractTo($outDir, null, true);
    } catch (Throwable $e) {
        fwrite(STDERR, "Failed to extract archive: {$e->getMessage()}\n");
        exit(1);
    }
}

if (!file_exists($outFile)) {
    fwrite(STDERR, "Downloaded archive did not contain expected file: {$outFile}\n");
    exit(1);
}

$calcephFiles = match ($family) {
    'Windows' => glob($outDir . '/calceph.dll') ?: [],
    'Darwin' => glob($outDir . '/libcalceph*.dylib') ?: [],
    default => glob($outDir . '/libcalceph.so*') ?: [],
};

if ($calcephFiles === []) {
    fwrite(STDERR, "Downloaded archive did not contain the CALCEPH runtime library.\n");
    exit(1);
}

echo "Prebuilt library installed: {$outFile}\n";
