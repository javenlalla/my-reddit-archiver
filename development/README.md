# My Reddit Archiver | Development

- [My Reddit Archiver | Development](#my-reddit-archiver--development)
  - [Setup](#setup)
    - [Hook Into Containers](#hook-into-containers)
  - [Testing](#testing)
    - [Configure Test Database](#configure-test-database)
    - [Run Tests](#run-tests)
  - [Database Changes](#database-changes)
  - [Updating Dependencies](#updating-dependencies)
    - [UX Components](#ux-components)
  - [Frontend Development](#frontend-development)
    - [yarn](#yarn)
  - [ffmpeg](#ffmpeg)

## Setup

1. Clone repository.
2. Create Development-specific `docker-compose.yml`

    ```yaml
    version: '3.9'

    services:
      mra-dev:
        container_name: mra-dev
        build:
          context: .
          dockerfile: ./development/Dockerfile.dev.alpine
          # Buster image also available if preferred.
          # dockerfile: ./development/Dockerfile.dev.buster
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

### Configure Test Database

1. Create the database and schema.

    ```bash
    # Within the container:
    php bin/console --env=test doctrine:database:create
    php bin/console --env=test doctrine:schema:create
    ```

2. Load the data fixtures.

    ```bash
    # Within the
    php bin/console --env=test doctrine:fixtures:load
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
