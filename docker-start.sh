#!/bin/bash

export WWWGROUP=$(id -g)
docker compose up -d
# for building: docker compose up --build --no-deps --force-recreate