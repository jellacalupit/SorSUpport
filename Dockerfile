# =========================
# Frontend build
# =========================
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build


# =========================
# Laravel / Apache
# =========================
FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy Laravel application
COPY . .

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

# Copy Vite production build
COPY --from=frontend /app/public/build ./public/build

# Laravel writable directories
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

# Configure Apache to serve Laravel's public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf

EXPOSE 80

CMD ["bash", "-c", "a2dismod mpm_event mpm_worker 2>/dev/null || true; a2enmod mpm_prefork rewrite; mkdir -p storage/app/public && chown -R www-data:www-data storage/app/public; php artisan storage:link; php artisan migrate --force && exec apache2-foreground"]