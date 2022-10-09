# My Reddit Archiver | Development

- [My Reddit Archiver | Development](#my-reddit-archiver--development)
  - [Setup](#setup)
    - [Hook Into Containers](#hook-into-containers)
  - [Testing](#testing)
    - [Configure Test Database](#configure-test-database)
    - [Run Tests](#run-tests)
  - [Database Changes](#database-changes)
  - [ffmpeg](#ffmpeg)

## Setup

```bash
cp .env.sample .env # And update the .env file with real values.
docker build ./build -f=build/Dockerfile --tag=mra:local # Build PHP image.
```

### Hook Into Containers

API:

```bash
docker exec -it mra-api sh
```

## Testing

### Configure Test Database

```bash
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:schema:create
```

Load Fixtures:

```bash
php bin/console --env=test doctrine:fixtures:load
```

### Run Tests

Run tests with either of the following approaches:

```bash
# Hook into container first, then run the tests.
docker exec -it mra-api sh
php bin/phpunit

# Run tests directly.
docker exec -it mra-api sh php bin/phpunit
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

## ffmpeg

`ffmpeg` is leveraged to combine Reddit-hosted video and audio files (once downloaded) into a single video file. The command used for this operation is as follows:

```bash
ffmpeg -i source_video_file.mp4  -i source_audio_file.mp4  -c:v copy -c:a aac combined_output_file.mp4  -hide_banner -loglevel error
```

The command was sourced from the following page: <https://superuser.com/a/277667>
