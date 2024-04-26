#!/bin/bash
set -e

dev_env=false
if [[ $APP_ENV != "prod" && $APP_ENV != "test" ]]; then
    dev_env=true
fi

### Customize: Verify reqiured application-specific Environment variables.
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
### End customization.

# Initialize the .env file.
> .env
echo "MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0" >> .env

### Customize: Add application-specific Environment variables to .env file.
echo "APP_PUBLIC_PATH=${APP_PUBLIC_PATH}" >> .env
echo "REDDIT_USERNAME=${REDDIT_USERNAME}" >> .env
echo "REDDIT_PASSWORD=${REDDIT_PASSWORD}" >> .env
echo "REDDIT_CLIENT_ID=${REDDIT_CLIENT_ID}" >> .env
echo "REDDIT_CLIENT_SECRET=${REDDIT_CLIENT_SECRET}" >> .env
### End customization.

# Configure the DATABASE_URL.
if [[ ! -z $DB_HOST ]]; then
    export DATABASE_URL="mysql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}?serverVersion=mariadb-10.8.6&charset=utf8mb4"
    echo "Database URL configured as: ${DATABASE_URL}"
    echo "DATABASE_URL=${DATABASE_URL}" >> .env
elif [[ ! -z $DB_SQLITE_FILENAME ]]; then
    export DATABASE_URL="sqlite:////database/$DB_SQLITE_FILENAME"
    echo "Database URL configured as: ${DATABASE_URL}"
    echo "DATABASE_URL=${DATABASE_URL}" >> .env
elif [[ $APP_ENV = "test" ]]; then
    export DATABASE_URL="sqlite:////database/app_test.db"
    echo "Database URL configured as: ${DATABASE_URL}"
    echo "DATABASE_URL=${DATABASE_URL}" >> .env
else
    # No database configuration detected. Fallback to Sqlite database filename `app.db`.
    # Note: it's also important to have the `DATABASE_URL` set regardless, because it is required as part of the Symfony setup when running `composer install`.
    # An error is thrown during the install if `DATABASE_URL` is not found.
    export DATABASE_URL="sqlite:////database/app.db"
    echo "Database URL configured as: ${DATABASE_URL}"
    echo "DATABASE_URL=${DATABASE_URL}" >> .env
fi

if [[ "$dev_env" = true ]]; then
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

if [[ "$dev_env" = true ]]; then
  echo "Installing composer dependencies. This will take a few minutes since xdebug is installed."
  composer install
fi

if [[ ! -z $DB_HOST ]]; then
    # Wait for the database to be accessible before proceeding.
    echo "Attempting to reach database ${DB_HOST}:${DB_PORT} with user ${DB_USERNAME}."
    echo "Command: mariadb -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE}"
    timeout 15 bash <<EOT
    while ! (mariadb -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE}) >/dev/null;
    do sleep 1;
    done;
EOT

    RESULT=$?
    if [ $RESULT -ne 0 ]; then
        echo "Unable to reach database. Exiting" 1>&2;
        exit $RESULT
    fi
fi

if [[ ! -z $DB_HOST || ! -z $DB_SQLITE_FILENAME || ! -z $DATABASE_URL ]]; then
    if compgen -G "/var/www/mra/migrations/Version*.php" > /dev/null; then
    # [ -f "/var/www/mra/migrations/Version*.php" ]; then
        # Once database is reachable, execute any pending migrations and console commands.
        echo "Migration files exist. Execute migration."
        php bin/console doctrine:migrations:migrate --no-interaction
    else
        echo "No migration files detected. Skipping migration execution."
    fi

    if [[ $APP_ENV = "test" ]]; then
        php bin/console doctrine:database:drop --force
        php bin/console doctrine:database:create
        php bin/console doctrine:migrations:migrate --no-interaction
        php bin/console doctrine:fixtures:load --no-interaction --append
    fi
fi

### Customize: Run application-specific commands.
php bin/console app:persist-reddit-account
### End customization.

# Install Symfony UX/Encore dependencies if not in a Production or Test environment.
if [[ "$dev_env" = true ]]; then
    echo "Installing Symfony UX/Encore dependencies."
    yarn install
fi

# Fix permissions, espeically to address `var` folder not being writeable for cache and logging.
if [[ "$dev_env" = true ]]; then
    if [ -d "/var/www/app" ]; then
        # Fix permissions, especially to address `var` folder not being writeable for cache and logging.
        echo "Fixing application folder permissions."
        chown -R www-data:www-data /var/www/app
    fi
fi

# Container is set up. Start services.
echo "Starting services."
/usr/bin/supervisord -c /etc/supervisor/supervisord.conf