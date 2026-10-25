cd /home/u736464956/domains/cubebitz.com/public_html/kiraya_mitra_web || exit 1

SHARED="/home/u736464956/shared-laravel/vendor"
BACKUP="vendor_backup_$(date +%Y%m%d_%H%M%S)"

# 1. Confirm required shared directories exist
[ -d "$SHARED/laravel" ] &&
[ -d "$SHARED/symfony" ] &&
[ -d "$SHARED/firebase" ] || {
  echo "Shared package directories missing; stopping."
  exit 1
}

# 2. Preserve current vendor backup
if [ -d vendor ]; then
  cp -a vendor "$BACKUP" || exit 1
  echo "Backup saved at: $BACKUP"
fi

# 3. Clear stale Laravel bootstrap caches
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php

# 4. Link package organization directories
mkdir -p vendor/composer

for item in "$SHARED"/*; do
  name=$(basename "$item")

  # Keep project-specific Composer metadata local
  [ "$name" = "composer" ] && continue
  [ "$name" = "autoload.php" ] && continue
  [ -d "$item" ] || continue

  dest="vendor/$name"

  if [ -L "$dest" ]; then
    rm "$dest" || exit 1
  elif [ -d "$dest" ]; then
    rm -rf "$dest" || exit 1
  elif [ -e "$dest" ]; then
    rm -f "$dest" || exit 1
  fi

  ln -s "$item" "$dest" || {
    echo "Link failed: $name. Restore backup: $BACKUP"
    exit 1
  }
done

# 5. Dump autoloader with --no-scripts first to prevent hook race conditions
/opt/alt/php84/usr/bin/php /usr/local/bin/composer \
  dump-autoload --optimize --no-scripts || {
    echo "Autoload generation failed. Restore backup: $BACKUP"
    exit 1
  }

# 6. Run package discovery cleanly
/opt/alt/php84/usr/bin/php artisan package:discover --ansi || {
  echo "Package discovery failed. Restore backup: $BACKUP"
  exit 1
}

# 7. Verify application boot
/opt/alt/php84/usr/bin/php artisan --version || {
  echo "Laravel boot failed. Restore backup: $BACKUP"
  exit 1
}

echo "Vendor linking completed successfully!"