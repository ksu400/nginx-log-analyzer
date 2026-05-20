FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    unzip \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        intl \
        zip \
        gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

EXPOSE 8080

CMD ["sh", "-c", "composer install --no-dev --optimize-autoloader --no-interaction --quiet && php yii serve 0.0.0.0:8080"]
