#!/bin/sh
# Container start: fix ownership of the persistent data, then serve as www-data.
#
# data/ (goats.json) and goats/ (the GIFs) are Coolify persistent volumes, not
# part of the image. Under nixpacks the site ran as root, so everything already
# in them is owned by root. PHP now runs as www-data, which can read those files
# but could not write them, and the admin page writes both when adding or
# deleting a goat. Only the owner changes; no file contents are touched. Files
# that already belong to www-data are skipped, so later starts do almost nothing.
set -e

for dir in /app/data /app/goats; do
    mkdir -p "$dir"
    find "$dir" ! -user www-data -exec chown www-data:www-data {} +
done

# router.php keeps the repository's internal files off the site.
exec setpriv --reuid=www-data --regid=www-data --init-groups \
    php -S "0.0.0.0:${PORT}" -t /app /app/router.php
