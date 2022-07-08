# My Reddit Archiver

Archive Saved posts under your Reddit account.

## Local Development

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

Configure Test Database:

```bash
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:schema:create
```

Load Fixtures:

```bash
php bin/console --env=test doctrine:fixtures:load
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
