#!/bin/sh
set -e

# One image, two roles. Railway can't set a per-service start command via the
# CLI, so web and worker share this entrypoint and branch on $CONTAINER_ROLE.
if [ "$CONTAINER_ROLE" = "worker" ]; then
    exec php artisan queue:work --tries=3 --max-time=3600
fi

# web (default): cache config/events (in a real shell, so $PORT expands), seed
# the idempotent staging accounts (railway.json preDeployCommand can't chain —
# Railway execs it without a shell), then serve. No route:cache — settings.php
# has a closure route that can't serialise.
php artisan config:cache
php artisan event:cache
php artisan db:seed --class=StagingSeeder --force
exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port="${PORT:-8080}"
