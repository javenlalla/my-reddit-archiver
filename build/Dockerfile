FROM php:8.1.7-fpm-alpine

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
#     # mysqli \
#     xmlrpc \
#     simplexml \
#     curl \
#     xml \
#     soap \
#     bcmath \
#     gmp \
#     opcache \
#     ctype \
#     json \
#     tokenizer

# Removed `mbstring` from above RUN command due to incompabilities with 7.4 image.
# https://stackoverflow.com/a/59253249

# RUN docker-php-ext-configure zip \
#     && docker-php-ext-install zip

RUN docker-php-source extract \
    && apk add --no-cache --virtual .phpize-deps-configure $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .phpize-deps-configure \
    && docker-php-source delete

RUN docker-php-source extract \
    && apk add --no-cache --virtual .phpize-deps-configure $PHPIZE_DEPS \
    && pecl install -o -f redis \
    && docker-php-ext-enable redis \
    && apk del .phpize-deps-configure \
    && docker-php-source delete

# COPY ./scripts/* /usr/bin/

COPY php.ini /usr/local/etc/php/php.ini

# Install Composer.
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

ENV TERM=cygwin \
    COLUMNS=175

RUN echo "export PS1='\[\e[36m\]\u:\W$ \[\e[0m\]'" >> $HOME/.profile && \
    echo "export TERM=cygwin" >> $HOME/.profile && \
    echo "stty cols 175" >> $HOME/.profile && \
    echo "export PATH=$HOME/.composer/vendor/bin:$PATH" >> $HOME/.profile
    
RUN wget https://get.symfony.com/cli/installer -O - | bash && \
    mv /root/.symfony/bin/symfony /usr/local/bin/symfony

RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.alpine.sh' | bash
RUN apk add symfony-cli