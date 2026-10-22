#!/bin/bash
# dev/phpcs.sh: check the theme and plugin against the WordPress Coding Standards (WPCS), using
# phpcs.xml.dist. Runs PHP_CodeSniffer on PHP 8.5 compiled to WebAssembly, so no local PHP or
# Composer is needed.
#   dev/phpcs.sh            report
#   dev/phpcs.sh --fix      apply automatic fixes (phpcbf), then report what's left
set -euo pipefail
cd "$(dirname "$0")/.."
T=dev/.tools
PHPCS_VERSION=3.13.6          # WPCS 3.x needs PHP_CodeSniffer 3.x
WPCS_VERSION=3.4.1
UTILS_VERSION=1.2.3
EXTRA_VERSION=1.5.1
PHPCS_KEY=5CB4F778BF9BC4FB67AE511D96E91A992CF22FF4   # official PHPCS release key (PHPCS README)

mkdir -p "$T/standards"
if [ ! -f "$T/phpcs.phar" ] || [ "$(cat "$T/.phpcs-version" 2>/dev/null)" != "$PHPCS_VERSION" ]; then
  for f in phpcs.phar phpcs.phar.asc phpcbf.phar phpcbf.phar.asc; do
    curl -fsSL -o "$T/$f" "https://github.com/PHPCSStandards/PHP_CodeSniffer/releases/download/$PHPCS_VERSION/$f"
  done
  GNUPGHOME=$(mktemp -d); export GNUPGHOME; trap 'rm -rf "$GNUPGHOME"' EXIT
  gpg --quiet --keyserver hkps://keys.openpgp.org --recv-keys "$PHPCS_KEY" 2>/dev/null
  for f in phpcs.phar phpcbf.phar; do
    gpg --status-fd 1 --verify "$T/$f.asc" "$T/$f" 2>/dev/null | grep -q "VALIDSIG $PHPCS_KEY" \
      || { echo "Signature check failed for $f; not running it." >&2; rm -f "$T/$f"; exit 1; }
  done
  echo "$PHPCS_VERSION" > "$T/.phpcs-version"
fi
fetch() {   # repo tag dir
  if [ "$(cat "$T/standards/$3/.version" 2>/dev/null)" != "$2" ]; then
    rm -rf "$T/standards/$3"; mkdir -p "$T/standards/$3"
    curl -fsSL "https://codeload.github.com/$1/tar.gz/refs/tags/$2" | tar -xz -C "$T/standards/$3" --strip-components=1
    echo "$2" > "$T/standards/$3/.version"
  fi
}
fetch WordPress/WordPress-Coding-Standards "$WPCS_VERSION" wpcs
fetch PHPCSStandards/PHPCSUtils "$UTILS_VERSION" phpcsutils
fetch PHPCSStandards/PHPCSExtra "$EXTRA_VERSION" phpcsextra

PATHS="$T/standards/wpcs,$T/standards/phpcsutils,$T/standards/phpcsextra"
run() { npx -y @php-wasm/cli@latest "$@" 2>&1 | grep -v -E '^npm notice|fgets\(\): Read of'; }
if [ "${1:-}" = --fix ]; then
  shift
  run "$T/phpcbf.phar" --runtime-set installed_paths "$PATHS" "$@" || true
fi
run "$T/phpcs.phar" --runtime-set installed_paths "$PATHS" "$@"
