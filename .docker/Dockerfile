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

# Enable Apache modules required by PowerBook .htaccess
RUN a2enmod rewrite headers expires

# AllowOverride All fuer /var/www/html, damit .htaccess greift.
# Apache-Default ist "None" - das wuerde alle Schutz-Regeln deaktivieren.
RUN sed -ri 's!<Directory /var/www/>\n\s+Options Indexes FollowSymLinks\n\s+AllowOverride None\n\s+Require all granted\n</Directory>!<Directory /var/www/>\n\tOptions FollowSymLinks\n\tAllowOverride All\n\tRequire all granted\n</Directory>!' /etc/apache2/apache2.conf || true \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# ServerSignature/ServerTokens sicher konfigurieren
RUN echo "ServerSignature Off\nServerTokens Prod" > /etc/apache2/conf-available/security-hardening.conf \
    && a2enconf security-hardening

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
