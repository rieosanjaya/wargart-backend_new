FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM php:8.4-cli
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libonig-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor /app/vendor
COPY docker/entrypoint.sh /usr/local/bin/wargart-entrypoint
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache /data \
    && chmod +x /usr/local/bin/wargart-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache /data
USER www-data
EXPOSE 8080
ENTRYPOINT ["wargart-entrypoint"]
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
