#!/bin/sh
set -e

PORT="${PORT:-8080}"

a2dismod mpm_event mpm_worker || true
a2enmod mpm_prefork rewrite

sed -i "s|^Listen .*|Listen ${PORT}|g" /etc/apache2/ports.conf
sed -i "s|<VirtualHost \*:.*>|<VirtualHost *:${PORT}>|g" /etc/apache2/sites-available/000-default.conf

apache2ctl configtest

exec apache2-foreground
