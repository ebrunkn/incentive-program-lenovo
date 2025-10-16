# Use official PHP image with FPM
FROM php:7.4-fpm

# Set working directory
WORKDIR /var/www/html

# Install basic PHP extensions and utilities
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy project files to container
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 9000 (PHP-FPM default)
EXPOSE 9000

# Start PHP-FPM server
CMD ["php-fpm"]
