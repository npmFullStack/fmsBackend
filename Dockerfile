# Use the official PHP 8.2 with Apache image
FROM php:8.2-apache

# Set environment variables for production
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV DEBIAN_FRONTEND=noninteractive

# Install required system packages
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    curl \
    libzip-dev \
    libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl

# Enable Apache mod_rewrite and headers
RUN a2enmod rewrite headers

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev dependencies for production)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-progress

# Copy all project files
COPY . .

# Fix Apache configuration - set DocumentRoot to public
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Enable .htaccess files and configure Apache for Laravel
RUN echo "<Directory /var/www/html/public>" > /etc/apache2/conf-available/laravel.conf
RUN echo "    Options Indexes FollowSymLinks" >> /etc/apache2/conf-available/laravel.conf
RUN echo "    AllowOverride All" >> /etc/apache2/conf-available/laravel.conf
RUN echo "    Require all granted" >> /etc/apache2/conf-available/laravel.conf
RUN echo "</Directory>" >> /etc/apache2/conf-available/laravel.conf
RUN a2enconf laravel

# Set proper permissions for Laravel
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create storage link and optimize Laravel (DO NOT RUN MIGRATIONS HERE)
RUN php artisan storage:link

# Optimize Laravel for production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose port 80
EXPOSE 80

# Start Apache server (SAFE - no database operations)
CMD ["apache2-foreground"]