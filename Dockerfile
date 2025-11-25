# Use the official PHP 8.2 with Apache image
FROM php:8.2-apache

# Install required PHP extensions and system packages
RUN apt-get update && apt-get install -y \
    git zip unzip libpng-dev libonig-dev libxml2-dev curl libzip-dev \
    libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

COPY composer.json composer.lock ./

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .

RUN sed -i 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf

RUN echo "<Directory /var/www/html/public>" > /etc/apache2/conf-available/laravel.conf \
    && echo "    Options Indexes FollowSymLinks" >> /etc/apache2/conf-available/laravel.conf \
    && echo "    AllowOverride All" >> /etc/apache2/conf-available/laravel.conf \
    && echo "    Require all granted" >> /etc/apache2/conf-available/laravel.conf \
    && echo "</Directory>" >> /etc/apache2/conf-available/laravel.conf

RUN a2enconf laravel

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN php artisan storage:link || true

CMD ["apache2-foreground"]

EXPOSE 80
