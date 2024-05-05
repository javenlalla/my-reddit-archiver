# My Reddit Archiver | Development

- [My Reddit Archiver | Development](#my-reddit-archiver--development)
  - [Setup](#setup)
    - [Hook Into Containers](#hook-into-containers)
  - [Testing](#testing)
    - [Create .env.test](#create-envtest)
    - [Configure Test Database](#configure-test-database)
    - [Run Tests](#run-tests)
  - [Database Changes](#database-changes)
  - [Updating Dependencies](#updating-dependencies)
    - [UX Components](#ux-components)
  - [Frontend Development](#frontend-development)
    - [yarn](#yarn)
  - [ffmpeg](#ffmpeg)
  - [Running Tests](#running-tests)
  - [Running And Testing Images Locally](#running-and-testing-images-locally)
  - [Update PHP Version](#update-php-version)

## Setup

1. Clone repository.
2. Create Development-specific `docker-compose.yml`

    ```yaml
    services:
      mra-dev:
        container_name: mra-dev
        build:
          context: .
          args:
            APP_VERSION: 0.1.0
          dockerfile: ./development/Dockerfile.dev.debian
          # Alpine image also available if preferred.
          # dockerfile: ./development/Dockerfile.dev.alpine
        volumes:
          - ./src:/var/www/mra
          - ./r-media:/r-media
          - ./database:/database
          - /var/www/mra/var/
        working_dir: /var/www/mra
        environment:
          REDDIT_USERNAME: MyRedditUsername
          REDDIT_PASSWORD: "MyRedditPassword"
          REDDIT_CLIENT_ID: "MyAppClientID"
          REDDIT_CLIENT_SECRET: "MyAppClientSecret"
          # If using Jetbrains/PHPStorm, define this value according to your IDE setup for debugging.
          # See the following page for information: https://www.jetbrains.com/help/phpstorm/debugging-a-php-cli-script.html
          # environment:
          # PHP_IDE_CONFIG: "serverName=mra.local.com"
        ports:
          - "2180:80" # Adjust port as needed.
    ```

3. Update the Environment variables in the `environment` section.
4. [Optional] Configure the Environment variable for `PHP_IDE_CONFIG` as needed.
5. [Optional] Adjust host port as needed.
6. Start container `docker-compose up -d`
7. Install `yarn` dependencies
   1. `docker exec -it mra-dev yarn install`
   2. `docker exec -it mra-dev yarn build`

### Hook Into Containers

Hook into the running container using the following command:

```bash
docker exec -it mra-dev sh
```

## Testing

In order to execute the PHPUnit tests, the test database must be instantiated and populated. The following sections provide the commands to set up the test database and run the tests.

### Create .env.test

Create the `test` environment `.env` file:

```bash
cp .env.test.sample .env.test
```

Be sure to provide a randomized secret value to the `APP_SECRET` variable.

### Configure Test Database

Run the following commands within the container to create and configure the `test` database:

> **WARNING**: Ensure the `DATABASE_URL` value in the `.env.test` value has been set to a different Sqlite database file than the Production file. Something like `app_test.db` would suffice.

```bash
# Hook into container first.
docker exec -it mra-dev bash

# Drop any existing database.
php bin/console --env=test doctrine:database:drop --force

# Create the database.
php bin/console --env=test doctrine:database:create

# Run migrations to create the expected tables and schemas.
php bin/console --env=test doctrine:migrations:migrate --no-interaction

# Load test data.
php bin/console --env=test doctrine:fixtures:load --no-interaction --append
```

### Run Tests

Run tests with either of the following approaches:

```bash
# Hook into container first, then run the tests.
docker exec -it mra-dev bash
php bin/phpunit

# Run tests directly.
docker exec -it mra-dev sh php bin/phpunit
```

## Database Changes

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load

# Recreate the testing database as necessary.
php bin/console --env=test doctrine:database:drop --force
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:schema:create
php bin/console --env=test doctrine:fixtures:load
```

## Updating Dependencies

### UX Components

When updating the Symfony UX components, such as `symfony/ux-live-component` or `symfony/ux-twig-component`, and any component related to those, be sure to install/update the relevant frontend dependencies with:

```bash
yarn install
```

Not updating correctly can result in UI interaction errors such as "Missing @checksum. key".

## Frontend Development

### yarn

```bash
docker exec -it mra-dev yarn watch
```

## ffmpeg

`ffmpeg` is leveraged to combine Reddit-hosted video and audio files (once downloaded) into a single video file. The command used for this operation is as follows:

```bash
ffmpeg -i source_video_file.mp4  -i source_audio_file.mp4  -c:v copy -c:a aac combined_output_file.mp4  -hide_banner -loglevel error
```

The command was sourced from the following page: <https://superuser.com/a/277667>

## Running Tests

Create a `.env.test` file in the root directory (next to `docker-compose.test.yml`) and provide values to the following variables:

```env
REDDIT_USERNAME=
REDDIT_PASSWORD=
REDDIT_CLIENT_ID=
REDDIT_CLIENT_SECRET=
```

Spin up the `test` container with relevant `docker compose` file:

```bash
docker compose -f docker-compose.test.yml up -d
```

Execute PHPUnit tests within the container using the following command as a base example:

```bash
docker exec mra-test php bin/phpunit --group ci-tests
```

## Running And Testing Images Locally

Create a Docker Compose file pointed to the desired `Dockerfile`: `docker-compose.local-image.yml`

```yaml
services:
  mra-local-image:
    container_name: mra-local-image
    build:
      context: .
      dockerfile: Dockerfile.debian
      args:
        APP_VERSION: 0.0.9
    volumes:
      - ./r-media:/r-media
      - ./database:/database
      # Mounting the source code folder will overwrite the built files within the image in the cases of the Production and Test images.
      # Mount only as necessary (ex: Development image).
      # - ./src:/var/www/mra
      # Declare Volume only if using Development image.
      # - /var/www/mra/var/
    working_dir: /var/www/mra
    environment:
      REDDIT_USERNAME:
      REDDIT_PASSWORD:
      REDDIT_CLIENT_ID:
      REDDIT_CLIENT_SECRET:
    ports:
      - "2183:80"
```

Spin up/down the container with the following commands:

```bash
docker compose -f docker-compose.local-image.yml up -d

docker compose -f docker-compose.local-image.yml stop
```

## Update PHP Version

Update the following Dockerfiles and test them via the `docker-compose.local-image.yml` setup.

- `development/Dockerfile.dev.debian`:
- `Dockerfile.test`
- `Dockerfile.debian`
- `Dockerfile.alpine`

For each `Dockerfile` updated, build and verify the image with the following command:

```bash
docker compose -f docker-compose.local-image.yml up -d --force-recreate --build && docker logs -f mra-local-image
```
