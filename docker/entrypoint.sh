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
php artisan optimize --no-interaction

exec docker-php-entrypoint "$@"
