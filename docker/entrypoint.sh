#!/bin/sh
set -e

PORT="${PORT:-8080}"
export PORT

php artisan config:cache
php artisan route:cache

envsubst '${PORT}' < /etc/nginx/templates/app.conf.template > /etc/nginx/conf.d/app.conf

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
