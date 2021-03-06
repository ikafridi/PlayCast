#!/bin/bash

bool() {
  case "$1" in
  Y* | y* | true | TRUE | 1) return 0 ;;
  esac
  return 1
}

ENABLE_REDIS=${ENABLE_REDIS:-false}
if bool "$ENABLE_REDIS"; then
  ENABLE_REDIS=true
else
  ENABLE_REDIS=false
fi

export ENABLE_REDIS

# Copy the nginx template to its destination.
dockerize -template "/etc/nginx/playcast.conf.tmpl:/etc/nginx/conf.d/playcast.conf"
