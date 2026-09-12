FROM php:8.4.25-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq5 libpq-dev unzip \
    && docker-php-ext-install pcntl pdo_pgsql \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove libpq-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2.8.12 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/cvortex.ini

WORKDIR /var/www/html
COPY . .

RUN composer install --no-interaction --prefer-dist \
    && chown -R www-data:www-data storage bootstrap/cache

CMD ["php-fpm"]
