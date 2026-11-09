# QueryLayer app container (dev + self-host release candidate)
# PHP 8.4 CLI image; artisan serve is the dev webserver, the release image can swap to octane/frankenphp later.
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libicu-dev \
        libsodium-dev \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# Database drivers are a hard requirement (external adapters + QueryLayer's own Postgres state).
RUN docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_pgsql \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        gd \
        sodium

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000
