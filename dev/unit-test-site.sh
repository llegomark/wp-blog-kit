#!/bin/bash
# dev/unit-test-site.sh: a second local site for WordPress's Theme Unit Test
# (https://make.wordpress.org/themes/handbook/review/theme-unit-test/): Tala Blog alone, without
# Tala SEO (a theme must work by itself), with WordPress's test content and the settings the
# handbook asks for. The demo site of dev/site.sh is left alone.
#
#   dev/unit-test-site.sh            create it in ~/Sites/themeunit
#   dev/unit-test-site.sh --reset    delete it (files and database) and create it again
#   dev/unit-test-site.sh --serve    serve it at http://127.0.0.1:8081 (stop with Ctrl+C)
#
# The test content: themeunittestdata.wordpress.xml, the handbook's file (classic content: image
# alignment, captions, galleries, HTML tags, comments, long titles, protected and untitled posts), and
# 64-block-test-data.xml, every core block. Both from github.com/WPTT/theme-unit-test at a fixed
# commit, checked against their SHA-256. Importing fetches their images from WordPress.com.
set -euo pipefail
KIT=$(cd "$(dirname "$0")/.." && pwd)
SITE=${WP_UNIT_SITE:-$HOME/Sites/themeunit}
DB=wp_themeunit
URL=http://127.0.0.1:8081
DATA=https://raw.githubusercontent.com/WPTT/theme-unit-test/b47acf980696897936265182cb684dca648476c7
declare -A SHA256=(
  [themeunittestdata.wordpress.xml]=457aace6ec93cf77369bbcc6158996e52da8798bd5e39c83d58dfab9b50d64fa
  [64-block-test-data.xml]=59e0eefd78087740f7ee98253666a7697ad57e1f2e7a80f6b8d74ae397072aea
)
# The admin login lives outside the site folder, which `wp server` serves file by file.
CREDENTIALS=${XDG_STATE_HOME:-$HOME/.local/state}/wp-blog-kit/$(basename "$SITE").credentials
wp() { command wp --path="$SITE" "$@"; }

mkdir -p "$(dirname "$CREDENTIALS")" && chmod 700 "$(dirname "$CREDENTIALS")"
if [ -f "$SITE/.dev-credentials" ]; then # A site made before the login moved out of its folder.
  mv -f "$SITE/.dev-credentials" "$CREDENTIALS" && chmod 600 "$CREDENTIALS"
fi
if [ "${1:-}" = --serve ]; then
  exec wp --path="$SITE" server --host=127.0.0.1 --port=8081
fi
if [ "${1:-}" = --reset ] && [ -f "$SITE/wp-config.php" ]; then
  wp db drop --yes || true
  rm -rf "$SITE"
fi
if [ -f "$SITE/wp-config.php" ]; then
  echo "The site already exists at $SITE ($URL). Use --reset to start over."
  exit 0
fi

mkdir -p "$SITE"
wp core download https://wordpress.org/latest.zip --quiet
wp core verify-checksums --quiet
wp config create --dbname="$DB" --dbuser="$USER" --dbpass='' --skip-check --quiet --extra-php <<'PHP'
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );          // wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', ! ( defined( 'WP_CLI' ) && WP_CLI ) );
define( 'SCRIPT_DEBUG', true );
define( 'WP_DEVELOPMENT_MODE', 'all' );
PHP
wp db create
PASSWORD=$(openssl rand -base64 18 | tr -d '/+=')
# A fairly long site title and an even longer tagline, as the handbook asks.
wp core install --url="$URL" --title="Theme Unit Test: a fairly long site title to see how it wraps" \
  --admin_user=admin --admin_password="$PASSWORD" --admin_email=dev@example.com --skip-email --quiet
wp option update blogdescription "And an even longer tagline, as the Theme Unit Test asks, to see how the header and footer handle it on a narrow phone screen" --quiet
install -m 600 /dev/null "$CREDENTIALS"
printf 'URL=%s/wp-admin/\nUSER=admin\nPASSWORD=%s\n' "$URL" "$PASSWORD" > "$CREDENTIALS"

ln -s "$KIT/tala-blog" "$SITE/wp-content/themes/tala-blog"
wp theme activate tala-blog --quiet
wp plugin install wordpress-importer --activate --quiet

mkdir -p "$SITE/.unit-test"
for file in "${!SHA256[@]}"; do
  curl -fsSL -o "$SITE/.unit-test/$file" "$DATA/$file"
  echo "${SHA256[$file]}  $SITE/.unit-test/$file" | sha256sum -c --quiet -
done
wp import "$SITE/.unit-test/themeunittestdata.wordpress.xml" --authors=create --quiet
wp import "$SITE/.unit-test/64-block-test-data.xml" --authors=create --quiet

# Settings → Reading, Discussion and Permalinks as the handbook asks: 5 posts per page, comments
# threaded 3 levels deep and paged 5 per page, and "Month and name" addresses.
wp option update posts_per_page 5 --quiet
wp option update thread_comments 1 --quiet
wp option update thread_comments_depth 3 --quiet
wp option update page_comments 1 --quiet
wp option update comments_per_page 5 --quiet
wp rewrite structure '/%year%/%monthnum%/%postname%/' --quiet

# The two menus: "Short Menu" with three pages, and "Long Menu" with every page, nested. The header
# shows the newest one (Long Menu); delete it to see the short one.
short=$(wp post list --post_type=page --post_status=publish --post_parent=0 --orderby=title --order=asc --posts_per_page=3 --format=ids)
links=''
for id in $short; do
  links+=$(printf '<!-- wp:navigation-link {"label":"%s","id":%d,"kind":"post-type","type":"page","url":"%s"} /-->' \
    "$(wp post get "$id" --field=post_title)" "$id" "$(wp post url "$id")")
done
wp post create --post_type=wp_navigation --post_status=publish --post_title="Short Menu" --post_content="$links" --quiet
wp post create --post_type=wp_navigation --post_status=publish --post_title="Long Menu" \
  --post_content='<!-- wp:page-list /-->' --quiet

echo "Theme Unit Test site: $SITE. Serve it with: dev/unit-test-site.sh --serve ($URL)"
