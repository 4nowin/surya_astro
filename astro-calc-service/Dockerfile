# Stage 1: Build vendor dependencies using official Composer image
FROM composer:2 AS vendor-builder

WORKDIR /build

COPY composer.json composer.lock ./
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

# Set environment
ENV DEBIAN_FRONTEND=noninteractive
ENV PORT=8080

# Install PHP 8.4 CLI, FFI, OPcache and required extensions from Sury repository
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

# Configure PHP 8.4 runtime enforcing memory limit and enabling FFI/OPcache for CLI
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

# Create unprivileged service user (UID 1000)
RUN groupadd -g 1000 astro && useradd -u 1000 -g astro -m astro

# Copy vendor from builder stage
COPY --from=vendor-builder --chown=astro:astro /build/vendor /app/vendor

# Copy application files
COPY --chown=astro:astro app/ /app/app/
COPY --chown=astro:astro bootstrap/ /app/bootstrap/
COPY --chown=astro:astro config/ /app/config/
COPY --chown=astro:astro packages/ /app/packages/
COPY --chown=astro:astro routes/ /app/routes/
COPY --chown=astro:astro composer.json composer.lock /app/
COPY --chown=astro:astro server.php /app/server.php

# Create runtime storage and bootstrap cache directories
RUN mkdir -p /app/storage/framework/cache /app/storage/framework/sessions /app/storage/framework/views /app/storage/logs /app/bootstrap/cache \
    && chown -R astro:astro /app/storage /app/bootstrap/cache

USER astro

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php -r "file_get_contents('http://127.0.0.1:8080/health') ? exit(0) : exit(1);"

ENTRYPOINT ["php", "/app/server.php"]
