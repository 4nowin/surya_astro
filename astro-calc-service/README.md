# Astro Setu — Dedicated Astrology Calculation Microservice

A dedicated, minimal, standalone calculation service for Vedic astrology and ephemeris computations.

- **Base Image:** Debian 12 Bookworm Slim (`debian:bookworm-slim`)
- **PHP Version:** PHP 8.4.27 CLI (NTS) with FFI, OPcache, PCNTL
- **Native Ephemeris:** Standalone Moshier ephemeris engine (`libjme.so`, glibc 2.28+ compatible)
- **Ayanamsha Mode:** True Citra Lahiri (Bit-exact: 23.841358° at J2000 epoch)
- **Container Limit:** 256 MB RAM ceiling, 1.0 vCPU constraint
- **Worker Concurrency:** Single worker daemon (`server.php`)
- **Security:** HMAC-SHA256 signature verification, Nonce replay protection, Timestamp expiration (±300s), Non-root user (`astro`, UID 1000)
- **Image Size:** **279 MB** unpacked disk usage / **62.5 MB** compressed content size

---

## 1. Directory Structure

```
astro-calc-service/
├── app/
│   ├── Console/Kernel.php
│   ├── Contracts/Astrology/KundliCalculatorInterface.php
│   ├── Exceptions/Handler.php
│   ├── Http/Kernel.php
│   ├── Models/
│   │   ├── AstrologyCalculationJob.php
│   │   ├── DailyPanchangCache.php
│   │   └── UserAstrologyProfile.php
│   ├── Providers/AppServiceProvider.php
│   └── Services/Astrology/
│       ├── Aspects/ParashariAspectService.php
│       ├── Calculation/
│       │   ├── CalculationHandlerInterface.php
│       │   └── Handlers/
│       │       ├── DailyPanchangCalculationHandler.php
│       │       └── KundliCalculationHandler.php
│       ├── Interpretation/
│       ├── Stability/
│       ├── Transits/SaturnTransitService.php
│       ├── Yogas/
│       ├── CanonicalPanchangCacheKey.php
│       ├── DailyPanchangService.php
│       ├── DashaService.php
│       ├── KundliService.php
│       └── NativeEphemerisResolver.php
├── bootstrap/app.php
├── config/ (app.php, astrology.php, cache.php, database.php, logging.php)
├── packages/astrology/
│   ├── jpl-moshier-ephemeris-php/ (src/ + libs/jme-linux-x64-glibc228/libjme.so)
│   └── panchang-core/ (src/ + config/, excluding scripts/output/)
├── routes/ (api.php, console.php)
├── .dockerignore (Context size: 9.48 MB)
├── composer.json (Zero non-calculation dependencies)
├── composer.lock (Locked reproducible dependencies)
├── Dockerfile (Multi-stage Debian 12 Bookworm Slim + PHP 8.4)
└── server.php (Single-worker HTTP daemon with HMAC replay protection)
```

---

## 2. Local Workstation Docker Build & GHCR Publishing (Recommended)

> [!IMPORTANT]
> **No VPS Builds:** Building Docker images directly on the 1 GB RAM VPS is **unsupported**. All builds must take place on the local development machine or CI/CD runner. The VPS only pulls and runs the prebuilt image from GitHub Container Registry (GHCR).

```powershell
# 1. Build the reproducible production image using its own directory as context
docker build -t astro-calc-service:optimized-v1 c:\wamp64\www\astro_setu\astro-calc-service

# 2. Authenticate to GitHub Container Registry
echo $env:CR_PAT | docker login ghcr.io -u <GITHUB_USERNAME> --password-stdin

# 3. Tag and push (compressed transfer: only 62.5 MB)
docker tag astro-calc-service:optimized-v1 ghcr.io/<ORG_OR_USER>/astro-calc-service:v1.0.0
docker tag astro-calc-service:optimized-v1 ghcr.io/<ORG_OR_USER>/astro-calc-service:latest

docker push ghcr.io/<ORG_OR_USER>/astro-calc-service:v1.0.0
docker push ghcr.io/<ORG_OR_USER>/astro-calc-service:latest
```

---

## 3. VPS Deployment (Pull & Run Prebuilt Image)

### Step 3.1 — Directory & Secrets Setup
```bash
sudo mkdir -p /opt/astro-calc-service
sudo chown -R $USER:$USER /opt/astro-calc-service
cd /opt/astro-calc-service

cat << 'EOF' > .env
PORT=8080
ASTRO_CALCULATION_HMAC_SECRET=<64_CHAR_HEX_SECRET>
APP_ENV=production
EOF
chmod 600 .env
```

### Step 3.2 — Pull & Run Prebuilt Image
Run with loopback binding (`-p 127.0.0.1:8080:8080`) so port 8080 is **never** exposed to the public internet:
```bash
# Authenticate to GHCR
echo $CR_PAT | docker login ghcr.io -u <GITHUB_USERNAME> --password-stdin

# Pull prebuilt image
docker pull ghcr.io/<ORG_OR_USER>/astro-calc-service:v1.0.0

# Run container (enforcing 256 MB RAM ceiling and 1.0 vCPU limit)
docker run -d \
  --name astro-calc-service \
  --restart unless-stopped \
  --cpus="1.0" \
  --memory="256m" \
  --memory-swap="512m" \
  -p 127.0.0.1:8080:8080 \
  --env-file /opt/astro-calc-service/.env \
  ghcr.io/<ORG_OR_USER>/astro-calc-service:v1.0.0
```

---

## 4. HTTPS Reverse Proxy with Caddy

Caddy serves as the lightweight HTTPS edge proxy, managing Let's Encrypt TLS automatically and proxying traffic to the internal container.

### Step 4.1 — Install Caddy on Debian 12
```bash
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update
sudo apt install -y caddy
```

### Step 4.2 — Configure `/etc/caddy/Caddyfile`
```caddyfile
calc.yourdomain.com {
    reverse_proxy 127.0.0.1:8080 {
        header_up Host {host}
        header_up X-Real-IP {remote_host}
        header_up X-Forwarded-For {remote_host}
        header_up X-Forwarded-Proto {scheme}
    }
}
```

Reload Caddy:
```bash
sudo systemctl reload caddy
```

---

## 5. Firewall Configuration (UFW)

Since Docker binds strictly to `127.0.0.1:8080`, port 8080 is completely inaccessible from the outside. Only SSH (port 22) and Caddy (ports 80 and 443) are allowed:

```bash
# Reset UFW defaults
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Allow SSH and Caddy HTTPS
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable
```

*(Optional IP Whitelisting):* To restrict HTTPS access solely to Hostinger:
```bash
sudo ufw delete allow 443/tcp
sudo ufw allow from <HOSTINGER_OUTBOUND_IP> to any port 443 proto tcp
```

---

## 6. Hostinger Laravel Integration

In the Hostinger Laravel backend `.env`, configure the secure HTTPS endpoint:

```ini
ASTRO_CALCULATION_DRIVER=remote
ASTRO_CALCULATION_REMOTE_URL=https://calc.yourdomain.com
ASTRO_CALCULATION_HMAC_SECRET=<64_CHAR_HEX_SECRET>
ASTRO_CALCULATION_CONNECT_TIMEOUT=3.0
ASTRO_CALCULATION_TIMEOUT=10.0
ASTRO_CALCULATION_FALLBACK_TO_LOCAL=false
```

Clear and re-cache configuration on Hostinger:
```bash
php artisan config:clear
php artisan config:cache
```
