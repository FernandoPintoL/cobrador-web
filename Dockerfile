# Use PHP 8.2 to match Railway's version
FROM php:8.2-fpm-alpine

# Install minimal system dependencies and build requirements for PHP extensions
RUN apk add --no-cache \
    git \
    curl \
    nodejs \
    npm \
    nginx \
    supervisor \
    libzip-dev \
    zlib-dev \
    postgresql-dev \
    $PHPIZE_DEPS

# Install and enable PHP extensions required by dependencies
RUN docker-php-ext-install zip pdo_mysql mbstring pdo_pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies (now that ext-zip is available)
# Defer Composer scripts (which call artisan) until after app code is copied
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy package files
COPY package.json package-lock.json ./

# Install Node dependencies
RUN npm ci

# Copy application code
COPY . /app/

# Configure Nginx and Supervisor
RUN mkdir -p /run/nginx /var/log/nginx /etc/nginx/http.d \
    && cp /app/nginx.conf /etc/nginx/http.d/default.conf \
    && cp /app/supervisord.conf /etc/supervisord.conf

# Build assets
RUN npm run build

# Clear any prebuilt caches (env will be provided at runtime on Railway)
RUN php artisan package:discover --ansi || true && \
    php artisan optimize:clear || true && \
    chmod -R 777 storage/ public/ bootstrap/cache

# Expose HTTP port (Railway defaults to 8080)
EXPOSE 8080

# Start Nginx and PHP-FPM via Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
