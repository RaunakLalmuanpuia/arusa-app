#!/usr/bin/env bash
# Deploy origin/master to the Lightsail server. Push first: this deploys what is on GitHub, not your working copy.
# Usage: ./deploy.sh
set -euo pipefail

SERVER="${DEPLOY_SERVER:-ubuntu@54.252.235.254}"
KEY="${DEPLOY_KEY:-$HOME/.ssh/arusa_lightsail}"

git fetch -q origin master
if [ "$(git rev-parse HEAD)" != "$(git rev-parse origin/master)" ]; then
    echo "Warning: your HEAD differs from origin/master. Deploying origin/master ($(git rev-parse --short origin/master))."
fi

ssh -i "$KEY" "$SERVER" 'bash -s' <<'REMOTE'
set -euo pipefail
export LC_ALL=C.UTF-8
cd /var/www/arusa

git pull -q --ff-only origin master
composer install --no-dev --optimize-autoloader --no-interaction -q
npm ci --silent --no-audit --no-fund
npm run build --silent >/dev/null

# Artisan runs as www-data so the caches and logs it writes stay writable by PHP-FPM and the worker.
# Editing .env with sed -i resets its group, and PHP then silently falls back to defaults (SQLite).
sudo chgrp www-data .env
sudo chgrp -R www-data storage bootstrap/cache && sudo chmod -R ug+rwX storage bootstrap/cache
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize
sudo -u www-data php artisan queue:restart

echo "Deployed $(git log --oneline -1)"
REMOTE
