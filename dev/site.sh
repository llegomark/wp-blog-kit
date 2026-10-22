#!/bin/bash
# dev/site.sh: create (or reset) the local WordPress site used to develop the kit.
# Needs what dev/setup-arch.sh installs (PHP, MariaDB) plus WP-CLI as `wp`.
#
#   dev/site.sh            create the site if it doesn't exist yet
#   dev/site.sh --reset    delete it (files and database) and create it again with fresh demo content
#
# The theme and plugin are symlinked from this repository, so edits show up immediately.
set -euo pipefail
KIT=$(cd "$(dirname "$0")/.." && pwd)
SITE=${WP_DEV_SITE:-$HOME/Sites/blogkit}
DB=wp_blogkit
URL=http://127.0.0.1:8080
wp() { command wp --path="$SITE" "$@"; }

if [ "${1:-}" = --reset ] && [ -f "$SITE/wp-config.php" ]; then
  wp db drop --yes || true
  rm -rf "$SITE"
fi
if [ -f "$SITE/wp-config.php" ]; then
  echo "The site already exists at $SITE ($URL). Use --reset to start over."
  exit 0
fi

mkdir -p "$SITE"
# The .zip, not WP-CLI's default .tar.gz: PHP's PharData unpacks that with file paths cut at 100
# characters, which loses files in wp-includes/php-ai-client (WordPress 7).
wp core download https://wordpress.org/latest.zip --quiet
wp core verify-checksums --quiet
# Database login through the Unix socket as your Linux user: no password exists or is stored.
wp config create --dbname="$DB" --dbuser="$USER" --dbpass='' --dbhost=localhost --skip-check --quiet --extra-php <<'PHP'
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );          // wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', ! ( defined( 'WP_CLI' ) && WP_CLI ) ); // browser: on screen; WP-CLI: debug.log only
define( 'SCRIPT_DEBUG', true );          // unminified core scripts: clearer editor errors
PHP
wp db create
PASSWORD=$(openssl rand -base64 18 | tr -d '/+=')
wp core install --url="$URL" --title="Guro Notes (dev)" --admin_user=admin --admin_password="$PASSWORD" \
  --admin_email=dev@example.com --skip-email --quiet
install -m 600 /dev/null "$SITE/.dev-credentials"
printf 'URL=%s/wp-admin/\nUSER=admin\nPASSWORD=%s\n' "$URL" "$PASSWORD" > "$SITE/.dev-credentials"

ln -s "$KIT/tala-blog" "$SITE/wp-content/themes/tala-blog"
ln -s "$KIT/tala-seo" "$SITE/wp-content/plugins/tala-seo"
wp plugin install plugin-check query-monitor --activate --quiet
wp plugin activate tala-seo --quiet
wp theme activate tala-blog --quiet
KIT_DEV_MEDIA="$KIT/dev/media/" wp eval-file "$KIT/dev/seed.php"
wp rewrite structure '/%postname%/' --quiet    # in its own process, so every taxonomy's rules are rebuilt

echo
echo "Local site ready: $URL  (start it with dev/serve.sh)"
echo "Admin login is in $SITE/.dev-credentials"
