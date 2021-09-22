#!/bin/bash
set -e
source /bd_build/buildconfig
set -x

add-apt-repository -y ppa:sftpgo/sftpgo
apt-get update

$minimal_apt_get_install sftpgo

mkdir -p /var/playcast/sftpgo/persist /var/playcast/sftpgo/backups

cp /bd_build/sftpgo/sftpgo.json /var/playcast/sftpgo/sftpgo.json

touch /var/playcast/sftpgo/sftpgo.db
chown -R playcast:playcast /var/playcast/sftpgo
