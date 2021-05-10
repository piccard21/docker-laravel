FROM php:7.4-fpm-alpine

# Arguments defined in docker-compose.yml
ARG user
ARG uid

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install xdebug-2.9.7 \
    && pecl install trader-0.5.1 \
    && pecl install mongodb \
    && docker-php-ext-enable xdebug \
    && apk add --no-cache zip libzip-dev libxslt-dev unzip \
    && apk add --no-cache freetype libpng libjpeg-turbo freetype-dev libpng-dev libjpeg-turbo-dev \
    && apk add --no-cache icu-dev \
    && apk add --no-cache libxml2-dev \
    && apk add --no-cache curl \
    &&  docker-php-ext-configure intl \
    &&  docker-php-ext-configure gd --with-freetype --with-jpeg \
    &&  docker-php-ext-configure zip \
    &&  docker-php-ext-install gd intl mysqli opcache soap zip xmlrpc xsl pdo pdo_mysql

# Create system user to run Composer and Artisan Commands
RUN addgroup --system --gid $uid $user \
    && adduser --system $user --uid $uid --ingroup $user --home /home/$user \
    && addgroup $user root \
    && addgroup $user www-data

RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Tell docker that all future commands should run as the appuser user
USER $user

# Set working directory
WORKDIR /var/www

