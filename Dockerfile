FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev libxml2-dev unzip \
    && docker-php-ext-install bcmath intl mbstring pdo_mysql xml \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --no-progress

EXPOSE 8000
