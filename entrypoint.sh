#!/bin/bash
set -e

required_variables=(
  REDDIT_USERNAME
  REDDIT_PASSWORD
  REDDIT_CLIENT_ID
  REDDIT_CLIENT_SECRET
)

for variable in "${required_variables[@]}"
do
  if [[ -z ${!variable+x} ]]; then   # indirect expansion here
    echo >&2 "error: environment variable ${variable} missing"
    exit 1
  fi
done

DB_HOST=${DB_HOST:-localhost}
DB_DATABASE=${DB_DATABASE:-bookstack}
DB_USERNAME=${DB_USERNAME:-bookstack}
DB_PASSWORD=${DB_PASSWORD:-password}
DB_PORT=${DB_PORT:-3306}

export DATABASE_URL="mysql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}?serverVersion=mariadb-10.8.6&charset=utf8mb4"

echo "DATABASE_URL=${DATABASE_URL}" >> .env

composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress
composer clear-cache
composer dump-autoload --classmap-authoritative --no-dev
composer dump-env prod
  # && composer dump-autoload --classmap-authoritative --no-dev \
composer run-script --no-dev post-install-cmd

# If database and schemas already exist.
# php bin/console make:migration
# php bin/console doctrine:migrations:migrate
# php bin/console doctrine:fixtures:load

# # If database/schemas do not already exist or it's desired to start fresh.
# php bin/console doctrine:database:drop --force
# php bin/console doctrine:database:create
# php bin/console doctrine:schema:create
# php bin/console doctrine:fixtures:load
#
# php bin/console doctrine:database:drop --force && php bin/console doctrine:database:create
#
# mysql -hsymfony_webserver_db -umy_archiver -pmy_archiver_password --database archive_db
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:persist-reddit-account


echo "Starting cron config."
/usr/sbin/crond -b -l 8
# Link log to STDOUT to track output in `docker logs`.
# ln -sf /proc/1/fd/1 /var/log/script.log
echo "cron started"

redis-server --daemonize yes
nginx
php-fpm