# Use PHP 8.2 to match Railway's version
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    nodejs \
    npm \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite-dev

# Create nginx directories
RUN mkdir -p /var/log/nginx && mkdir -p /var/cache/nginx

# Copy nginx config
COPY nginx.conf /etc/nginx/http.d/default.conf

# Copy supervisor config
COPY supervisord.conf /etc/supervisor/supervisord.conf

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd soap zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Update PHP dependencies to resolve version conflicts
RUN composer update --ignore-platform-reqs --no-dev --optimize-autoloader

# Copy package files
COPY package.json package-lock.json ./

# Install Node dependencies
RUN npm ci

# Copy application code
COPY . /app/

# Build assets
RUN npm run build

# Run Laravel optimizations
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan storage:link && \
    chmod 777 -R storage/ && \
    chmod 777 -R public/ && \
    chmod 777 -R bootstrap/cache

# Expose port
EXPOSE 80

# Start supervisor
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]