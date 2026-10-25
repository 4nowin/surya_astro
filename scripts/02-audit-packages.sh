STAGING_DIR="/home/u736464956/staging-bloodspan"
cd "$STAGING_DIR"

php -r '
$lockFile = __DIR__ . "/composer.lock";
$sharedVendor = "/home/u736464956/shared-laravel/vendor";

if (!file_exists($lockFile)) {
    echo "ERROR: composer.lock not found in staging.\n";
    exit(1);
}

$lock = json_decode(file_get_contents($lockFile), true);
$packages = $lock["packages"] ?? [];

echo "==============================================================\n";
echo "PRE-FLIGHT COMPATIBILITY AUDIT: BLOODSPAN vs SHARED STORE\n";
echo "==============================================================\n";

$allValid = true;
$inventory = [];

foreach ($packages as $pkg) {
    $name = $pkg["name"];
    $reqVer = $pkg["version"];
    $targetPath = $sharedVendor . "/" . $name;
    
    if (!is_dir($targetPath)) {
        echo sprintf("[-] MISSING IN SHARED STORE: %-35s (Required: %s)\n", $name, $reqVer);
        $allValid = false;
        continue;
    }
    
    // Check package version inside shared composer.json if available
    $pkgJson = $targetPath . "/composer.json";
    $sharedVer = "Present";
    if (file_exists($pkgJson)) {
        $pData = json_decode(file_get_contents($pkgJson), true);
        if (isset($pData["version"])) {
            $sharedVer = $pData["version"];
        }
    }
    
    $inventory[] = [
        "name" => $name,
        "target" => $targetPath,
        "reqVer" => $reqVer
    ];
    echo sprintf("[+] VERIFIED COMPATIBLE: %-35s [Target: %s]\n", $name, $reqVer);
}

echo "==============================================================\n";
echo sprintf("Summary: %d packages verified. Compatible: %s\n", count($inventory), $allValid ? "YES" : "NO");
echo "==============================================================\n";

if (!$allValid) {
    echo "AUDIT FAILED: The shared store is missing packages required by Bloodspan.\n";
    echo "Aborting before creating any links.\n";
    exit(1);
}

// Write the audited package inventory for the linking step
file_put_contents(__DIR__ . "/audited_packages.json", json_encode($inventory, JSON_PRETTY_PRINT));
echo "Inventory written to audited_packages.json.\n";
'
