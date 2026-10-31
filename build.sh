#!/bin/bash
# build.sh: package the theme and plugin as installable zips in dist/.
#   ./build.sh            → dist/tala-blog-<version>.zip, dist/tala-seo-<version>.zip and
#                           dist/tala-ads-<version>.zip
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist

version() { grep -m1 -E "^\s*\*?\s*Version:" "$1" | sed -E 's/.*Version:\s*//; s/\s+$//'; }

pack() {   # folder, header file
  local dir=$1 v
  v=$(version "$2")
  rm -f "dist/$dir-$v.zip"
  python3 -I - "$dir" "dist/$dir-$v.zip" <<'PY'
import os, sys, zipfile
src, out = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk(src):
        dirs[:] = sorted(d for d in dirs if not d.startswith("."))
        for name in dirs + sorted(f for f in files if not f.startswith(".")):
            path = os.path.join(root, name)
            # A link would put whatever it points to into the zip (a file outside the folder, or a
            # secret of the machine building it): only real files are packed.
            if os.path.islink(path):
                sys.exit(f"build.sh: {path} is a symbolic link; the zip takes real files only.")
            if os.path.isfile(path):
                z.write(path, path)
PY
  echo "dist/$dir-$v.zip"
}

pack tala-blog tala-blog/style.css
pack tala-seo tala-seo/tala-seo.php
pack tala-ads tala-ads/tala-ads.php
