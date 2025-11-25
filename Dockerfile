# Use the official PHP 8.2 with Apache image
FROM php:8.2-apache

# Install required PHP extensions and system packages
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    curl \
    libzip-dev \
    libjpeg-dev \
    libfreetype6-dev \
    jpegoptim optipng pngquant gifsicle \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Enable Apache mod_rewrite and mod_deflate for compression
RUN a2enmod rewrite deflate headers

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies (cache layer)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-progress

# Copy all project files
COPY . .

# Optimize Laravel for production
RUN composer dump-autoload --optimize \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Fix Apache configuration - set DocumentRoot to public
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Apache configuration for better performance
RUN echo "<VirtualHost *:80>" > /etc/apache2/sites-available/000-default.conf
RUN echo "    DocumentRoot /var/www/html/public" >> /etc/apache2/sites-available/000-default.conf
RUN echo "    <Directory /var/www/html/public>" >> /etc/apache2/sites-available/000-default.conf
RUN echo "        AllowOverride All" >> /etc/apache2/sites-available/000-default.conf
RUN echo "        Require all granted" >> /etc/apache2/sites-available/000-default.conf
RUN echo "        Options -Indexes +FollowSymLinks" >> /etc/apache2/sites-available/000-default.conf
RUN echo "    </Directory>" >> /etc/apache2/sites-available/000-default.conf
RUN echo "    # Performance optimizations" >> /etc/apache2/sites-available/000-default.conf
RUN echo "    EnableSendfile on" >> /etc/apache2/sites-available/000-default.conf
RUN echo "    Timeout 300" >> /etc/apache2/sites-available/000-default.conf
RUN echo "</VirtualHost>" >> /etc/apache2/sites-available/000-default.conf

# PHP OPcache configuration for better performance
RUN echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini
RUN echo "opcache.interned_strings_buffer=32" >> /usr/local/etc/php/conf.d/opcache.ini
RUN echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini
RUN echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini
RUN echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/opcache.ini
RUN echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini
RUN echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Additional PHP performance settings
RUN echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/performance.ini
RUN echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/performance.ini
RUN echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/performance.ini
RUN echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/performance.ini

# Permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate storage link (only if storage directory exists)
RUN if [ -d "/var/www/html/storage/app/public" ]; then php artisan storage:link; fi

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose port 80
EXPOSE 80

# Add before the CMD line
RUN a2enmod expires
RUN echo "ExpiresActive On" >> /etc/apache2/conf-available/expires.conf
RUN echo "ExpiresByType image/jpg \"access plus 1 year\"" >> /etc/apache2/conf-available/expires.conf
RUN echo "ExpiresByType image/jpeg \"access plus 1 year\"" >> /etc/apache2/conf-available/expires.conf
RUN echo "ExpiresByType image/gif \"access plus 1 year\"" >> /etc/apache2/conf-available/expires.conf
RUN echo "ExpiresByType image/png \"access plus 1 year\"" >> /etc/apache2/conf-available/expires.conf
RUN echo "ExpiresByType text/css \"access plus 1 month\"" >> /etc/apache2/conf-available/expires.conf
RUN echo "ExpiresByType application/pdf \"access plus 1 month\"" >> /etc/apache2/conf-available/expires.conf
RUN echo "ExpiresByType text/javascript \"access plus 1 month\"" >> /etc/apache2/conf-available/expires.conf
RUN a2enconf expires


# Start Apache server with better performance settings
CMD ["apache2-foreground"]