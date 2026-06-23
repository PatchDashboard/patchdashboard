#!/bin/sh
set -e

if [ -f /database/migrate.php ]; then
    php /database/migrate.php
fi

exec php-fpm -F
