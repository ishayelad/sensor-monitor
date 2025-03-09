FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    unzip \
    git && \
    docker-php-ext-install mysqli zip && \
    a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install composer dependencies before copying project files
COPY composer.json composer.lock /var/www/html/
RUN composer install --no-dev --optimize-autoloader

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
