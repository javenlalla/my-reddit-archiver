# My Reddit Archiver | Development

- [My Reddit Archiver | Development](#my-reddit-archiver--development)
  - [Setup](#setup)
    - [Hook Into Containers](#hook-into-containers)
  - [Testing](#testing)
    - [Configure Test Database](#configure-test-database)
    - [Run Tests](#run-tests)
  - [Database Changes](#database-changes)
  - [Frontend Development](#frontend-development)
    - [yarn](#yarn)
  - [ffmpeg](#ffmpeg)

## Setup

1. Create and configure an `.env.dev` from the provided `.env.sample` file.

    ```bash
    cp .env.sample .env
    ```

    - `DB_USERNAME` should be configured to `root` to allow `test` database creation and manipulation when running tests.
    - Uncomment and configure the `DB_ROOT_PASSWORD` variable.
    - Ensure the `DB_HOST` is configured to `mra-db-dev`.
2. Create a `docker-compose.yml` file:

    ```bash
    cp docker-compose.development.sample.yml docker-compose.development.yml
    ```

3. Modify as needed.
4. Start application:

    ```bash
    docker-compose -f docker-compose.development.yml up -d
    ```

5. Install the frontend dependencies:

    ```bash
    docker exec -it mra-dev yarn install
    ```

6. Build the frontend UI:

    ```bash
    docker exec -it mra-dev-fe yarn warn
    ```

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
docker exec -it mra-dev sh
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
