FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        intl \
        zip \
        gd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

EXPOSE 8080

CMD ["php", "yii", "serve", "0.0.0.0:8080"]
