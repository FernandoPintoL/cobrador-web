# Use PHP 8.2 to match Railway's version
FROM php:8.2-fpm-alpine

# Install minimal system dependencies and build requirements for PHP extensions
RUN apk add --no-cache \
    git \
    curl \
    nodejs \
    npm \
    libzip-dev \
    zlib-dev \
    $PHPIZE_DEPS

# Install and enable PHP extensions required by dependencies
RUN docker-php-ext-install zip

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

# Build assets
RUN npm run build

# Run Laravel optimizations
RUN php artisan package:discover --ansi && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    chmod 777 -R storage/ && \
    chmod 777 -R public/
