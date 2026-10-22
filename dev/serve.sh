#!/bin/bash
# dev/serve.sh: serve the local site (dev/site.sh) at http://127.0.0.1:8080 with PHP's built-in
# server, reachable from this laptop only. Ctrl-C stops it.
set -euo pipefail
SITE=${WP_DEV_SITE:-$HOME/Sites/blogkit}
# Several workers, so the block editor's parallel requests don't queue behind each other.
export PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-4}
exec wp --path="$SITE" server --host=127.0.0.1 --port=8080
