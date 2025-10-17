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

# Fix Apache configuration - set DocumentRoot to public
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Enable .htaccess files
RUN echo "<Directory /var/www/html/public>" > /etc/apache2/conf-available/laravel.conf
RUN echo "    Options Indexes FollowSymLinks" >> /etc/apache2/conf-available/laravel.conf
RUN echo "    AllowOverride All" >> /etc/apache2/conf-available/laravel.conf
RUN echo "    Require all granted" >> /etc/apache2/conf-available/laravel.conf
RUN echo "</Directory>" >> /etc/apache2/conf-available/laravel.conf
RUN a2enconf laravel

# Permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate storage link
RUN php artisan storage:link

# Clear configuration
RUN php artisan config:clear

# Expose port 80
EXPOSE 80

# Start Apache server
CMD ["apache2-foreground"]