FROM php:8.1.7-fpm-alpine

ENV APP_ENV=prod
ENV APP_PUBLIC_PATH=/var/www/mra/public
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apk --no-cache add \
    libxml2-dev \
    libmemcached-dev \
    cyrus-sasl-dev \
    curl \
    curl-dev \
    libmcrypt-dev \
    gmp-dev \
    bash \
    git \
    openssh \
    rsync \
    mysql-client \
    patch \
    pcre-dev \
    ncurses \
    findutils \
    zlib-dev \
    libzip-dev \
    # mariadb-connector-c needed for caching_sha2_password plugin if connecting to a MySQL 8.0 database.
    mariadb-connector-c \
    # icu-dev is a dependency required by the `intl` extension.
    icu-dev \
    # ffmpeg is needed for combining Reddit video and audio asset files.
    ffmpeg

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    intl \
    # sysvsem is required for the RateLimiter Semaphore store.
    sysvsem

RUN docker-php-source extract \
    && apk add --no-cache --virtual .phpize-deps-configure $PHPIZE_DEPS \
    && pecl install -o -f redis \
    && docker-php-ext-enable redis \
    && apk del .phpize-deps-configure \
    && docker-php-source delete

COPY build/php.prod.ini /usr/local/etc/php/php.ini

# Install Composer.
# https://stackoverflow.com/a/58694421
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN echo "export PATH=$HOME/.composer/vendor/bin:$PATH" >> $HOME/.profile

# Copy application to image.
RUN mkdir /var/www/mra/
COPY api/app /var/www/mra
WORKDIR /var/www/mra
RUN rm -rf var

# Cron setup.
RUN mkdir /cron-execution
RUN touch /var/log/cron-execution.log
COPY build/cron/full_sync_cron.sh /cron-execution/full_sync_cron.sh
RUN chmod 755 /cron-execution/full_sync_cron.sh
ADD build/cron/crontab.txt /crontab.txt
RUN /usr/bin/crontab /crontab.txt

# Install Nginx.
RUN apk --no-cache add nginx
COPY build/nginx-site.conf /etc/nginx/http.d/default.conf

# Install Redis.
RUN apk --no-cache add redis

COPY entrypoint.sh /entrypoint.sh
RUN chmod u+x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]