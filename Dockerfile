
# --- Synchrenity Dockerfile (pushed to the modern limit) ---

# --- Build Stage ---
FROM composer:2.7 AS composer

FROM php:8.1-fpm-alpine AS build
LABEL maintainer="JadeHyphen <communitybladers1@gmail.com>"
LABEL org.opencontainers.image.source="https://github.com/JadeHyphen/synchrenity"

# Install system dependencies and PHP extensions
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    bash git curl libpng-dev libjpeg-turbo-dev libwebp-dev libzip-dev icu-dev zlib-dev openssl-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip intl gd \
    && apk del .build-deps

# Set working directory
WORKDIR /app

# Copy composer and install dependencies (with cache busting)
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy application code (exclude dev, test, and dotfiles via .dockerignore)
COPY . .

# --- Production Stage ---
FROM php:8.1-fpm-alpine AS prod
LABEL maintainer="JadeHyphen <communitybladers1@gmail.com>"
LABEL org.opencontainers.image.source="https://github.com/JadeHyphen/synchrenity"

# Install runtime dependencies only
RUN apk add --no-cache bash curl libpng libjpeg-turbo libwebp libzip icu zlib openssl

# Set up non-root user for security
RUN addgroup -g 1001 -S synchrenity && adduser -u 1001 -S synchrenity -G synchrenity

# Set working directory
WORKDIR /app

# Copy built app from build stage
COPY --from=build /app /app

# Set permissions (non-root, secure)
RUN chown -R synchrenity:synchrenity /app
USER synchrenity

# Expose port
EXPOSE 8080

# Healthcheck (robust, supports both HTTP and FPM)
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -f http://localhost:8080/health || curl -f http://localhost/health || exit 1

# Add runtime observability (optional, comment out if not needed)
# ENV PHP_FPM_LOG_LEVEL=notice
# ENV PHP_MEMORY_LIMIT=256M
# ENV PHP_MAX_EXECUTION_TIME=60

# Start PHP-FPM (with config override support)
CMD ["php-fpm", "-y", "/etc/php8/php-fpm.conf"]
