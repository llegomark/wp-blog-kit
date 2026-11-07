#!/bin/bash
# dev/wp-cli-phar.sh: sourced by dev/plugin-check.sh and dev/theme-check.sh when they run in WordPress
# Playground (no local site). It sets the pinned tool versions and puts a checked WP-CLI in dev/.tools.
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
