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

# Configure the .env variables.
export DATABASE_URL="mysql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}?serverVersion=mariadb-10.8.6&charset=utf8mb4"
> .env
echo "DATABASE_URL=${DATABASE_URL}" >> .env

if [[ $APP_ENV != "prod" ]]; then
    # Note: it is intentional that the APP_ENV is written to the .env file instead of declared in the Dockerfile.dev because the container Environment Variables take precedence over the .env file(s).
    # As a result, running tests in the development environment fail due to not switching to the test environment programmatically when running. The following error is thrown:
    # Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException: You have requested a non-existent service "test.service_container". Did you mean this: "service_container"?
    # So to address this and have the command line switch environments correctly during tests, the APP_ENV variable is written to the .env file.
    # See the following page for more info: https://symfony.com/doc/current/configuration.html#overriding-environment-values-via-env-local
    # "Real environment variables always win over env vars created by any of the .env files."
    echo "APP_ENV=dev" >> .env
fi

# Generate APP_SECRET.
export APP_SECRET=$(openssl rand -base64 40 | tr -d /=+ | cut -c -32)
echo "APP_SECRET=${APP_SECRET}" >> .env

# Configure Typesense.
TYPESENSE_API_KEY=$(openssl rand -base64 40 | tr -d /=+ | cut -c -32)
> /etc/typesense/typesense-config.ini
echo "TYPESENSE_API_KEY=${TYPESENSE_API_KEY}" >> .env
echo "[server]" >> /etc/typesense/typesense-config.ini
echo "" >> /etc/typesense/typesense-config.ini
echo "api-key = ${TYPESENSE_API_KEY}" >> /etc/typesense/typesense-config.ini
echo "data-dir = /etc/typesense/typesense-data" >> /etc/typesense/typesense-config.ini
echo "enable-cors = true" >> /etc/typesense/typesense-config.ini

# Install and configure composer dependencies.
if [[ $APP_ENV = "prod" ]]; then
    echo "Installing composer dependencies."
    composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress
    composer clear-cache
    composer dump-autoload --classmap-authoritative --no-dev
    composer dump-env prod
    composer run-script --no-dev post-install-cmd
else
  echo "Installing composer dependencies. This will take a few minutes since xdebug is installed."
  composer install
fi

# Wait for the database to be accessible before proceeding.
echo "Attempting to reach database ${DB_HOST}:${DB_PORT} with user ${DB_USERNAME}."
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

# Install Symfony UX/Encore dependencies.
echo "Installing Symfony UX/Encore dependencies."
yarn install
if [[ $APP_ENV = "prod" ]]; then
    yarn encore prod
fi

# Container is set up. Start services.
echo "Starting services."
/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf