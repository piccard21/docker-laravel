FROM php:7.4-cli-alpine

# Arguments defined in docker-compose.yml
ARG user
ARG uid

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install xdebug-2.9.7 \
    && pecl install trader-0.5.1 \
    && docker-php-ext-enable xdebug \
    && apk add --update sudo \
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
    && addgroup $user www-data \
    && echo "$user ALL=(ALL) NOPASSWD: ALL" > /etc/sudoers.d/$user \
    && chmod 0440 /etc/sudoers.d/$USER

RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# supercronic - cron for containers
ENV SUPERCRONIC_URL=https://github.com/aptible/supercronic/releases/download/v0.1.12/supercronic-linux-amd64 \
    SUPERCRONIC=supercronic-linux-amd64 \
    SUPERCRONIC_SHA1SUM=048b95b48b708983effb2e5c935a1ef8483d9e3e

RUN curl -fsSLO "$SUPERCRONIC_URL" \
 && echo "${SUPERCRONIC_SHA1SUM}  ${SUPERCRONIC}" | sha1sum -c - \
 && chmod +x "$SUPERCRONIC" \
 && mv "$SUPERCRONIC" "/usr/local/bin/${SUPERCRONIC}" \
 && ln -s "/usr/local/bin/${SUPERCRONIC}" /usr/local/bin/supercronic

COPY --chown=$user:$user crontabs /etc/crontabs/myAwesomeCrons

COPY ./do-ntp /etc/periodic/15min/do-ntp
RUN chmod +x /etc/periodic/15min/do-ntp

# Tell docker that all future commands should run as the appuser user
USER $user

# Set working directory
WORKDIR /var/www

CMD [ "/usr/local/bin/supercronic", "/etc/crontabs/myAwesomeCrons" ]

