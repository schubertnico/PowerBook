FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    msmtp \
    msmtp-mta \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    pdo \
    pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Create log directory
RUN mkdir -p /var/log/php && chown www-data:www-data /var/log/php

# Configure msmtp for mail sending to Mailpit
RUN echo "account default\n\
host mail\n\
port 1025\n\
from webmaster@powerbook.local\n\
auth off\n\
tls off" > /etc/msmtprc

# Copy custom PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80
