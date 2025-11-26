FROM php:8.3-fpm

# System dependencies + PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libmemcached-dev \
    zlib1g-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 👉 Copy EVERYTHING first so artisan is present
COPY . .

# 👉 Now run composer install (artisan exists now)
RUN if [ -f composer.json ]; then \
      composer install --no-interaction --prefer-dist --optimize-autoloader; \
    else \
      echo "No composer.json found, skipping composer install"; \
    fi

# Permissions (ignore if storage/bootstrap don't exist yet)
RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache || true

EXPOSE 9000
CMD ["php-fpm"]
