#!/bin/bash
# dev/plugin-check.sh: run WordPress's official Plugin Check (wordpress.org/plugins/plugin-check)
# against tala-seo, including its runtime and experimental checks.
#   dev/plugin-check.sh                 errors and warnings
#   dev/plugin-check.sh --format=json   any extra arguments go to `wp plugin check`
# It uses the local site from dev/site.sh when there is one, otherwise WordPress Playground (no local
# PHP needed). Exits 1 when Plugin Check finds anything, so `composer check` fails on real problems.
#
# One finding is ignored on purpose: "plugin_updater_detected" flags the `Update URI: false` header,
# which keeps this private plugin from being "updated" by an unrelated wordpress.org plugin of the
# same name. Only that code is ignored; the rest of the plugin_updater check still runs.
set -euo pipefail
cd "$(dirname "$0")/.."
SITE=${WP_DEV_SITE:-$HOME/Sites/blogkit}
ARGS=(plugin check tala-seo --include-experimental --ignore-codes=plugin_updater_detected
      --fields=line,column,type,code,message "$@")

report() {   # print the findings; fail when there are any
  local out
  out=$(cat)
  if grep -q '^FILE:' <<<"$out"; then
    printf '%s\n' "$out"
    exit 1
  fi
  echo "Plugin Check: no findings."
}

if command -v wp >/dev/null && [ -f "$SITE/wp-config.php" ]; then
  wp --path="$SITE" plugin is-active plugin-check || wp --path="$SITE" plugin install plugin-check --activate --quiet
  wp --path="$SITE" --require="$SITE/wp-content/plugins/plugin-check/cli.php" "${ARGS[@]}" 2>&1 | report
  exit 0
fi

TOOLS=dev/.tools
mkdir -p "$TOOLS"
if [ ! -f "$TOOLS/wp-cli.phar" ]; then
  curl -fsSL -o "$TOOLS/wp-cli.phar" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  curl -fsSL -o "$TOOLS/wp-cli.phar.sha512" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.sha512
  (cd "$TOOLS" && echo "$(cat wp-cli.phar.sha512)  wp-cli.phar" | sha512sum -c - >/dev/null)
fi
npx -y @wp-playground/cli@latest php --php=8.5 --verbosity=quiet \
  --mount="$PWD/tala-seo:/wordpress/wp-content/plugins/tala-seo" \
  --mount="$PWD/$TOOLS:/tools" \
  --blueprint="$PWD/dev/plugin-check-blueprint.json" \
  -- /tools/wp-cli.phar --path=/wordpress --require=/wordpress/wp-content/plugins/plugin-check/cli.php "${ARGS[@]}" 2>&1 \
  | grep -v -E '^npm notice|^(PHP )?Deprecated: .*(phar:///tools/wp-cli.phar|plugin-check/vendor)' | report
