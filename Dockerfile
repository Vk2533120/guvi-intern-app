FROM php:8.2-apache

# Install required packages for PHP extensions and Composer
RUN apt-get update && apt-get install -y \
    libssl-dev \
    libcurl4-openssl-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install mysqli and mongodb using the php-extension-installer
COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions mysqli mongodb-1.21.0

# Install Composer securely
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Define the PORT environment variable for Apache (Render overrides this at runtime)
ENV PORT=80

# Setup Apache server configuration to respect the Render $PORT variable
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf \
    && sed -i 's/:80/:${PORT}/g' /etc/apache2/sites-available/000-default.conf

# Set working directory to the Apache web root
WORKDIR /var/www/html

# Copy the application source code (including composer.json)
COPY . /var/www/html/

# Install PHP dependencies without dev packages
RUN composer install --no-dev --optimize-autoloader

# Ensure proper permissions
RUN chown -R www-data:www-data /var/www/html

# The base image already has EXPOSE 80 and CMD ["apache2-foreground"]
