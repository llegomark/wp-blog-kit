#!/bin/bash
# build.sh: package the theme and plugin as installable zips in dist/.
#   ./build.sh            → dist/tala-blog-<version>.zip and dist/tala-seo-<version>.zip
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
        for f in sorted(files):
            if not f.startswith("."):
                path = os.path.join(root, f)
                z.write(path, path)
PY
  echo "dist/$dir-$v.zip"
}

pack tala-blog tala-blog/style.css
pack tala-seo tala-seo/tala-seo.php
