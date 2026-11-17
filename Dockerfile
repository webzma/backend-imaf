# Imagen oficial de PHP 8.4: su pdo_mysql/mysqlnd soporta caching_sha2_password,
# el metodo de auth por defecto de MySQL 8/9 (Railway usa MySQL 9.4).
# Esto es lo que el php84 de Nixpacks NO podia hacer -> error SQLSTATE[HY000] [2054].
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype-dev \
        libonig-dev \
        nginx \
        supervisor \
        gettext-base \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring bcmath gd zip exif \
    && printf '%s\n' 'upload_max_filesize=8M' 'post_max_size=10M' > /usr/local/etc/php/conf.d/uploads.ini \
    && rm -f /etc/nginx/sites-enabled/default \
    && mkdir -p /etc/nginx/templates /tmp/nginx/client_body /tmp/nginx/fastcgi /tmp/nginx/proxy /tmp/nginx/scgi /tmp/nginx/uwsgi \
    && chown -R www-data:www-data /tmp/nginx \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && php artisan view:cache \
    && chown -R www-data:www-data /app

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx.conf.template /etc/nginx/templates/app.conf.template
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod 755 /usr/local/bin/entrypoint.sh \
    && touch /etc/nginx/conf.d/app.conf \
    && chown www-data:www-data /etc/nginx/conf.d/app.conf

EXPOSE 8080

USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
