#!/usr/bin/env bash

cp sample.env .env
cp playcast.dev.env playcast.env
cp docker-compose.sample.yml docker-compose.yml
cp .devcontainer/docker-compose.override.yml docker-compose.override.yml
docker-compose build web
docker-compose run --rm --user=playcast web playcast_install
docker-compose up -d
