FROM php:8.1.7-fpm-buster

ENV APP_ENV=prod
ENV APP_PUBLIC_PATH=/var/www/mra/public
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt update && apt install -y \
    curl \
    git \
    cron \
    nginx \
    redis \
    mariadb-client \
    supervisor \
    # libicu-dev is a dependency required by the `intl` extension.
    libicu-dev \
    # ffmpeg is needed for combining Reddit video and audio asset files.
    ffmpeg

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    intl \
    # sysvsem is required for the RateLimiter Semaphore store.
    sysvsem

RUN pecl install -o -f redis \
    && docker-php-ext-enable redis

# Clean up apt cache.
RUN rm -rf /var/lib/apt/lists/*

# Configure php.
COPY build/php.prod.ini /usr/local/etc/php/php.ini

# Install Composer.
# https://stackoverflow.com/a/58694421
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN echo "export PATH=$HOME/.composer/vendor/bin:$PATH" >> $HOME/.profile

# Copy application to image.
RUN mkdir /var/www/mra/
COPY src /var/www/mra
WORKDIR /var/www/mra
RUN rm -rf var

# Cron setup.
RUN mkdir /cron-execution
RUN touch /var/log/cron-execution.log
COPY build/cron/full_sync_cron.sh /cron-execution/full_sync_cron.sh
RUN chmod 755 /cron-execution/full_sync_cron.sh
ADD build/cron/crontab.txt /crontab.txt
RUN crontab /crontab.txt

# Configure Nginx.
COPY build/nginx-site.conf /etc/nginx/sites-enabled/default

# Configure Entrypoint.
COPY entrypoint.sh /entrypoint.sh
RUN chmod u+x /entrypoint.sh

# Configure Supervisor.
RUN mkdir -p /var/log/supervisor
COPY ./build/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

ENTRYPOINT ["/entrypoint.sh"]