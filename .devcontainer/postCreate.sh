#!/bin/bash
set -euo pipefail

echo "Setting up Memories development environment..."

# Install dependencies
make init

# Fix permissions
chown -R www-data:www-data bin-ext
git config --global --add safe.directory /var/www/html/custom_apps/memories

# Install Nextcloud only when the persistent configuration is not installed.
if ! sudo -E -u www-data php /var/www/html/occ status --output=json | php -r '$s = json_decode(stream_get_contents(STDIN), true); exit(empty($s["installed"]) ? 1 : 0);'; then
sudo -E -u www-data php /var/www/html/occ maintenance:install \
    --verbose \
    --database=mysql \
    --database-name=nextcloud \
    --database-host=db \
    --database-user=nextcloud \
    --database-pass=nextcloud \
    --admin-user=admin \
    --admin-pass=admin
fi

# Enable debug mode in Nextcloud
sudo -E -u www-data php /var/www/html/occ config:system:set --type bool --value true debug

# Enable Memories
sudo -E -u www-data php /var/www/html/occ app:enable memories
sudo -E -u www-data php /var/www/html/occ memories:index

if [ ! -f .vscode/launch.json ]; then
    cp .devcontainer/launch.json .vscode/launch.json
fi

echo "Start the frontend watcher in a container terminal: make watch-js"
