# Image website publik RT05 TAKEDA (Blade yang sudah diterima, dirender PHP; tanpa database warga).
# Build dari root repo: docker build -f infra/docker/web.Dockerfile .

FROM node:22-alpine AS assets
WORKDIR /app
COPY website/package.json website/package-lock.json website/.npmrc ./
RUN npm ci
COPY website/ ./
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY website/composer.json website/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY website/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

FROM dunglas/frankenphp:1-php8.4
RUN install-php-extensions intl opcache zip
WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY infra/docker/php-production.ini /usr/local/etc/php/conf.d/zz-rt05.ini
COPY infra/docker/web-entrypoint.sh /usr/local/bin/rt05-entrypoint
RUN chmod +x /usr/local/bin/rt05-entrypoint \
    && rm -rf .env preview node_modules && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr SESSION_DRIVER=file CACHE_STORE=file DB_CONNECTION=sqlite SERVER_NAME=:80 SERVER_ROOT=/app/public
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php -r "exit(@file_get_contents('http://127.0.0.1/') === false ? 1 : 0);"
ENTRYPOINT ["rt05-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
