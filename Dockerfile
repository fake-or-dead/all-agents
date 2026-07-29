# syntax=docker/dockerfile:1.7@sha256:a57df69d0ea827fb7266491f2813635de6f17269be881f696fbfdf2d83dda33e

ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1-php8.5-bookworm@sha256:9c07e0c60c8f856e3730c618fa2376ccb7f2493c1379f7bbe8737d89531f2d2a
ARG NODE_IMAGE=node:24-bookworm-slim@sha256:6f7b03f7c2c8e2e784dcf9295400527b9b1270fd37b7e9a7285cf83b6951452d
ARG COMPOSER_IMAGE=composer:2@sha256:5946476338742b200bb9ff88f8be56275ddae4b3949c72305cb0dbf10cfcb760

FROM ${COMPOSER_IMAGE} AS composer-bin

FROM ${FRANKENPHP_IMAGE} AS php-base

RUN install-php-extensions \
    bcmath \
    intl \
    opcache \
    pcntl \
    pdo_pgsql \
    redis \
    sockets \
    zip

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

FROM php-base AS php-dependencies

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --classmap-authoritative

FROM php-base AS php-test-dependencies

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

FROM ${NODE_IMAGE} AS frontend-dependencies

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

FROM frontend-dependencies AS frontend-build

COPY resources ./resources
COPY public ./public
COPY tsconfig.json vite.config.ts ./
RUN npm run build

FROM php-base AS test

ENV APP_ENV=testing \
    PEOPLE_IDENTIFIER_LOOKUP_KEY_VERSION=v1 \
    PEOPLE_IDENTIFIER_LOOKUP_KEY=people-image-test-key-11111111111111111111111111 \
    IDENTITY_ACCOUNT_LOOKUP_KEY_VERSION=v1 \
    IDENTITY_ACCOUNT_LOOKUP_KEY=account-image-test-key-2222222222222222222222222 \
    IDENTITY_RATE_LIMIT_KEY_VERSION=v1 \
    IDENTITY_RATE_LIMIT_KEY=rate-image-test-key-33333333333333333333333333333 \
    IDENTITY_VERIFICATION_ADAPTER=deterministic-fake \
    IDENTITY_DETERMINISTIC_CODE=246810

COPY --from=php-test-dependencies /app/vendor ./vendor
COPY --from=frontend-build /app/public/build ./public/build
COPY . .
RUN cp .env.example .env \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
RUN composer dump-autoload --no-interaction

ENTRYPOINT []
CMD ["php", "artisan", "test"]

FROM php-base AS runtime

ARG APP_BUILD_VERSION=local
ARG APP_BUILD_COMMIT=unknown

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_BUILD_VERSION=${APP_BUILD_VERSION} \
    APP_BUILD_COMMIT=${APP_BUILD_COMMIT}

LABEL org.opencontainers.image.title="Tapoda Next" \
      org.opencontainers.image.version="${APP_BUILD_VERSION}" \
      org.opencontainers.image.revision="${APP_BUILD_COMMIT}"

COPY --from=php-dependencies --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend-build --chown=www-data:www-data /app/public/build ./public/build
COPY --chown=www-data:www-data . .
COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && mkdir -p /config/caddy /data/caddy \
    && chown -R www-data:www-data storage bootstrap/cache /config /data
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=3s --start-period=20s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8080/health/live") === false ? 1 : 0);'

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
