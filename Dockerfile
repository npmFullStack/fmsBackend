# PHP 8.2 with Apache
FROM php:8.2-apache

# Install system packages + required PHP extensions
RUN apt-get update && apt-get install -y \
    git zip unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif bcmath gd zip opcache

# Enable Apache rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (layer caching)
COPY composer.json composer.lock ./

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Install dependencies (production)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy the full project
COPY . .

# Laravel optimize commands (safe)
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Create storage link
RUN php artisan storage:link || true

# Fix Apache DocumentRoot
RUN sed -i 's#/var/www/html#/var/www/html/public#g' \
    /etc/apache2/sites-available/000-default.conf

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Enable basic performance features for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=32" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 80

CMD ["apache2-foreground"]
