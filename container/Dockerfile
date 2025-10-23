# Multi-stage Dockerfile for PHP with Apache and MongoDB support
# Stage 1: Builder - Install dependencies and extensions
FROM php:8.2-apache AS builder

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    unzip \
    git \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    gd \
    opcache \
    exif

# Install MongoDB PHP extension (latest version)
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

# Stage 2: Production - Final lightweight image
FROM php:8.2-apache

# Install runtime dependencies only
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip5 \
    libpng16-16 \
    libjpeg62-turbo \
    libfreetype6 \
    libssl3t64 \
    libcurl4t64 \
    && rm -rf /var/lib/apt/lists/*

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Enable Apache modules and set ServerName/RemoteIP
RUN a2enmod rewrite headers expires deflate remoteip \
    && printf "RemoteIPHeader X-Forwarded-For\nRemoteIPTrustedProxy 172.24.0.0/16\nRemoteIPTrustedProxy 192.168.123.0/24\nRemoteIPTrustedProxy 192.168.100.0/24\n" > /etc/apache2/conf-available/remoteip.conf \
    && a2enconf remoteip \
    && printf "ServerName demo.rashlink.eu.org\n" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# Copy custom Apache configuration
COPY container/docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy PHP configuration
COPY container/docker/php/php.ini /usr/local/etc/php/php.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . /var/www/html/

# Copy vendor from builder stage
COPY --from=builder --chown=www-data:www-data /var/www/html/vendor /var/www/html/vendor

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/var/cache /var/www/html/var/logs /var/www/html/var/sessions \
    && chown -R www-data:www-data /var/www/html/var \
    && chmod -R 755 /var/www/html/var

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache in foreground
CMD ["apache2-foreground"]
