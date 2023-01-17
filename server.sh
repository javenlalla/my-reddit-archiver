#!/usr/bin/env bash
#
# Usage
#   - ./server.sh start-dev | Start the dev container(s) for local development environment.
#   - ./server.sh stop-dev | Stop dev container(s).
#   - ./server.sh connect-dev | Hook into the dev container.

action=$1

if [[ "${action}" = "start-dev" ]]; then
    docker-compose -f docker-compose.development.yml up -d
elif [[ "${action}" = "stop-dev" ]]; then
    docker-compose -f docker-compose.development.yml stop
elif [[ "${action}" = "connect-dev" ]]; then
    docker exec -it mra-dev bash
elif [[ "${action}" = "yarn-watch" ]]; then
    docker exec -it mra-dev yarn watch
else
  echo "Invalid `action` argument provided. See usage details."
  exit 1
fi
