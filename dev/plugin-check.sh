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

TOOLS=dev/.tools
# A fixed WP-CLI release, checked against the SHA-512 recorded here (a checksum fetched next to the
# file would only catch a broken download). This one carries a good signature from WP-CLI's release
# key 63AF7AA15067C05616FDDD88A3A2E8F226F0BC06.
WP_CLI_VERSION=2.12.0
WP_CLI_SHA512=be928f6b8ca1e8dfb9d2f4b75a13aa4aee0896f8a9a0a1c45cd5d2c98605e6172e6d014dda2e27f88c98befc16c040cbb2bd1bfa121510ea5cdf5f6a30fe8832
PLAYGROUND_VERSION=3.1.60   # @wp-playground/cli: a fixed version, the same as @php-wasm/cli in dev/phpcs.sh
mkdir -p "$TOOLS"
if ! echo "$WP_CLI_SHA512  $TOOLS/wp-cli.phar" | sha512sum -c --quiet - 2>/dev/null; then
  curl -fsSL -o "$TOOLS/wp-cli.phar" "https://github.com/wp-cli/wp-cli/releases/download/v$WP_CLI_VERSION/wp-cli-$WP_CLI_VERSION.phar"
  echo "$WP_CLI_SHA512  $TOOLS/wp-cli.phar" | sha512sum -c --quiet - \
    || { echo "Checksum failed for WP-CLI $WP_CLI_VERSION; not running it." >&2; rm -f "$TOOLS/wp-cli.phar"; exit 1; }
fi
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
