cd "$STAGING_DIR"

php -r '
$inventoryFile = __DIR__ . "/audited_packages.json";
if (!file_exists($inventoryFile)) {
    echo "ERROR: audited_packages.json not found.\n";
    exit(1);
}

$inventory = json_decode(file_get_contents($inventoryFile), true);
$vendorDir = __DIR__ . "/vendor";

echo "==> Applying Granular Package-Level Symlinks...\n";

foreach ($inventory as $item) {
    $name = $item["name"];
    $target = $item["target"];
    $dest = $vendorDir . "/" . $name;
    
    // Create organization parent directory (e.g. vendor/laravel)
    $parent = dirname($dest);
    if (!is_dir($parent)) {
        mkdir($parent, 0755, true);
    }
    
    // Remove existing physical package folder in staging vendor
    if (is_dir($dest) && !is_link($dest)) {
        exec(sprintf("rm -rf %s", escapeshellarg($dest)));
    } elseif (is_link($dest)) {
        unlink($dest);
    }
    
    // Create symlink directly to package
    symlink($target, $dest);
    
    // Verify symlink resolution
    if (is_link($dest) && file_exists($dest)) {
        echo sprintf("  [OK] %s -> %s\n", $name, $target);
    } else {
        echo sprintf("  [ERROR] Broken link for %s\n", $name);
        exit(1);
    }
}

// Ensure vendor/bin is also symlinked if present in shared store
if (is_dir("/home/u736464956/shared-laravel/vendor/bin")) {
    if (is_dir($vendorDir . "/bin") && !is_link($vendorDir . "/bin")) {
        exec("rm -rf " . escapeshellarg($vendorDir . "/bin"));
    }
    if (!is_link($vendorDir . "/bin")) {
        symlink("/home/u736464956/shared-laravel/vendor/bin", $vendorDir . "/bin");
        echo "  [OK] bin -> shared-laravel/vendor/bin\n";
    }
}

echo "Package linking complete.\n";
'