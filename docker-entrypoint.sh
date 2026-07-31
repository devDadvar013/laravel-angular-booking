#!/bin/sh
set -e

# Laravel needs these directories to exist at runtime (storage/framework/views, etc.).
# Git doesn't track empty directories, so if they weren't committed with a placeholder
# file, they simply won't exist after cloning — recreate them defensively every start.
mkdir -p storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache/data \
         storage/logs \
         bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Regenerate the autoloader's package discovery (safe, no external services needed)
php artisan package:discover --ansi || true

# Clear + rebuild caches now that DB/Redis are actually reachable (container runtime, not build time)
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache

# Run pending migrations automatically on startup (remove this if you prefer running migrations manually)
php artisan migrate --force

exec "$@"
