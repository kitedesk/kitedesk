# syntax=docker/dockerfile:1

# KiteDesk on FrankenPHP (Caddy with PHP built in). One image runs every role by changing
# its command:
#
#   web        (default)  serves the app on port 8080
#   queue      php artisan queue:work
#   scheduler  php artisan schedule:work
#   realtime   php artisan reverb:start --host=0.0.0.0 --port=8081
#
# Configure it with environment variables only. On start, the web server runs
# `php artisan kitedesk:setup`: it creates the OAuth keys when PASSPORT_* is empty, migrates the
# database and creates the KITEDESK_ADMIN_* administrator on a new installation. APP_KEY is
# required. Mount a volume on /app/storage to keep uploads and the generated keys.

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

FROM node:${NODE_VERSION}-bookworm-slim AS node

# Composer packages and the frontend build. Both are the same on every platform, so this
# stage always runs natively on the build machine, even for arm64 images.
FROM --platform=$BUILDPLATFORM dunglas/frankenphp:1-php${PHP_VERSION} AS build

RUN install-php-extensions bcmath exif intl pcntl zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && php artisan package:discover --ansi \
    && npm run build \
    && rm -rf node_modules

FROM dunglas/frankenphp:1-php${PHP_VERSION} AS app

RUN install-php-extensions bcmath exif intl opcache pcntl pdo_mysql pdo_pgsql redis zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-kitedesk.ini

# Run as www-data. The capability still lets Caddy bind to ports 80 and 443 when
# SERVER_NAME is set to a domain for automatic HTTPS.
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R www-data:www-data /config/caddy /data/caddy

WORKDIR /app

COPY --from=build --chown=www-data:www-data /app /app
RUN php artisan storage:link --no-interaction

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=:8080

USER www-data

EXPOSE 8080

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
