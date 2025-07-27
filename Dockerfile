FROM php:8.1-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache bash git curl libpng-dev libjpeg-turbo-dev libwebp-dev libzip-dev icu-dev zlib-dev

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring zip intl gd

# Set working directory
WORKDIR /var/www/synchrenity

# Copy composer and install dependencies
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/synchrenity

# Expose port
EXPOSE 8080

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s CMD curl -f http://localhost:8080/health || exit 1

# Start PHP-FPM
CMD ["php-fpm"]
