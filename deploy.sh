#!/usr/bin/env bash

set -euo pipefail

# CPANEL CRON SETUP (one-time manual step):
# In cPanel > Cron Jobs, add:
# * * * * * /usr/local/bin/php /home/USERNAME/DEPLOY_PATH/artisan schedule:run >> /dev/null 2>&1

echo "🚀 Starting deployment..."

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

# Restart queue workers (if using database queue)
php artisan queue:restart

echo "✅ Deployment complete!"
