#!/bin/bash
# dev/site.sh: create (or reset) the local WordPress site used to develop the kit.
# Needs what dev/setup-arch.sh installs (PHP, MariaDB) plus WP-CLI as `wp`.
#
#   dev/site.sh            create the site if it doesn't exist yet
#   dev/site.sh --reset    delete it (files and database) and create it again with fresh demo content
#
# The database login is your Linux user through MariaDB's Unix socket, with no password. Another
# server (CI uses a database container) comes from WP_DEV_DB_HOST, WP_DEV_DB_USER and
# WP_DEV_DB_PASSWORD; wp-config.php then reads the password from the environment, so it is never
# stored or put on a command line.
#
# The theme and plugin are symlinked from this repository, so edits show up immediately.
set -euo pipefail
KIT=$(cd "$(dirname "$0")/.." && pwd)
SITE=${WP_DEV_SITE:-$HOME/Sites/blogkit}
DB=wp_blogkit
DB_USER=${WP_DEV_DB_USER:-$USER}
DB_HOST=${WP_DEV_DB_HOST:-localhost}
URL=http://127.0.0.1:8080
# The admin login lives outside the site folder: `wp server` hands out every file in that folder to
# anyone who can reach the port, whatever its permissions. (tests/e2e/utils.mjs reads it here too.)
CREDENTIALS=${XDG_STATE_HOME:-$HOME/.local/state}/wp-blog-kit/$(basename "$SITE").credentials
wp() { command wp --path="$SITE" "$@"; }

mkdir -p "$(dirname "$CREDENTIALS")" && chmod 700 "$(dirname "$CREDENTIALS")"
if [ -f "$SITE/.dev-credentials" ]; then # A site made before the login moved out of its folder.
  mv -f "$SITE/.dev-credentials" "$CREDENTIALS" && chmod 600 "$CREDENTIALS"
fi

if [ "${1:-}" = --reset ] && [ -f "$SITE/wp-config.php" ]; then
  wp db drop --yes || true
  rm -rf "$SITE"
fi
if [ -f "$SITE/wp-config.php" ]; then
  # A site made before this setting: its admin can't rewrite the repository's theme and plugin files.
  wp config has DISALLOW_FILE_EDIT 2>/dev/null || wp config set DISALLOW_FILE_EDIT true --raw --quiet
  echo "The site already exists at $SITE ($URL). Use --reset to start over."
  exit 0
fi

mkdir -p "$SITE"
# The .zip, not WP-CLI's default .tar.gz: PHP's PharData unpacks that with file paths cut at 100
# characters, which loses files in wp-includes/php-ai-client (WordPress 7).
wp core download https://wordpress.org/latest.zip --quiet
wp core verify-checksums --quiet
wp config create --dbname="$DB" --dbuser="$DB_USER" --dbpass='' --dbhost="$DB_HOST" --skip-check --quiet --extra-php <<'PHP'
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );          // wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', ! ( defined( 'WP_CLI' ) && WP_CLI ) ); // browser: on screen; WP-CLI: debug.log only
define( 'SCRIPT_DEBUG', true );          // unminified core scripts: clearer editor errors
define( 'WP_DEVELOPMENT_MODE', 'all' ); // no cached theme.json or pattern files: edits show at once
define( 'DISALLOW_FILE_EDIT', true );  // the theme and plugin are this repository's files
PHP
if [ -n "${WP_DEV_DB_PASSWORD:-}" ]; then
  wp config set DB_PASSWORD "getenv( 'WP_DEV_DB_PASSWORD' ) ?: ''" --raw --quiet
fi
wp db create
PASSWORD=$(openssl rand -base64 18 | tr -d '/+=')
wp core install --url="$URL" --title="Guro Notes (dev)" --admin_user=admin --admin_email=dev@example.com \
  --skip-email --quiet
# The password reaches WordPress on standard input: on a command line, other local users could read it.
printf '%s\n' "$PASSWORD" | wp eval 'wp_set_password( trim( (string) fgets( STDIN ) ), get_user_by( "login", "admin" )->ID );'
install -m 600 /dev/null "$CREDENTIALS"
printf 'URL=%s/wp-admin/\nUSER=admin\nPASSWORD=%s\n' "$URL" "$PASSWORD" > "$CREDENTIALS"

ln -s "$KIT/tala-blog" "$SITE/wp-content/themes/tala-blog"
ln -s "$KIT/tala-seo" "$SITE/wp-content/plugins/tala-seo"
wp plugin install plugin-check query-monitor --activate --quiet
wp plugin activate tala-seo --quiet
wp theme activate tala-blog --quiet
KIT_DEV_MEDIA="$KIT/dev/media/" wp eval-file "$KIT/dev/seed.php"
wp rewrite structure '/%postname%/' --quiet    # in its own process, so every taxonomy's rules are rebuilt

echo
echo "Local site ready: $URL  (start it with dev/serve.sh)"
echo "Admin login is in $CREDENTIALS"
