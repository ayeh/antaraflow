#!/usr/bin/env bash

set -euo pipefail

# CPANEL SETUP (one-time manual steps):
# 1. In cPanel > Domains, set document root to the "public/" subdirectory of this project.
#    This is the recommended approach and avoids needing a root-level .htaccess redirect.
#
# 2. In cPanel > Cron Jobs, add:
# * * * * * /usr/local/bin/php /home/your-cpanel-user/your-app-directory/artisan schedule:run >> /dev/null 2>&1

echo "🚀 Starting deployment..."

# Resolve a PHP 8.4+ binary (the shell/cron CLI default may be an older PHP).
# Covers cPanel (ea-php*) and DirectAdmin (/usr/local/php*) layouts.
PHP_BIN=""
for candidate in php ea-php84 php8.4 php84 \
    /usr/local/php84/bin/php \
    /usr/local/bin/php84 \
    /usr/local/php8*/bin/php \
    /opt/cpanel/ea-php84/root/usr/bin/php \
    /opt/cpanel/ea-php8*/root/usr/bin/php; do
    if command -v "$candidate" >/dev/null 2>&1 && \
        "$candidate" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' >/dev/null 2>&1; then
        PHP_BIN="$candidate"
        break
    fi
done

if [ -z "$PHP_BIN" ]; then
    echo "❌ No PHP >= 8.4 binary found. Edit PHP_BIN in deploy.sh to the correct path." >&2
    exit 1
fi
echo "Using PHP: $("$PHP_BIN" -v | head -n 1)"

# Resolve Composer so it runs under the correct PHP (its shebang may point at old PHP)
if command -v composer >/dev/null 2>&1; then
    COMPOSER="$PHP_BIN $(command -v composer)"
elif [ -f composer.phar ]; then
    COMPOSER="$PHP_BIN composer.phar"
else
    echo "❌ Composer not found." >&2
    exit 1
fi

# Enter maintenance mode
"$PHP_BIN" artisan down --retry=60 --refresh=15

# Pull latest code
git pull origin main

# Install PHP dependencies (production only)
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

# Install JS dependencies and build assets
npm ci
npm run build

# Run database migrations
"$PHP_BIN" artisan migrate --force

# Clear and rebuild caches
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan icons:cache 2>/dev/null || true

# Harden file permissions
chmod 600 .env
chmod -R 750 storage/ bootstrap/cache/

# storage/app/public is symlinked into public/storage and served as static files
# by the web server, which runs under a different group than the app user. The
# 750 hardening above makes uploaded assets (logos, favicons) unreadable to it,
# yielding 404s. Re-open just that public subtree for traversal and reading.
chmod o+x storage storage/app
find storage/app/public -type d -exec chmod 755 {} +
find storage/app/public -type f -exec chmod 644 {} +

# Verify critical production settings
if grep -q "APP_DEBUG=true" .env; then
    echo "⚠️  WARNING: APP_DEBUG is true in production!"
fi

# Restart queue workers (if using database queue)
"$PHP_BIN" artisan queue:restart

# Exit maintenance mode
"$PHP_BIN" artisan up

echo "✅ Deployment complete!"
