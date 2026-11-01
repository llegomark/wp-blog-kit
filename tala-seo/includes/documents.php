<?php
/**
 * PDFs and the articles that present them.
 *
 * A PDF that search engines index on its own sends readers straight to the file, past the article
 * that presents it. Redirecting the file to the article loses it from search altogether (the
 * article isn't the same document). Google's answer for documents that aren't HTML is a
 * rel="canonical" HTTP header on the file that names the preferred page: the file stays available
 * to everyone, and search engines can show the article in its place. The web server adds that
 * header, from the map `wp tala-seo pdf-canonicals` writes.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Each PDF in the uploads folder that a published, indexable post or page links to or embeds, with
 * that article's address: the file's path as the web server sees it (decoded) => the article's URL.
 *
 * When several articles present a PDF, its article is the one it was uploaded to (the attachment's
 * parent) if that one presents it too; else the one that presents the fewest PDFs (an article
 * about that document rather than a list of many), posts before pages, the earliest first.
 * Articles hidden from search engines, redirected, or behind a password are never chosen, and
 * files that aren't in the uploads folder are left out.
 *
 * @return array<string, string> Sorted by path.
 */
function tala_seo_pdf_canonicals() {
	$uploads = wp_get_upload_dir();
	$base    = rtrim( (string) wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ), '/' );
	$hosts   = tala_seo_site_hosts();
	$pattern = '#(?:(?:https?:)?//([a-z0-9.-]+(?::\d+)?))?(' . preg_quote( $base, '#' ) . '/[^\s"\'<>?\#]+?\.pdf)(?![a-z0-9])#i';

	$ids = get_posts(
		array(
			'post_type'      => tala_seo_post_types(),
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	$presented = array(); // PDF path => IDs of the articles that present it.
	$counts    = array(); // Article ID => how many PDFs it presents.
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( tala_seo_post_noindex( $id ) || '' !== tala_seo_post_redirect( $id ) ) {
			continue;
		}
		// Block attributes store addresses as JSON, with escaped slashes.
		$content = str_replace( '\\/', '/', (string) get_post_field( 'post_content', $id, 'raw' ) );
		if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			continue;
		}
		$found = array();
		foreach ( $matches as $match ) {
			if ( '' !== $match[1] && ! in_array( strtolower( $match[1] ), $hosts, true ) ) {
				continue;
			}
			$path = rawurldecode( $match[2] );
			if ( str_contains( $path, '/../' ) || ! is_file( $uploads['basedir'] . substr( $path, strlen( $base ) ) ) ) {
				continue;
			}
			$found[ $path ] = true;
		}
		foreach ( array_keys( $found ) as $path ) {
			$presented[ $path ][] = $id;
		}
		if ( $found ) {
			$counts[ $id ] = count( $found );
		}
	}

	$canonicals = array();
	foreach ( $presented as $path => $articles ) {
		$parent = tala_seo_pdf_parent( $path, $base );
		usort(
			$articles,
			static fn( $a, $b ) => tala_seo_pdf_article_rank( $a, $parent, $counts ) <=> tala_seo_pdf_article_rank( $b, $parent, $counts )
		);
		$url = get_permalink( $articles[0] );
		if ( $url ) {
			$canonicals[ $path ] = $url;
		}
	}
	ksort( $canonicals, SORT_STRING );
	return $canonicals;
}

/**
 * How well an article suits a PDF, for sorting (lowest first): the post it was uploaded to, then the
 * fewest PDFs presented, posts before pages, the earliest.
 *
 * @param int             $id          Article ID.
 * @param int             $uploaded_to The PDF's attachment parent, or 0.
 * @param array<int, int> $counts      Article ID => PDFs it presents.
 * @return array{int, int, int, string, int}
 */
function tala_seo_pdf_article_rank( $id, $uploaded_to, $counts ) {
	$is_parent = $uploaded_to === $id;
	$is_post   = 'post' === get_post_type( $id );
	return array( $is_parent ? 0 : 1, $counts[ $id ] ?? 0, $is_post ? 0 : 1, (string) get_post_field( 'post_date_gmt', $id ), $id );
}

/**
 * The site's own host names: the home address's, with and without "www.".
 *
 * @return string[]
 */
function tala_seo_site_hosts() {
	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$port = wp_parse_url( home_url(), PHP_URL_PORT );
	$bare = (string) preg_replace( '/^www\./', '', $host );
	$list = array( $bare, 'www.' . $bare );
	if ( $port ) {
		$list[] = $host . ':' . $port;
	}
	return $list;
}

/**
 * The post a PDF was uploaded to (its attachment's parent), or 0.
 *
 * @param string $path The file's path on the site, such as /wp-content/uploads/2021/10/a.pdf.
 * @param string $base The uploads folder's path on the site.
 * @return int
 */
function tala_seo_pdf_parent( $path, $base ) {
	$relative = ltrim( substr( $path, strlen( $base ) ), '/' );
	$found    = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'application/pdf',
			'posts_per_page' => 1,
			'fields'         => 'id=>parent',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- once per PDF, from WP-CLI only.
			'meta_query'     => array(
				array(
					'key'   => '_wp_attached_file',
					'value' => $relative,
				),
			),
		)
	);
	return $found ? (int) reset( $found ) : 0;
}

/**
 * The PDF map as an nginx map block: $variable is the Link header naming the file's article as its
 * canonical page, and empty for other files (nginx then sends no Link header).
 *
 * @param array<string, string> $canonicals Path => article URL.
 * @param string                $variable   The nginx variable's name, without "$".
 * @return string
 */
function tala_seo_pdf_canonicals_nginx( $canonicals, $variable ) {
	$lines = array();
	foreach ( $canonicals as $path => $url ) {
		// A quote, a backslash, a "$" (an nginx variable) or a control character can't be written
		// safely in nginx's quoted strings; WordPress's own file names and permalinks have none.
		if ( preg_match( '/["\'\\\\$\x00-\x1f\x7f]/', $path . $url ) ) {
			continue;
		}
		$lines[] = sprintf( "    \"%s\" '<%s>; rel=\"canonical\"';", $path, $url );
	}
	return '# The canonical article of each PDF, from `wp tala-seo pdf-canonicals` (' . count( $lines ) . " PDFs).\n"
		. "# Use it in the site's PDF location: add_header Link \$$variable always;\n"
		. "map \$uri \$$variable {\n    default \"\";\n" . implode( "\n", $lines ) . ( $lines ? "\n" : '' ) . "}\n";
}
