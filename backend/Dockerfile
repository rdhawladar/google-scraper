FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    nginx \
    supervisor

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:2.6.5 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/public \
    && mkdir -p /var/log/supervisor \
    && mkdir -p /var/log/nginx \
    && mkdir -p /run/php \
    && chown -R www-data:www-data /var/www/html \
    && chown -R www-data:www-data /var/log/supervisor \
    && chown -R www-data:www-data /var/log/nginx \
    && chown -R www-data:www-data /run/php

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Copy PHP-FPM configuration
RUN echo "php_admin_value[error_log] = /var/log/php-fpm/php-error.log" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "php_admin_flag[log_errors] = on" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "catch_workers_output = yes" >> /usr/local/etc/php-fpm.d/www.conf

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-interaction --no-scripts

# Set permissions again after copying files
RUN chown -R www-data:www-data /var/www/html

# Set up supervisor
COPY docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
