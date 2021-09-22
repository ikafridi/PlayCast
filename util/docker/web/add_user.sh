#!/bin/bash
set -e
source /bd_build/buildconfig
set -x

$minimal_apt_get_install sudo

# Workaround for sudo errors in containers, see: https://github.com/sudo-project/sudo/issues/42
echo "Set disable_coredump false" >> /etc/sudo.conf

adduser --home /var/playcast --disabled-password --gecos "" playcast

usermod -aG docker_env playcast
usermod -aG www-data playcast

mkdir -p /var/playcast/www /var/playcast/backups /var/playcast/www_tmp \
  /var/playcast/uploads /var/playcast/geoip /var/playcast/dbip

chown -R playcast:playcast /var/playcast
chmod -R 777 /var/playcast/www_tmp

echo 'playcast ALL=(ALL) NOPASSWD: ALL' >> /etc/sudoers
