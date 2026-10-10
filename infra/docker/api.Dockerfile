# Image REST API RT05 TAKEDA (api, worker, scheduler, migrate memakai image yang sama).
# Build dari root repo: docker build -f infra/docker/api.Dockerfile .
# Tidak ada secret pada build arg/layer; seluruh credential diberikan sebagai env runtime.

FROM composer:2 AS vendor
WORKDIR /app
COPY rest-api/composer.json rest-api/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY rest-api/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

FROM dunglas/frankenphp:1-php8.4
RUN install-php-extensions pdo_pgsql pgsql intl gd redis pcntl opcache zip \
    && apt-get update && apt-get install -y --no-install-recommends ca-certificates && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=vendor /app /app
# Sumber tunggal hak role runtime (rt05:db-grant-runtime-role membaca ../infra/sql).
COPY infra/sql /infra/sql
COPY infra/docker/php-production.ini /usr/local/etc/php/conf.d/zz-rt05.ini
COPY infra/docker/api-entrypoint.sh /usr/local/bin/rt05-entrypoint
RUN chmod +x /usr/local/bin/rt05-entrypoint \
    && rm -f .env && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private \
    && chown -R www-data:www-data storage bootstrap/cache
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr SERVER_NAME=:80 SERVER_ROOT=/app/public
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php -r "exit(@file_get_contents('http://127.0.0.1/health/live') === false ? 1 : 0);"
ENTRYPOINT ["rt05-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
