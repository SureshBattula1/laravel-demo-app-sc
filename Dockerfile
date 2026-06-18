# ---- Laravel API image (PHP 8.4) ----
FROM php:8.4-fpm

# Install PHP extensions reliably via the extension-installer helper.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_mysql \
        mbstring \
        gd \
        intl \
        zip \
        bcmath \
        exif \
        pcntl \
        opcache

# Composer (from the official composer image).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first to leverage Docker layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-progress --prefer-dist

# Copy the rest of the application (vendor/.env excluded via .dockerignore).
COPY . .
RUN composer dump-autoload --optimize --no-scripts \
    && chmod +x docker/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
