# syntax=docker/dockerfile:1

# --- Build frontend assets ---
FROM node:18 AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ ./resources/
COPY vite.config.js ./
RUN npm run build

# --- Build PHP/Laravel ---
FROM php:8.2-fpm-alpine AS backend

# Install system dependencies
RUN apk add --no-cache \
    bash \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    curl \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    postgresql-dev \
  && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd intl xml \
  && apk del postgresql-dev

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www

# Copy backend code
COPY . .

# Copy built frontend assets
COPY --from=frontend /app/public/build ./public/build

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache


# --- Production: Nginx + PHP-FPM ---
FROM php:8.2-fpm-alpine AS production

# 安裝 nginx、supervisor 及 Laravel 最基本 extension 依賴
RUN apk add --no-cache nginx supervisor \
    icu-dev oniguruma-dev libxml2-dev postgresql-dev \
    && mkdir -p /var/log/nginx \
    # 安裝 Laravel 必要 PHP extension
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring intl xml bcmath pcntl exif
    

# 複製 backend 產物
COPY --from=backend /var/www /var/www
COPY .cicd/nginx.conf /etc/nginx/http.d/default.conf
COPY .cicd/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]