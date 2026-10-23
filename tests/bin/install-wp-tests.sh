#!/bin/bash
# tests/bin/install-wp-tests.sh: what the PHPUnit tests need, set up once (`composer test` runs it).
#
#   1. A copy of WordPress for the tests in .cache/wordpress (WP_CORE_DIR), checked against
#      WordPress.org's checksums. Delete the folder to get the latest release.
#   2. An empty test database (WP_TESTS_DB_NAME, default wp_tests_blogkit). The tests empty and fill
#      it on every run, so it must never be a site's real database.
#
# The database login comes from WP_TESTS_DB_USER / WP_TESTS_DB_PASSWORD / WP_TESTS_DB_HOST, as in
# tests/phpunit/wp-tests-config.php. Without them: MariaDB through its Unix socket as your own user,
# as dev/setup-arch.sh sets up. The password is passed to the client in MYSQL_PWD, never on a
# command line.
set -euo pipefail
KIT=$(cd "$(dirname "$0")/../.." && pwd)
CORE=${WP_CORE_DIR:-$KIT/.cache/wordpress}
DB=${WP_TESTS_DB_NAME:-wp_tests_blogkit}
DB_USER=${WP_TESTS_DB_USER:-$USER}
DB_HOST=${WP_TESTS_DB_HOST:-localhost}

if [ ! -f "$CORE/wp-settings.php" ]; then
  mkdir -p "$CORE"
  # The .zip, not WP-CLI's default .tar.gz (see dev/site.sh).
  wp core download https://wordpress.org/latest.zip --path="$CORE" --quiet
  wp core verify-checksums --path="$CORE" --quiet
  echo "WordPress $(sed -n "s/^\$wp_version = '\(.*\)';/\1/p" "$CORE/wp-includes/version.php") for the tests: $CORE"
fi

case "$DB" in
  *[!A-Za-z0-9_]*) echo "WP_TESTS_DB_NAME may only hold letters, digits and underscores." >&2; exit 1 ;;
esac
client=(mariadb)
command -v mariadb >/dev/null || client=(mysql)
client+=(--user="$DB_USER")
[ "$DB_HOST" = localhost ] || client+=(--host="${DB_HOST%%:*}" --protocol=tcp)
[[ "$DB_HOST" == *:* ]] && client+=(--port="${DB_HOST##*:}")
[ -n "${WP_TESTS_DB_PASSWORD:-}" ] && export MYSQL_PWD="$WP_TESTS_DB_PASSWORD"
"${client[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$DB\`"
