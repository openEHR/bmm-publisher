ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-cli-alpine

# Create an app user
ARG user=app
ARG group=app
ARG uid=1000
ARG gid=1000
ENV XDG_CONFIG_HOME=/data/.config
ENV XDG_CACHE_HOME=/data/.cache
RUN addgroup -g ${gid} -S ${group} \
    && adduser -u ${uid} -G ${group} -S ${user} \
    && mkdir -m 0755 -p "$XDG_CONFIG_HOME" "$XDG_CACHE_HOME" \
    && chown -R ${gid}:${group} "$XDG_CONFIG_HOME" "$XDG_CACHE_HOME"

# Install dependencies
RUN apk add --no-cache \
      git \
      ca-certificates \
      curl \
    && update-ca-certificates

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=0 \
    PATH=/app/bin:/app/vendor/bin:$PATH

# Install PHP modules and configure it for development
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        xdebug \
    && cp -f "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && php -m

WORKDIR /app
USER app
