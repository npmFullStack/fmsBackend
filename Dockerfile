# Use the official PHP 8.2 with Apache image
FROM php:8.2-apache

# Install required PHP extensions and system packages
RUN apt-get update && apt-get install -y \
    git zip unzip libpng-dev libonig-dev libxml2-dev curl libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all project files
COPY . /var/www/html

# Install Composer (from the official composer image)
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set Apache DocumentRoot to /public
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage/framework/ /var/www/html/storage/logs/

# Generate storage link
RUN php artisan storage:link

# Clear all caches
RUN php artisan config:clear
RUN php artisan cache:clear
RUN php artisan view:clear

# Expose port 80
EXPOSE 80

# Start Apache server with migrations and cache clearing
CMD php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan migrate --force && apache2-foreground