# My Reddit Archiver

Archive Saved posts under your Reddit account.

- [My Reddit Archiver](#my-reddit-archiver)
  - [Prerequisites](#prerequisites)
    - [Create Reddit Client ID And Secret](#create-reddit-client-id-and-secret)
      - [Limitations](#limitations)
        - [2FA](#2fa)
  - [Setup](#setup)
    - [Dockerfile](#dockerfile)
    - [docker-compose](#docker-compose)
    - [Start Application](#start-application)
  - [Execute Sync](#execute-sync)
  - [Logging](#logging)
    - [Container Logs](#container-logs)
    - [Cron Logs](#cron-logs)
  - [Upgrading](#upgrading)
    - [Upgrading | Dockerfile](#upgrading--dockerfile)
  - [Development](#development)

## Prerequisites

### Create Reddit Client ID And Secret

In order to configure the application, a Reddit Client ID and Client Secret must be generated. This is accomplished by creating a Reddit app under your account. The app and its credentials are needed to make authorized APIs calls to the Reddit API to retrieve user account data such as the profile's `Saved` Link Posts and Comments.

1. While logged into Reddit, navigate to [Authorized Applications](https://www.reddit.com/prefs/apps).
2. Scroll to the bottom of the page and click **create another app**.
3. Provide the following information:
    - Name: a name for client app
    - Select **script**
    - Redirect URI: though not used at this time for this project's current implementation, this is needed to create the client app. The server url where the project will be served can be used here.
4. Click **create app**.
5. Note down the *Client ID* and *Client Secret* as shown in the following screenshot:
    - ![Client ID And Secret](docs/assets/client_app_id_secret.png "Client ID And Secret")

#### Limitations

##### 2FA

The application requires your Reddit `Password` to be provided in the configuration. This is used to make authenticated OAuth calls in order to use Reddit's API. As a result, this flow does not work if 2FA is enabled on the target Reddit account. This may be addressed in the future with a redirect to Reddit for login, but at this time, the only workaround is to disable 2FA.

## Setup

The application can be configured to run via `docker-compose` or using the `Dockerfile` directly with `docker run`. See the following sections on how to set up the application with either of these methods.

### Dockerfile

1. Build the image:

    ```bash
    docker build --tag=mra .
    ```

2. Initialize database (skip this step if using an existing database):

    ```bash
    # Create a network first to allow communication
    # between the application and the database.
    docker network create mra_net

    # Initialize Database
    docker run -d --net mra_net  \
    -e MYSQL_ROOT_PASSWORD=my_archiver_secure_root_pw \
    -e MYSQL_DATABASE=archive_db \
    -e MYSQL_USER=my_archiver \
    -e MYSQL_PASSWORD=my_archiver_password \
    --volume data/db:/var/lib/mysql \
    --name="mra-db" \
    mariadb:10.8.6
    ```

3. Configure environment variables and start the application:

    ```bash
    docker run --rm \
    --name mra \
    --net mra_net \ # Exclude if using an existing database and/or different network.
    -e REDDIT_USERNAME="MyRedditUserName" \
    -e REDDIT_PASSWORD="MyRedditPassword" \
    -e REDDIT_CLIENT_ID="ClientId" \
    -e REDDIT_CLIENT_SECRET="ClientSecret" \
    -e DB_HOST=mra-db \
    -e DB_DATABASE=archive_db \
    -e DB_USERNAME=my_archiver \
    -e DB_PASSWORD=my_archiver_password \
    -p 8080:80 \
    mra
    ```

### docker-compose

Create docker-compose.yml file:

```bash
cp docker-compose.sample.yml docker-compose.yml
```

Update the following values in the created `docker-compose.yml` file:

- REDDIT_USERNAME
- REDDIT_PASSWORD
- REDDIT_CLIENT_ID
  - The `Client ID` generated in the previous section.
- REDDIT_CLIENT_SECRET
  - The `Client Secret` generated in the previous section.
- DATABASE_URL
  - The DSN used to connect to the database. Must be formatted as follows:
    - mysql://DB_USER:DB_USER_PASSWORD@DB_HOST:3306/DB_DATABASE_NAME?serverVersion=mariadb-10.8.3&charset=utf8mb4

If using the database included in the `docker-compose.yml` file, the following parameters also need to be updated:

- MARIADB_ROOT_PASSWORD
- MARIADB_USER
  - This value will be used in the `DATABASE_URL` above.
- MARIADB_PASSWORD
  - This value will be used in the `DATABASE_URL` above.
- MARIADB_DATABASE
  - This value will be used in the `DATABASE_URL` above.

### Start Application

Once the `docker-compose.yml` is configured, the application can be started using the following command:

```bash
docker-compose up -d
```

## Execute Sync

Once the application is configured and running, use the following command to execute the syncing of the Reddit profile's `Saved` Posts down to the local system:

```bash
docker exec -it mra-api ./sync-api
```

## Logging

### Container Logs

View the container logs using the following Docker command:

```bash
docker logs mra
```

### Cron Logs

The cron logs can be viewed using the following command:

```bash
docker exec -it mra sh -c "tail -f /var/log/cron-execution.log"
```

## Upgrading

### Upgrading | Dockerfile

```bash
# Stop and remove current container.
docker stop mra
docker rm mra

# Pull latest release of code.
git pull

# Rebuild local Dockerfile image.
docker build --tag=mra .

# Execute `run` command.
```

## Development

For developing in this application, see [Development](docs/development/README.md).
