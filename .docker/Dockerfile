ARG PHP_VERSION=8.5

#
# Base stage — shared by production and development
#
FROM php:${PHP_VERSION}-cli-alpine AS base

RUN set -eux \
    && apk update && apk upgrade --no-cache \
    && apk add --no-cache ca-certificates \
    && update-ca-certificates \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app

#
# Vendor builder — install production dependencies and generate optimized autoloader
#
FROM base AS vendor-builder

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
COPY bin ./bin
COPY src ./src
RUN composer install --no-interaction --no-progress --no-ansi --no-scripts --no-dev --classmap-authoritative --optimize-autoloader

#
# Production image (default target)
#
FROM base AS production

COPY --from=vendor-builder /app/vendor ./vendor
COPY --from=vendor-builder /app/bin ./bin
COPY --from=vendor-builder /app/src ./src
COPY resources ./resources

RUN adduser -S -u 1000 -G root app \
    && mkdir -p /app/output \
    && chown app:root /app/output
USER app

ENTRYPOINT ["php", "bin/bmm-publisher"]

#
# Development image
#
FROM base AS development

# Xdebug and Composer
COPY --chmod=0755 --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN set -eux \
    && mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && apk add --no-cache git \
    && install-php-extensions xdebug @composer

COPY .docker/php/zz-development.ini /usr/local/etc/php/conf.d/

# App user with XDG dirs for Composer cache
ARG uid=1000
ARG gid=1000
ENV XDG_CONFIG_HOME=/data/.config
ENV XDG_CACHE_HOME=/data/.cache
RUN addgroup -g ${gid} -S app \
    && adduser -u ${uid} -G app -S app \
    && mkdir -m 0775 -p "$XDG_CONFIG_HOME/composer" "$XDG_CACHE_HOME/composer" \
    && chown -R app:app "$XDG_CONFIG_HOME" "$XDG_CACHE_HOME"

ENV COMPOSER_ALLOW_SUPERUSER=0 \
    PATH=/app/bin:/app/vendor/bin:$PATH

USER app
