# Astro Setu — Dedicated Calculation Microservice: VPS Deployment Guide

This guide describes the complete, production-ready deployment procedure for the dedicated Debian 12 astrology calculation microservice on the provisioned VPS (1 vCPU, 1 GB RAM, 512 MB swap, 10 GB SSD).

---

## 1. Architecture Overview

```
Flutter Mobile Apps (Astro Setu / Pandit Setu)
              │  (HTTPS JSON REST API)
              ▼
Hostinger Premium Web Hosting (Laravel Backend)
  - MySQL Persistence & Universal Cache (Single Source of Truth)
  - User Authentication, Jajman Profiles, Payments, Notifications
  - Calculation Job Manager & Background Async Orchestration
              │
              │  (Private Service-to-Service HTTP / HMAC-SHA256 Signed)
              ▼
Debian 12 VPS (1 vCPU / 1 GB RAM / 512 MB Swap)
  - Isolated Docker Container (`astro-calc-service`)
  - PHP 8.4 CLI + FFI + Moshier Native C Ephemeris Engine (`libjme.so`)
  - Unprivileged user (`astro`, UID 1000)
  - Enforced 256 MB cgroup ceiling & 1.0 vCPU constraint
  - NO database credentials, NO public data exposure, NO write privileges
```

---

## 2. Prerequisites on Debian 12 VPS

### Step 2.1 — Install Docker Engine & iptables-persistent
On the Debian 12 Bookworm VPS:
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install prerequisites
sudo apt install -y ca-certificates curl gnupg lsb-release iptables-persistent netfilter-persistent

# Add Docker's official GPG key
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

