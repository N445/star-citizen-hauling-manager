# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1.12-php8.4 AS base
WORKDIR /app
ARG APP_UID=1000
ARG APP_GID=1000
RUN install-php-extensions intl opcache zip pdo_mysql
COPY --from=composer/composer:2-bin /composer /usr/bin/composer
COPY docker/php/conf.d/base.ini $PHP_INI_DIR/conf.d/
# Les conteneurs tournent sous l'uid de l'hôte : sans ce remappage `whoami` répond « I have no name! ».
RUN groupmod -g $APP_GID www-data \
 && usermod -u $APP_UID -g $APP_GID -s /bin/bash -d /home/www-data www-data \
 && install -d -o www-data -g www-data /home/www-data

FROM base AS dev
ENV HOME=/home/www-data \
    XDG_DATA_HOME=/tmp \
    XDG_CONFIG_HOME=/tmp \
    COMPOSER_HOME=/app/var/composer \
    YARN_CACHE_FOLDER=/app/var/yarn
COPY docker/php/conf.d/dev.ini $PHP_INI_DIR/conf.d/
RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*
COPY --from=node:22-trixie-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-trixie-slim /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && npm install -g yarn@1.22.22

FROM base AS builder
ENV APP_ENV=prod APP_DEBUG=0
RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*
COPY composer.* symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative
# --no-scripts saute les auto-scripts de Flex, et public/bundles est gitignoré.
RUN bin/console assets:install public --no-interaction && rm -rf var

# public/build est gitignoré : sans ce stage l'image prod n'a ni CSS ni JS.
FROM node:22-trixie-slim AS assets
WORKDIR /app
COPY --from=builder /app/vendor ./vendor
COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile
COPY webpack.config.js ./
COPY assets ./assets
RUN yarn build

FROM base AS prod
ARG VERSION=dev
ARG REVISION
# Sans ces labels, l'image hérite de ceux de FrankenPHP et le package GHCR s'annonce comme « FrankenPHP ».
LABEL org.opencontainers.image.source="https://github.com/N445/star-citizen-hauling-manager" \
      org.opencontainers.image.url="https://github.com/N445/star-citizen-hauling-manager" \
      org.opencontainers.image.title="Star Citizen Hauling Manager" \
      org.opencontainers.image.description="Planificateur de missions cargo Star Citizen — Symfony 7.4 + FrankenPHP" \
      org.opencontainers.image.licenses="UNLICENSED" \
      org.opencontainers.image.vendor="N445" \
      org.opencontainers.image.version="$VERSION" \
      org.opencontainers.image.revision="$REVISION"
ENV APP_ENV=prod APP_DEBUG=0 SERVER_NAME=":8000" FRANKENPHP_CONFIG="worker ./public/index.php"
COPY docker/php/conf.d/prod.ini $PHP_INI_DIR/conf.d/
COPY --from=builder /app /app
COPY --from=assets /app/public/build /app/public/build
EXPOSE 8000
