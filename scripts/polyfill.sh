php -r '
$jsonPath = "/home/u736464956/shared-laravel/vendor/composer/installed.json";
if (!file_exists($jsonPath)) {
    echo "ERROR: installed.json not found\n";
    exit(1);
}
$data = json_decode(file_get_contents($jsonPath), true);
$packages = $data["packages"] ?? $data;
$found = false;
foreach ($packages as $pkg) {
    if (($pkg["name"] ?? "") === "symfony/polyfill-php83") {
        echo "[METADATA FOUND] symfony/polyfill-php83 version: " . ($pkg["version"] ?? "unknown") . " at path: " . ($pkg["install-path"] ?? "default") . "\n";
        $found = true;
    }
}
if (!$found) {
    echo "[METADATA MISSING] symfony/polyfill-php83 is NOT present in shared installed.json\n";
}
'

find /home/u736464956/shared-laravel/vendor -maxdepth 3 -name "*polyfill-php83*"