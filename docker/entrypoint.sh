#!/bin/sh
set -e

# One image, two roles. Railway can't set a per-service start command via the
# CLI, so web and worker share this entrypoint and branch on $CONTAINER_ROLE.
if [ "$CONTAINER_ROLE" = "worker" ]; then
    exec php artisan queue:work --tries=3 --max-time=3600
fi

# web (default): cache config/events (in a real shell, so $PORT expands), seed,
# then serve. The seeding lives here rather than in railway.json's
# preDeployCommand because Railway execs that without a shell and it cannot
# chain. No route:cache — settings.php has a closure route that can't serialise.
php artisan config:cache
php artisan event:cache
php artisan db:seed --class=StagingSeeder --force

# The catalog is what the classifier matches typed food against; an empty one
# sends every entry to the review queue and leaves correlations with no tags to
# work with. Taxonomy first — the foods resolve their categories by slug. Both
# are idempotent, so running them on every boot is a no-op once seeded.
php artisan db:seed --class=CategoryTaxonomySeeder --force
php artisan db:seed --class=CommonFoodsSeeder --force

exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port="${PORT:-8080}"
