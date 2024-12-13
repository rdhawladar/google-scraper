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

# Install Composer
COPY --from=composer:2.6.5 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-interaction --no-scripts

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

# Set up supervisor
RUN mkdir -p /etc/supervisor/conf.d/
COPY docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf

EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
