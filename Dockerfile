# Imagen oficial de PHP 8.4: su pdo_mysql/mysqlnd soporta caching_sha2_password,
# el metodo de auth por defecto de MySQL 8/9 (Railway usa MySQL 9.4).
# Esto es lo que el php84 de Nixpacks NO podia hacer -> error SQLSTATE[HY000] [2054].
FROM php:8.4-cli

# Dependencias de sistema para compilar las extensiones de PHP que usa la app
# (gd -> dompdf, zip/mbstring/bcmath -> Laravel).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring bcmath gd zip exif \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copia el codigo y instala dependencias de produccion.
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    # view:cache no depende de variables de entorno -> se precompila en build.
    && php artisan view:cache

EXPOSE 8080

# config:cache y route:cache se ejecutan en runtime, cuando Railway ya inyecto las
# variables de entorno (APP_KEY, DB_*, etc.). Cachearlas en build hornearia valores
# nulos / podria fallar por falta de APP_KEY.
CMD php artisan config:cache \
    && php artisan route:cache \
    && php artisan migrate --force \
    && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
