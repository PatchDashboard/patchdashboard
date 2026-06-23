FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction || composer install --no-interaction

FROM php:8.3-cli-bookworm
RUN docker-php-ext-install pdo pdo_mysql
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
