# Build the Reddit Go CLI tool.
FROM golang:1.20.1-buster AS reddit-go-cli-build

WORKDIR /app

RUN git clone https://github.com/javenlalla/reddit-cli.git
WORKDIR reddit-cli
# COPY go.mod ./
# COPY go.sum ./

RUN go mod download

# COPY *.go ./

RUN go build -o /reddit-sync

# Build the main image.
FROM php:8.1.7-fpm-buster

ENV APP_ENV=prod
ENV APP_PUBLIC_PATH=/var/www/mra/public
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=reddit-go-cli-build /reddit-sync /usr/bin/reddit-sync

RUN apt update && apt install -y \
    curl \
    git \
    cron \
    # zip/unzip packages required for Composer in order to install packages.
    zlib1g-dev \
    libzip-dev \
    unzip \
    nginx \
    redis \
    supervisor \
    # libicu-dev is a dependency required by the `intl` extension.
    libicu-dev \
    # ffmpeg is needed for combining Reddit video and audio asset files.
    ffmpeg

RUN docker-php-ext-install \
    pdo \
    intl \
    # sysvsem is required for the RateLimiter Semaphore store.
    sysvsem

RUN pecl install -o -f redis \
    && docker-php-ext-enable redis

RUN docker-php-ext-install zip

# Install Yarn.
RUN curl -fsSL https://deb.nodesource.com/setup_19.x | bash - && apt-get install -y nodejs \
    && corepack enable \
    && corepack prepare yarn@stable --activate

# Install Typesense.
RUN mkdir -p /etc/typesense/typesense-data && \
    curl -o /etc/typesense/typesense.tar.gz https://dl.typesense.org/releases/0.23.1/typesense-server-0.23.1-linux-amd64.tar.gz && \
    tar -xzf /etc/typesense/typesense.tar.gz -C /etc/typesense

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

# Additional folder setup.
RUN mkdir /database

ENTRYPOINT ["/entrypoint.sh"]