#!/bin/sh
set -eu

mkdir -p \
    storage/app/private/documents/uploads \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

php artisan package:discover --ansi >/dev/null

exec "$@"
