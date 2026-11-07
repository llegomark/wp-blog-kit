#!/bin/bash
# dev/theme-check.sh: run WordPress.org's official Theme Check (wordpress.org/plugins/theme-check), the
# tool the theme review team uses, against tala-blog.
#   dev/theme-check.sh
# It uses the local site from dev/site.sh when there is one, otherwise WordPress Playground (no local
# PHP needed), like dev/plugin-check.sh. Exits 1 when Theme Check reports anything above INFO, so
# `composer check` fails on real problems.
#
# One finding is ignored on purpose: the REQUIRED "Update URI: is found" notice flags the
# `Update URI: false` header, which keeps this private theme from ever being "updated" by an unrelated
# wordpress.org theme of the same name. Only that notice is ignored; INFO notes are not failures.
set -euo pipefail
cd "$(dirname "$0")/.."
SITE=${WP_DEV_SITE:-$HOME/Sites/blogkit}
THEME=tala-blog

report() {   # read Theme Check's JSON; print and fail on anything above INFO but the Update URI notice
  node -e '
    let text = "";
    process.stdin.on( "data", ( chunk ) => ( text += chunk ) ).on( "end", () => {
      let found;
      try {
        found = JSON.parse( text.slice( text.indexOf( "[" ), text.lastIndexOf( "]" ) + 1 ) );
      } catch {
        console.error( "Theme Check gave no JSON:\n" + text );
        process.exit( 1 );
      }
      const deliberate = ( f ) => "REQUIRED" === f.type && f.value.startsWith( "Update URI: is found" );
      const problems = found.filter( ( f ) => "INFO" !== f.type && ! deliberate( f ) );
      for ( const f of problems ) {
        console.log( f.type + ": " + f.value );
      }
      if ( problems.length ) {
        process.exit( 1 );
      }
      console.log( "Theme Check, " + process.argv[ 1 ] + ": no findings." );
    } );
  ' "$THEME"
}

if command -v wp >/dev/null && [ -f "$SITE/wp-config.php" ]; then
  wp --path="$SITE" plugin is-active theme-check 2>/dev/null || wp --path="$SITE" plugin install theme-check --activate --quiet
  # Theme Check exits 1 on any REQUIRED notice, the deliberate one included: its JSON decides instead.
  { wp --path="$SITE" theme-check run "$THEME" --format=json 2> >(grep -v -E '^(PHP )?Deprecated: .*phar://' >&2) || true; } | report
  exit 0
fi

# The pinned WP-CLI and Playground releases, shared with dev/plugin-check.sh.
. dev/wp-cli-phar.sh
{ npx -y "@wp-playground/cli@$PLAYGROUND_VERSION" php --php=8.5 --verbosity=quiet \
  --mount="$PWD/$THEME:/wordpress/wp-content/themes/$THEME" \
  --mount="$PWD/$TOOLS:/tools" \
  --blueprint="$PWD/dev/theme-check-blueprint.json" \
  -- /tools/wp-cli.phar --path=/wordpress theme-check run "$THEME" --format=json 2> >(grep -v -E '^npm notice|^(PHP )?Deprecated: .*phar:///tools/wp-cli.phar' >&2) \
  || true; } | report
