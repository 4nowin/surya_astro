FROM php:8.4-cli-bookworm

# Metadata
LABEL maintainer="Astro Setu Engineering Team"
LABEL description="Debian 12 Bookworm PHP 8.4 CLI environment for dedicated astrology calculation benchmarks"

# Install build dependencies for FFI and process control
RUN apt-get update && apt-get install -y --no-install-recommends \
    libffi-dev \
    procps \
    && docker-php-ext-install ffi pcntl \
    && rm -rf /var/lib/apt/lists/*

# Production-like PHP configuration for calculation microservice
RUN { \
        echo 'memory_limit = 512M'; \
        echo 'ffi.enable = 1'; \
        echo 'opcache.enable_cli = 1'; \
        echo 'opcache.memory_consumption = 64'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 10000'; \
        echo 'opcache.validate_timestamps = 0'; \
    } > /usr/local/etc/php/conf.d/astrology-benchmark.ini

WORKDIR /app

# Non-root user setup for production-like security
RUN groupadd -g 1000 astro && useradd -u 1000 -g astro -m astro

ENTRYPOINT ["php"]
