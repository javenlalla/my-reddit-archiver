# My Reddit Archiver

Archive Saved posts under your Reddit account.

> Note: This project is currently under active development. There will be data structure and/or bc-breaking changes until the first official release is pushed.

- [My Reddit Archiver](#my-reddit-archiver)
  - [Prerequisites](#prerequisites)
    - [Create Reddit Client ID And Secret](#create-reddit-client-id-and-secret)
      - [Limitations](#limitations)
        - [2FA](#2fa)
  - [Setup](#setup)
    - [Environment Variables](#environment-variables)
    - [docker run](#docker-run)
    - [docker-compose](#docker-compose)
  - [Execute Sync](#execute-sync)
  - [Logging](#logging)
    - [Container Logs](#container-logs)
    - [Cron Logs](#cron-logs)
  - [Updating](#updating)
    - [Update | docker run](#update--docker-run)
    - [Update | docker-compose](#update--docker-compose)
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

The application can be configured to run via `docker-compose` or `docker run`. See the following sections on how to spin up the application with either of these methods.

### Environment Variables

The first step is to set the `Environment Variables` required by the application. See the table below for an overview of the required variables.

| Environment Variable  | Default              | Description                                      |
| --------------------- | -------------------- | ------------------------------------------------ |
| REDDIT_USERNAME       |                      | Your Reddit username.                            |
| REDDIT_PASSWORD       |                      | Your Reddit password.                            |
| REDDIT_CLIENT_ID      |                      | The Client ID of the Reddit App you created.     |
| REDDIT_CLIENT_SECRET  |                      | The Client Secret of the Reddit App you created. |

For convenience, an `.env.sample` file is provided in the root of this repository. Copy that file to `.env` and update the placeholder values to real values.

### docker run

If the `docker run` method is preferred for running the application, proceed with this section. If the `docker-compose` method is preferred, skip to [docker-compose(#docker-compose)].

Once the `.env` file has been created and configured, start the application with the following command.

Note: The volume mount is needed for backup/persistent storage of downloaded media assets from Reddit Posts.

```bash
  docker run -d \
  --env-file=.env \
  --volume </path/to/media>:/var/www/mra/public/r-media \
  --volume </path/to/database-folder>:/var/www/mra/database \
  -p 3580:80 \
  --name mra \
  javenlalla/mra
```

Notes:

- Update the host port as necessary

### docker-compose

If the `docker-compose` method is preferred for running the application, proceed with the following steps.

1. Create a `docker-compose.yml`:

    ```yaml
    version: '3.9'

    services:
      mra:
        container_name: mra
        image: javenlalla/mra
        volumes:
          - </path/to/media-folder>:/var/www/mra/public/r-media
          - </path/to/database-folder>:/database
        environment:
          REDDIT_USERNAME: MyRedditUsername
          REDDIT_PASSWORD: "MyRedditPassword"
          REDDIT_CLIENT_ID: "MyAppClientID"
          REDDIT_CLIENT_SECRET: "MyAppClientSecret"
        ports:
          - "3580:80"
    ```

2. Adjust the `volumes` paths, `environment` variables, and `port` as needed.
3. Start application.

    ```bash
    docker-compose up -d
    ```

## Execute Sync

Once the application is configured and running, use the following command to execute the syncing of the Reddit profile's `Saved` Posts down to the local system:

```bash
docker exec -it mra ./sync-api
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

## Updating

### Update | docker run

1. Stop current container.

    ```bash
    docker stop mra
    ```

1. Pull latest image.

    ```bash
    docker pull javenlalla/mra
    ```

1. Start application as previously set up with this method. See [docker run](#docker-run) for more information.

### Update | docker-compose

1. Stop current container.

    ```bash
    docker stop mra
    ```

1. Pull latest image.

    ```bash
    docker pull javenlalla/mra
    ```

1. Remove the current container and start it again with the latest image.

    ```bash
    docker-compose up -d --force-recreate --build
    ```

## Development

For developing in this application, see [Development](development/README.md).
