#!/bin/bash
set -e
source /bd_build/buildconfig
set -x

$minimal_apt_get_install nginx nginx-common nginx-extras openssl

# Install nginx and configuration
cp /bd_build/nginx/nginx.conf /etc/nginx/nginx.conf
cp /bd_build/nginx/playcast.conf.tmpl /etc/nginx/playcast.conf.tmpl

mkdir -p /etc/nginx/playcast.conf.d/

# Create nginx temp dirs
mkdir -p /tmp/playcast_nginx_client /tmp/playcast_fastcgi_temp
touch /tmp/playcast_nginx_client/.tmpreaper
touch /tmp/playcast_fastcgi_temp/.tmpreaper
chmod -R 777 /tmp/playcast_*
