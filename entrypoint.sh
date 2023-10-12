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

# Configure the .env file.
> .env
echo "APP_PUBLIC_PATH=${APP_PUBLIC_PATH}" >> .env
echo "REDDIT_USERNAME=${REDDIT_USERNAME}" >> .env
echo "REDDIT_PASSWORD=${REDDIT_PASSWORD}" >> .env
echo "REDDIT_CLIENT_ID=${REDDIT_CLIENT_ID}" >> .env
echo "REDDIT_CLIENT_SECRET=${REDDIT_CLIENT_SECRET}" >> .env
export DATABASE_URL="sqlite:///%kernel.project_dir%/database/app.db"
echo "DATABASE_URL=${DATABASE_URL}" >> .env
echo "MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0" >> .env

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

# Execute any pending database migrations and console commands.
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