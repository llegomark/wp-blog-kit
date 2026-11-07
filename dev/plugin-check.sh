#!/bin/bash
# dev/plugin-check.sh: run WordPress's official Plugin Check (wordpress.org/plugins/plugin-check)
# against tala-seo, tala-ads and tala-guard, including its runtime and experimental checks.
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
PLUGINS=(tala-seo tala-ads tala-guard)
ARGS=(--include-experimental --ignore-codes=plugin_updater_detected
      --fields=line,column,type,code,message "$@")

report() {   # print one plugin's findings; fail when there are any
  local out
  out=$(cat)
  if grep -q '^FILE:' <<<"$out"; then
    printf '%s\n' "$out"
    exit 1
  fi
  echo "Plugin Check, $1: no findings."
}

if command -v wp >/dev/null && [ -f "$SITE/wp-config.php" ]; then
  wp --path="$SITE" plugin is-active plugin-check || wp --path="$SITE" plugin install plugin-check --activate --quiet
  for plugin in "${PLUGINS[@]}"; do
    wp --path="$SITE" --require="$SITE/wp-content/plugins/plugin-check/cli.php" plugin check "$plugin" "${ARGS[@]}" 2>&1 | report "$plugin"
  done
  exit 0
fi

# The pinned WP-CLI and Playground releases, shared with dev/theme-check.sh.
. dev/wp-cli-phar.sh
for plugin in "${PLUGINS[@]}"; do
  npx -y "@wp-playground/cli@$PLAYGROUND_VERSION" php --php=8.5 --verbosity=quiet \
    --mount="$PWD/tala-seo:/wordpress/wp-content/plugins/tala-seo" \
    --mount="$PWD/tala-ads:/wordpress/wp-content/plugins/tala-ads" \
    --mount="$PWD/tala-guard:/wordpress/wp-content/plugins/tala-guard" \
    --mount="$PWD/$TOOLS:/tools" \
    --blueprint="$PWD/dev/plugin-check-blueprint.json" \
    -- /tools/wp-cli.phar --path=/wordpress --require=/wordpress/wp-content/plugins/plugin-check/cli.php plugin check "$plugin" "${ARGS[@]}" 2>&1 \
    | grep -v -E '^npm notice|^(PHP )?Deprecated: .*(phar:///tools/wp-cli.phar|plugin-check/vendor)' | report "$plugin"
done
