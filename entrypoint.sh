#!/bin/bash
set -e

# Verify the required Reddit Environment Variables have been configured.
required_variables=(
  REDDIT_USERNAME
  REDDIT_PASSWORD
  REDDIT_CLIENT_ID
  REDDIT_CLIENT_SECRET
)

for variable in "${required_variables[@]}"
do
  if [[ -z ${!variable+x} ]]; then
    echo >&2 "error: environment variable ${variable} missing"
    exit 1
  fi
done

DB_HOST=${DB_HOST:-mra-db}
DB_DATABASE=${DB_DATABASE:-archive_db}
DB_USERNAME=${DB_USERNAME:-my_archiver}
DB_PASSWORD=${DB_PASSWORD:-my_archiver_password}
DB_PORT=${DB_PORT:-3306}

# Configure the DATABASE_URL Environment Variabled needed by the application.
export DATABASE_URL="mysql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}?serverVersion=mariadb-10.8.6&charset=utf8mb4"
echo "DATABASE_URL=${DATABASE_URL}" >> .env

# Install and configure composer dependencies.
composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress
composer clear-cache
composer dump-autoload --classmap-authoritative --no-dev
composer dump-env prod
composer run-script --no-dev post-install-cmd

# Wait for the database to be accessible before proceeding.
timeout 15 bash <<EOT
while ! (mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE}) >/dev/null;
  do sleep 1;
done;
EOT

RESULT=$?
if [ $RESULT -ne 0 ]; then
  echo "Unable to reach database. Exiting" 1>&2;
  exit $RESULT
fi

# Once database is reachable, execute any pending migrations and console commands.
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:persist-reddit-account

# Container is set up. Start services.
echo "Starting services."
/usr/sbin/crond -b -l 8
redis-server --daemonize yes
nginx
php-fpm