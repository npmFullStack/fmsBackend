#!/bin/bash

echo "=== Starting Application Startup ==="

# Wait for database to be ready (optional but good practice)
# echo "Waiting for database..."
# while ! nc -z $DB_HOST $DB_PORT; do
#   sleep 0.5
# done
# echo "Database is ready!"

echo "Running database migrations..."
php artisan migrate:fresh --seed --force

echo "Clearing and caching configuration..."
php artisan config:clear
php artisan config:cache

echo "Clearing and caching routes..."
php artisan route:clear
php artisan route:cache

echo "Clearing and caching views..."
php artisan view:clear
php artisan view:cache

echo "Linking storage..."
php artisan storage:link || true

echo "=== Starting Apache Server ==="
exec apache2-foreground