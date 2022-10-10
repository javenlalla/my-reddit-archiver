# My Reddit Archiver

Archive Saved posts under your Reddit account.

- [My Reddit Archiver](#my-reddit-archiver)
  - [Setup](#setup)
    - [Create Reddit Client ID And Secret](#create-reddit-client-id-and-secret)
      - [Limitations](#limitations)
    - [Configure Application](#configure-application)
    - [Start Application](#start-application)
  - [Development](#development)

## Setup

### Create Reddit Client ID And Secret

Firstly, a Reddit Client ID and Client Secret must be generated in order to configure the application.

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

Does not work with 2FA.

### Configure Application

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

## Development

For developing in this application, see [Development](docs/development/README.md).
