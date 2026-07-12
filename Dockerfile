# syntax=docker/dockerfile:1

# ============================================================
# Stage 1 — build: composer deps + built Inertia assets.
# Wayfinder's Vite plugin shells out to `php artisan` during
# `npm run build`, so the asset build needs PHP present — hence
# a PHP base here rather than a plain node image.
# ============================================================
FROM dunglas/frankenphp:1-php8.4 AS build

# Runtime + build PHP extensions (pcntl required by Octane)
RUN install-php-extensions pdo_pgsql pgsql intl zip opcache pcntl

# Node 22 (matches CI) + tooling for the Vite build
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip ca-certificates gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 1) PHP deps first for layer caching. --no-scripts: no .git yet (git hooks
#    no-op anyway) and app code not copied, so discovery runs in step 4.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# 2) JS deps (separate cache layer)
COPY package.json package-lock.json ./
RUN npm ci

# 3) Application source
COPY . .

# 4) Optimised autoloader + package discovery + Filament static assets.
#    Dummy APP_KEY so any provider that touches the encrypter can boot at build.
RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && php artisan filament:assets

# 5) Build the front-end (Wayfinder generates typed routes, then Vite bundles)
RUN npm run build

# Drop dev JS deps before copying the tree forward
RUN rm -rf node_modules

# ============================================================
# Stage 2 — runtime: slim FrankenPHP + Octane
# ============================================================
FROM dunglas/frankenphp:1-php8.4 AS runtime

RUN install-php-extensions pdo_pgsql pgsql intl zip opcache pcntl

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /app

# App + vendor + built public/build from the build stage
COPY --from=build /app /app

ENV APP_ENV=staging \
    OCTANE_SERVER=frankenphp

# FrankenPHP binds Railway's injected $PORT (defaults to 8000 locally)
EXPOSE 8000
CMD ["sh", "-c", "php artisan octane:start --server=frankenphp --host=:: --port=${PORT:-8000}"]
