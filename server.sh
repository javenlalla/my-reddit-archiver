#!/usr/bin/env bash
#
# Usage
#   - ./server.sh start-dev | Start dev server for local development environment.
#   - ./server.sh stop-dev | Stop dev server.

action=$1

if [[ "${action}" = "start-dev" ]]; then
    docker-compose -f docker-compose.development.yml up -d
elif [[ "${action}" = "stop-dev" ]]; then
    docker-compose -f docker-compose.development.yml stop
else
  echo "Invalid `action` argument provided. See usage details."
  exit 1
fi
