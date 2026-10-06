FROM php:8.3-cli

RUN apt-get update && apt-get install -y git unzip libzip-dev libpq-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Klasörleri ve izinleri hazırlama
RUN mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             storage/app/public \
             bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache && \
    chmod +x docker/start.sh

RUN composer install --no-dev --optimize-autoloader --no-interaction

ENV APP_ENV=production \
    LOG_CHANNEL=stderr \
    PORT=10000

EXPOSE 10000

CMD ["sh", "docker/start.sh"]
