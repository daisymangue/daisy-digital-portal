#!/usr/bin/env bash
# Pull the latest portal changes and bring this copy up to date.
# Usage, from the project folder:  ./update.sh
set -euo pipefail
cd "$(dirname "$0")"

if [ -f .env ] && grep -q '^APP_ENV=production' .env; then
    echo "This looks like the live site (APP_ENV=production)."
    read -r -p "Update it anyway? Make sure you have a backup. [y/N] " answer
    [ "$answer" = "y" ] || [ "$answer" = "Y" ] || exit 1
fi

echo "1/5 Getting the latest code..."
git pull --ff-only

echo "2/5 Installing PHP libraries..."
if grep -q '^APP_ENV=production' .env 2>/dev/null; then
    composer install --no-dev --optimize-autoloader --no-interaction
else
    composer install --no-interaction
fi

if [ ! -f .env ]; then
    echo "No .env file yet. Follow the setup steps in README.md first, then run ./update.sh again."
    exit 1
fi

echo "3/5 Updating the database..."
php artisan migrate --force

echo "4/5 Applying defaults for new features..."
php artisan db:seed --force

echo "5/5 Clearing old cached files..."
php artisan optimize:clear > /dev/null

echo ""
echo "Up to date. Latest change:"
git log -1 --pretty=format:"  %s (%cr)%n"
echo ""
