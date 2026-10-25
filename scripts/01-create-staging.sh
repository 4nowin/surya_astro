# 1. Define paths
LIVE_DIR="/home/u736464956/domains/bloodspan.cubebitz.com"
STAGING_DIR="/home/u736464956/staging-bloodspan"
SHARED_VENDOR="/home/u736464956/shared-laravel/vendor"

# 2. Safety check: Ensure live app and shared store exist
if [ ! -d "$LIVE_DIR" ] || [ ! -d "$SHARED_VENDOR" ]; then
    echo "ERROR: Required directories missing."
    exit 1
fi

# 3. Clean up any previous staging attempts
rm -rf "$STAGING_DIR"

# 4. Clone the live application including its working standalone vendor
echo "==> Cloning live application to staging (preserving standalone vendor)..."
cp -a "$LIVE_DIR" "$STAGING_DIR"

# 5. Isolate staging storage and caches from production
cd "$STAGING_DIR"
