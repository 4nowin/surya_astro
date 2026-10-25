cd "$STAGING_DIR"

# 1. Regenerate optimized autoloader and package manifest locally
composer dump-autoload --optimize --no-dev
php artisan package:discover --ansi

# 2. Run Comprehensive CLI Diagnostics
php -r '
echo "==============================================================\n";
echo "STAGING RUNTIME VERIFICATION CHECKS\n";
echo "==============================================================\n";

// Check 1: Autoloader integrity
$autoloadFile = __DIR__ . "/vendor/autoload.php";
$isRealAutoload = file_exists($autoloadFile) && !is_link($autoloadFile);
echo sprintf("[Check 1] vendor/autoload.php is a REAL local file: %s\n", $isRealAutoload ? "PASS" : "FAIL");

// Check 2: Composer metadata directory integrity
$composerDir = __DIR__ . "/vendor/composer";
$isRealComposerDir = is_dir($composerDir) && !is_link($composerDir);
echo sprintf("[Check 2] vendor/composer/ is a REAL local directory: %s\n", $isRealComposerDir ? "PASS" : "FAIL");

// Check 3: App namespace resolution
require $autoloadFile;
$app = require_once __DIR__ . "/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo sprintf("[Check 3] Laravel Framework boots cleanly: %s (Version: %s)\n", $app ? "PASS" : "FAIL", $app->version());

// Check 4: Bloodspan Core Models Resolution
$models = [
    "App\\Models\\User",
    "App\\Models\\Person",
    "App\\Models\\Family",
    "App\\Models\\Fact",
    "App\\Models\\Relationship"
];
foreach ($models as $m) {
    echo sprintf("[Check 4] Class %-28s resolved: %s\n", $m, class_exists($m) ? "PASS" : "FAIL");
}

// Check 5: Pint namespace pollution check (MUST NOT EXIST)
$pintCheck = class_exists("App\\Commands\\DefaultCommand") || file_exists(__DIR__ . "/vendor/laravel/pint");
echo sprintf("[Check 5] Pint collision isolated (Pint must NOT be present): %s\n", !$pintCheck ? "PASS (Pint excluded)" : "FAIL (Pint found!)");

// Check 6: Package Discovery manifest inspection
$manifest = require __DIR__ . "/bootstrap/cache/packages.php";
$hasBreeze = isset($manifest["laravel/breeze"]);
$hasSocialite = isset($manifest["laravel/socialite"]);
echo sprintf("[Check 6] Package manifest isolated (No Breeze/Socialite): %s\n", (!$hasBreeze && !$hasSocialite) ? "PASS" : "FAIL");

echo "==============================================================\n";
'

# 3. Test Artisan commands in staging
php artisan about
php artisan config:cache
php artisan route:cache
php artisan optimize:clear