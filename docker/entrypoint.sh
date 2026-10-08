#!/bin/sh
set -e

# A volume mounted on storage/ starts empty, so recreate the folders Laravel writes to.
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# Cache the configuration, routes, views and events from this container's environment.
php artisan optimize --no-interaction --quiet

# The web server (the default command) prepares the installation: OAuth keys, migrations and
# the first administrator. Queue, scheduler and one-off commands skip it.
case "$1" in
    -* | frankenphp) php artisan kitedesk:setup --no-interaction ;;
esac

exec docker-php-entrypoint "$@"
