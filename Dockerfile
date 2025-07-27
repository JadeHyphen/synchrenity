FROM php:8.1-fpm
WORKDIR /var/www/html
COPY . .
RUN apt-get update && apt-get install -y libpq-dev && docker-php-ext-install pdo pdo_mysql
CMD ["php-fpm"]
