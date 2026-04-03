#!/usr/bin/env bash

set -euo pipefail

# CPANEL SETUP (one-time manual steps):
# 1. In cPanel > Domains, set document root to the "public/" subdirectory of this project.
#    This is the recommended approach and avoids needing a root-level .htaccess redirect.
#
# 2. In cPanel > Cron Jobs, add:
# * * * * * /usr/local/bin/php /home/your-cpanel-user/your-app-directory/artisan schedule:run >> /dev/null 2>&1

echo "🚀 Starting deployment..."

# Enter maintenance mode
php artisan down --retry=60 --refresh=15

# Pull latest code
git pull origin main

# Install PHP dependencies (production only)
composer install --no-dev --optimize-autoloader --no-interaction

# Install JS dependencies and build assets
npm ci
npm run build

# Run database migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache 2>/dev/null || true

# Harden file permissions
chmod 600 .env
chmod -R 750 storage/ bootstrap/cache/

# Verify critical production settings
if grep -q "APP_DEBUG=true" .env; then
    echo "⚠️  WARNING: APP_DEBUG is true in production!"
fi

# Restart queue workers (if using database queue)
php artisan queue:restart

# Exit maintenance mode
php artisan up

echo "✅ Deployment complete!"
