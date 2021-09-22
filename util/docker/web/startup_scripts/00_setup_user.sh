#!/bin/bash

PUID=${PUID:-1000}
PGID=${PGID:-1000}

groupmod -o -g "$PGID" playcast
usermod -o -u "$PUID" playcast

echo "Docker 'playcast' User UID: $(id -u playcast)"
echo "Docker 'playcast' User GID: $(id -g playcast)"