# Add Docker repository
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker CE and Compose plugin
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Enable and start Docker service
sudo systemctl enable docker
sudo systemctl start docker
```

---

## 3. Environment & Security Configuration

### Step 3.1 — Generate Shared HMAC Secret
Run on a secure terminal (Hostinger backend or deployment workstation):
```bash
openssl rand -hex 32
# Output: 64-character hexadecimal string, e.g., e8d4a9235e1b6f002ac79d48b12...
```

### Step 3.2 — VPS Directory Structure
Create the service directory on the Debian VPS:
```bash
sudo mkdir -p /opt/astro-calc-service
sudo chown -R $USER:$USER /opt/astro-calc-service
cd /opt/astro-calc-service
```

Create `/opt/astro-calc-service/.env`:
```ini
# /opt/astro-calc-service/.env
PORT=8080
ASTRO_CALCULATION_HMAC_SECRET=your_generated_64_char_hex_secret_here
APP_ENV=production
```
Restrict permissions so only root and the deployment user can read it:
```bash
chmod 600 /opt/astro-calc-service/.env
```

---

## 4. Production Docker Configuration

### 4.1 Minimal Standalone Architecture (`astro-calc-service`)
The microservice is isolated into a standalone directory (`astro-calc-service/`) containing only the required astrology calculation services, handlers, ephemeris native libraries, and a lean Laravel container bootstrap.

**Key Size Reductions:**
| Metric | Monolithic Image (`:production`) | Optimized Image (`:optimized-v1`) | Reduction |
| :--- | :--- | :--- | :--- |
| **Unpacked Disk Usage** | 1.46 GB (1,495 MB) | **279 MB** | **-80.9%** |
| **Compressed Transfer Size** | 299 MB | **62.5 MB** | **-79.1%** |
| **Build Context** | ~1.5 GB | **9.48 MB** | **-99.3%** |
| **Peak Container RAM** | 51.2 MB | **55.1 MB** (ceiling 256 MB) | **Safe Headroom: ~201 MB** |

### 4.2 Multi-Stage Dockerfile (`astro-calc-service/Dockerfile`)
The service uses a two-stage build:
1. **Builder Stage (`composer:2`):** Installs only essential framework components (`laravel/framework`, `jayeshmepani/*`), excluding heavy monolithic dependencies (`mpdf`, `google/apiclient`, `umpirsky/country-list`, etc.).
2. **Runtime Stage (`debian:bookworm-slim`):** Installs only `php8.4-cli`, `php8.4-ffi`, and `php8.4-opcache` via Sury's official Debian repository, discarding all build tools and compilers.

```dockerfile
# Stage 1: Build vendor dependencies using official Composer image
FROM composer:2 AS vendor-builder

WORKDIR /build

COPY composer.json ./
COPY app/ ./app/
COPY packages/ ./packages/

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress \
    --ignore-platform-req=ext-ffi \
    --ignore-platform-req=ext-pcntl

# Stage 2: Minimal Production Runtime on Debian 12 Bookworm Slim
FROM debian:bookworm-slim AS runtime

LABEL maintainer="Astro Setu Engineering Team"
LABEL description="Minimal Debian 12 Bookworm PHP 8.4 Dedicated Astrology Calculation Service"

ENV DEBIAN_FRONTEND=noninteractive
ENV PORT=8080

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    gnupg \
    procps \
    && curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/trusted.gpg.d/sury-keyring.gpg \
    && echo "deb https://packages.sury.org/php/ bookworm main" > /etc/apt/sources.list.d/php.list \
    && apt-get update && apt-get install -y --no-install-recommends \
    php8.4-cli \
    php8.4-ffi \
    php8.4-opcache \
    php8.4-mbstring \
    php8.4-xml \
    && apt-get purge -y gnupg curl \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN { \
        echo 'memory_limit = 192M'; \
        echo 'ffi.enable = 1'; \
        echo 'opcache.enable_cli = 1'; \
        echo 'opcache.memory_consumption = 64'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 10000'; \
        echo 'opcache.validate_timestamps = 0'; \
    } > /etc/php/8.4/cli/conf.d/99-astrology-service.ini

WORKDIR /app

RUN groupadd -g 1000 astro && useradd -u 1000 -g astro -m astro

COPY --from=vendor-builder --chown=astro:astro /build/vendor /app/vendor
COPY --chown=astro:astro app/ /app/app/
COPY --chown=astro:astro bootstrap/ /app/bootstrap/
COPY --chown=astro:astro config/ /app/config/
COPY --chown=astro:astro packages/ /app/packages/
COPY --chown=astro:astro routes/ /app/routes/
COPY --chown=astro:astro composer.json /app/composer.json
COPY --chown=astro:astro server.php /app/server.php

RUN mkdir -p /app/storage/framework/cache /app/storage/framework/sessions /app/storage/framework/views /app/storage/logs /app/bootstrap/cache \
    && chown -R astro:astro /app/storage /app/bootstrap/cache

USER astro

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php -r "file_get_contents('http://127.0.0.1:8080/health') ? exit(0) : exit(1);"

ENTRYPOINT ["php", "/app/server.php"]
```

### 4.3 `docker-compose.yml`
```yaml
version: '3.8'

services:
  calculation-service:
    image: ghcr.io/<ORG_OR_USER>/astro-calc-service:v1.0.0
    container_name: astro-calc-service
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:8080"
    environment:
      - PORT=8080
      - ASTRO_CALCULATION_HMAC_SECRET=${ASTRO_CALCULATION_HMAC_SECRET}
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 256M
          memory_swap: 512M
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"
```

> [!NOTE]
> **Understanding the 256 MB Memory Limit & 127.0.0.1 Binding:**  
> The 256 MB limit is an operating system cgroup **maximum ceiling**, not a static memory reservation. The container consumes only ~55 MB at peak runtime. Binding to `127.0.0.1:8080` guarantees that port 8080 is **never accessible to the public internet**; only the local Caddy HTTPS reverse proxy running on the host can communicate with the daemon.

---

## 5. Network Security, Caddy HTTPS Reverse Proxy & Firewall

### Step 5.1 — Architecture Overview
```
Hostinger Laravel Backend (HTTPS Client)
             │
             │ HTTPS (Port 443) / Let's Encrypt TLS
             ▼
Debian 12 VPS: Caddy Reverse Proxy
             │
             │ Local Loopback (http://127.0.0.1:8080)
             ▼
Docker Container (`astro-calc-service`)
- Bound strictly to 127.0.0.1:8080 (No public exposure)
- HMAC-SHA256 authenticated + Nonce replay protected
```

### Step 5.2 — Install Caddy on Debian 12 VPS
```bash
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update
sudo apt install -y caddy
```

### Step 5.3 — Configure `/etc/caddy/Caddyfile`
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

### Step 5.4 — Safe Host Firewall (UFW)
Because Docker binds strictly to `127.0.0.1:8080`, external packets cannot reach port 8080 directly. The host firewall allows only administrative SSH (port 22) and Caddy (ports 80 and 443):

```bash
# Default policy
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Allow SSH and Caddy
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable
```

*(Optional IP Whitelisting):* To restrict port 443 solely to Hostinger outbound requests:
```bash
# 1. Determine Hostinger outbound IP via Tinker:
# php artisan tinker --execute="echo file_get_contents('https://api.ipify.org') . PHP_EOL;"

# 2. Restrict HTTPS port 443 to Hostinger only:
sudo ufw delete allow 443/tcp
sudo ufw allow from <HOSTINGER_OUTBOUND_IP> to any port 443 proto tcp
```

```bash
# 1. Create a dedicated chain for the astrology calculation service (if not already existing)
sudo iptables -N ASTRO-CALC-FILTER 2>/dev/null || true

# 2. Flush ONLY the dedicated ASTRO-CALC-FILTER sub-chain (NEVER flush DOCKER-USER)
sudo iptables -F ASTRO-CALC-FILTER

# 3. Allow established and related connections (container return traffic)
sudo iptables -A ASTRO-CALC-FILTER -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT

# 4. Allow local loopback traffic (for health checks on 127.0.0.1)
sudo iptables -A ASTRO-CALC-FILTER -i lo -j ACCEPT
sudo iptables -A ASTRO-CALC-FILTER -s 127.0.0.1 -j ACCEPT

# 5. Allow port 8080 ONLY from verified Hostinger egress IP(s)
# Replace 185.123.45.67 with the actual Hostinger IP obtained in Step 5.1
sudo iptables -A ASTRO-CALC-FILTER -s 185.123.45.67 -j ACCEPT

# (Optional: If Hostinger has multiple outbound IPs, repeat step 5 for each IP)

# 6. Drop all remaining traffic attempting to reach port 8080
sudo iptables -A ASTRO-CALC-FILTER -j DROP

# 7. Route ONLY port 8080 traffic from DOCKER-USER into ASTRO-CALC-FILTER (idempotent jump check)
sudo iptables -C DOCKER-USER -p tcp -m tcp --dport 8080 -j ASTRO-CALC-FILTER 2>/dev/null || \
sudo iptables -I DOCKER-USER 1 -p tcp -m tcp --dport 8080 -j ASTRO-CALC-FILTER

# 8. Persist iptables rules across VPS reboots
sudo netfilter-persistent save
```

### Step 5.3 — Standard Host UFW Configuration
Configure UFW for host-level protection (SSH):
```bash
sudo ufw allow 22/tcp comment "SSH Administrative Access"
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw enable
```

### Step 5.4 — Verify Effective Firewall Rules & External Access Test
1. **Inspect effective iptables rules:**
   ```bash
   sudo iptables -L DOCKER-USER -v -n --line-numbers
   sudo iptables -L ASTRO-CALC-FILTER -v -n --line-numbers
   ```
   Verify that rule 1 of `DOCKER-USER` matches `tcp dpt:8080` and jumps to `ASTRO-CALC-FILTER`, and that other ports bypass this chain.

2. **From an external machine (NOT Hostinger):**
   ```bash
   curl --connect-timeout 3 http://<VPS_IP>:8080/health
   ```
   **Expected result:** Connection times out or is dropped by `ASTRO-CALC-FILTER`.

3. **From the Hostinger server:**
   ```bash
   curl --connect-timeout 3 http://<VPS_IP>:8080/health
   ```
   **Expected result:** `HTTP/1.1 200 OK` with minimal health payload:
   `{"status":"ok","service":"astro-calc-service","timestamp":...,"uptime_seconds":...}`.

> [!NOTE]
> **Mandatory HMAC Authentication:**  
> Even with network firewall IP whitelisting in place, HMAC-SHA256 signature verification remains **strictly mandatory** for `/metrics` and all calculation endpoints (`/calculate/*`). Network isolation and cryptographic authentication operate as complementary layers of defense-in-depth.

---

## 6. HMAC Nonce Replay Protection & Restart Semantics

### Normal Operation
- Every request transmits `X-Timestamp`, `X-Nonce`, and `X-Signature`.
- `X-Timestamp` must be within $\pm 300\text{ seconds}$ (5 minutes) of server time.
- `X-Nonce` is checked against an in-memory hash map (`$seenNonces`).
- If a nonce is repeated, the request is rejected immediately with `401 Unauthorized: Nonce already used`.
- Expired nonces (> 300s old) are automatically pruned every 60 seconds to keep memory consumption bounded (< 2 MB for 10,000 nonces).

### Daemon / Container Restart Behavior
- Because nonce storage is kept in-memory to remain lightweight and avoid adding Redis, **the nonce cache resets if the container or daemon restarts**.
- What this means in practice:
  - If the container restarts, any nonce used during the previous 5 minutes is purged from memory.
  - However, requests older than 300 seconds are still strictly rejected by the timestamp check (`abs($now - $reqTs) > 300`).
  - Thus, the theoretical replay exposure window following an unexpected restart is strictly bounded by at most the remaining duration of the 300-second timestamp window.
- Replay protection does **not** persist across restarts, but the 300-second expiration window provides defense-in-depth without requiring additional database or Redis infrastructure.

---

## 7. Container Restart Policy & In-Flight Request Handling

- **Restart Policy:** Configured as `unless-stopped`.
- **Unexpected Termination:** If the PHP daemon encounters an unhandled fatal error or kernel OOM, Docker automatically restarts the container within seconds.
- **In-Flight Requests:**
  - A single-instance service does NOT provide zero downtime during an unexpected restart.
  - If a restart occurs while a calculation is in-flight, the TCP connection is severed.
  - Laravel's `RemoteHttpCalculationDriver` catches the resulting cURL error (`CURLE_COULDNT_CONNECT` or `CURLE_RECV_ERROR`) and throws `CalculationServiceUnavailableException`.
  - In Laravel's background queue, `CalculationJobManager` catches this exception and returns the job to `pending` with an incremented attempt count (`attempts < max_attempts`).
  - As soon as Docker brings the container back up (typically 1-3 seconds), the worker retries and finishes the calculation cleanly.
  - No database records, cache keys, or user profiles are corrupted.

---

## 8. Laravel Hostinger Integration Configuration

> [!IMPORTANT]
> **Production Fallback Requirement:**  
> Because Hostinger's managed web hosting PHP environment does not support native PHP FFI, **automatic local fallback MUST BE DISABLED (`ASTRO_CALCULATION_FALLBACK_TO_LOCAL=false`) in production**.  
> If the remote calculation service is unreachable, attempting local FFI on Hostinger will cause a fatal PHP crash. Disabling fallback ensures Laravel catches errors cleanly, triggers background job retries, and protects existing data.

In Hostinger Laravel `.env`:
```ini
# Dedicated Astrology Calculation Microservice Configuration
ASTRO_CALCULATION_DRIVER=remote
ASTRO_CALCULATION_REMOTE_URL=https://calc.yourdomain.com
ASTRO_CALCULATION_HMAC_SECRET=your_generated_64_char_hex_secret_here
ASTRO_CALCULATION_CONNECT_TIMEOUT=3.0
ASTRO_CALCULATION_TIMEOUT=10.0
ASTRO_CALCULATION_FALLBACK_TO_LOCAL=false
```

Clear and re-cache Laravel configuration on Hostinger:
```bash
php artisan config:clear
php artisan config:cache
```

### Manual Driver Switching
If the administrator needs to switch calculation drivers:
1. Edit Hostinger `.env`:
   - To use remote VPS: `ASTRO_CALCULATION_DRIVER=remote`
   - To use local CLI worker (in environments where FFI is enabled): `ASTRO_CALCULATION_DRIVER=local`
2. Run:
   ```bash
   php artisan config:cache
   ```

---

## 9. Deployment Workflows & Production Commands

### Option A: GHCR Registry Workflow (Standard & Mandatory)
Building the 279 MB image on the local workstation or CI/CD runner and pulling the 62.5 MB compressed image to the VPS is the **only supported deployment method**. Direct VPS builds are strictly unsupported.

#### On Local Development Machine (Windows):
```powershell
# 1. Build optimized standalone image using its own directory as context
docker build -t astro-calc-service:optimized-v1 c:\wamp64\www\astro_setu\astro-calc-service

# 2. Tag for GitHub Container Registry
docker tag astro-calc-service:optimized-v1 ghcr.io/<ORG_OR_USER>/astro-calc-service:v1.0.0
docker tag astro-calc-service:optimized-v1 ghcr.io/<ORG_OR_USER>/astro-calc-service:latest

# 3. Authenticate and push (compressed transfer is only 62.5 MB)
echo $env:CR_PAT | docker login ghcr.io -u <GITHUB_USERNAME> --password-stdin
docker push ghcr.io/<ORG_OR_USER>/astro-calc-service:v1.0.0
docker push ghcr.io/<ORG_OR_USER>/astro-calc-service:latest
```

#### On Debian 12 VPS:
```bash
# 1. Prepare directory and environment
sudo mkdir -p /opt/astro-calc-service
sudo chown -R $USER:$USER /opt/astro-calc-service
cd /opt/astro-calc-service

cat << 'EOF' > .env
PORT=8080
ASTRO_CALCULATION_HMAC_SECRET=<PASTE_YOUR_64_HEX_SECRET>
APP_ENV=production
EOF
chmod 600 .env

# 2. Pull prebuilt image from GHCR
echo $CR_PAT | docker login ghcr.io -u <GITHUB_USERNAME> --password-stdin
docker pull ghcr.io/<ORG_OR_USER>/astro-calc-service:v1.0.0

# 3. Run production container with enforced limits (256 MB RAM, 1.0 vCPU)
# NOTE: Binds strictly to 127.0.0.1:8080 so port 8080 is NEVER exposed publicly.
docker run -d \
  --name astro-calc-service \
  --restart unless-stopped \
  --cpus="1.0" \
  --memory="256m" \
  --memory-swap="512m" \
  -p 127.0.0.1:8080:8080 \
  --env-file .env \
  ghcr.io/<ORG_OR_USER>/astro-calc-service:v1.0.0

# 4. Verify local container health on loopback
curl -i http://127.0.0.1:8080/health
# Expected: HTTP 200 OK with {"status":"ok","service":"astro-calc-service",...}
```

---

### Option B: Direct VPS Image Building (UNSUPPORTED)
> [!WARNING]
> **Direct VPS Image Building is UNSUPPORTED:**  
> Building Docker images directly on the production Debian 12 VPS (1 vCPU, 1 GB RAM) is **strictly unsupported**. Compiling dependencies and running package managers on a memory-constrained 1 GB VPS risks triggering kernel Out-Of-Memory (OOM) faults and disrupting server stability. All production deployments must pull the prebuilt image from GHCR as described in Option A.

---

### Rollback Procedure to Baseline Image
The monolithic image `astro-calc-service:production` (1.46 GB) remains fully preserved as a tested rollback version:
```bash
# 1. Stop current container
docker stop astro-calc-service
docker rm astro-calc-service

# 2. Run previous monolithic image (also bound strictly to 127.0.0.1)
docker run -d \
  --name astro-calc-service \
  --restart unless-stopped \
  --cpus="1.0" \
  --memory="256m" \
  --memory-swap="512m" \
  -p 127.0.0.1:8080:8080 \
  --env-file .env \
  astro-calc-service:production

# 3. Verify health
curl -i http://127.0.0.1:8080/health
```
