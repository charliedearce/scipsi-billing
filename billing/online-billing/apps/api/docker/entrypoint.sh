#!/bin/sh
set -eu

# Compose mounts the same development secret used by PostgreSQL. Loading it at
# process start keeps the password out of committed files and image layers.
if [ -r /run/secrets/postgres_password ]; then
    export DB_PASSWORD="$(cat /run/secrets/postgres_password)"
fi

# Private uploads survive image rebuilds via the api_private_storage volume.
mkdir -p /var/www/html/storage/app/private
chmod -R ug+rwX /var/www/html/storage/app/private || true

exec "$@"
