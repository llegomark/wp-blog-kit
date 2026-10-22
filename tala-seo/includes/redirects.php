<?php
/**
 * Redirects for pages and term archives (categories, tags) whose address changed.
 *
 * WordPress only redirects posts whose slug changed (wp_old_slug_redirect()); a page or category
 * that is renamed or moved simply starts returning 404 at its old address, and links and search
 * results pointing there break. Here every published page and public term remembers the addresses
 * it had, and a request that would end in a 404 at an old address gets a 301 to the current one.
 * Children move with their parent, so an old address also matches as a prefix:
 * old-parent/child → new-parent/child, category/old/page/2 → category/new/page/2.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * How many old addresses one page or term keeps.
 */
const TALA_SEO_OLD_PATHS_KEPT = 20;

/**
 * The path of a URL on this site, without the home path or surrounding slashes ("parent/child").
 *
 * @param string $url URL.
 * @return string
 */
function tala_seo_site_path( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $home && str_starts_with( $path, $home ) ) {
		$path = substr( $path, strlen( $home ) );
	}
	return trim( $path, '/' );
}

/**
 * Hierarchical post types with SEO fields (pages): the ones WordPress doesn't redirect itself.
 *
 * @return string[]
 */
function tala_seo_redirect_post_types() {
	return array_values( array_filter( tala_seo_post_types(), 'is_post_type_hierarchical' ) );
}

/**
 * Public taxonomies with their own archive pages.
 *
 * @return string[]
 */
function tala_seo_redirect_taxonomies() {
	$taxonomies = get_taxonomies(
		array(
			'public'             => true,
			'publicly_queryable' => true,
		)
	);
	return array_values( array_diff( $taxonomies, array( 'post_format' ) ) );
}

/**
 * Records that a page or term moved from one address to another.
 *
 * Each old address belongs to one page or term at a time (the latest to leave it), and an address
 * that becomes current again stops being an old one.
 *
 * @param string $meta_type 'post' or 'term'.
 * @param int    $id        Post or term ID.
 * @param string $old_url   Address before the change.
 * @param string $new_url   Address after the change.
 */
function tala_seo_remember_move( $meta_type, $id, $old_url, $new_url ): void {
	$old = tala_seo_site_path( $old_url );
	$new = tala_seo_site_path( $new_url );
	if ( '' === $old || $old === $new ) {
		return;
	}
	foreach ( array( 'post', 'term' ) as $type ) {
		delete_metadata( $type, 0, TALA_SEO_OLD_PATH, $old, true );
	}
	delete_metadata( $meta_type, $id, TALA_SEO_OLD_PATH, $new );
	add_metadata( $meta_type, $id, TALA_SEO_OLD_PATH, $old );

	// Oldest first (meta is read in the order it was added): drop what is over the limit.
	$paths = array_map( 'strval', (array) get_metadata( $meta_type, $id, TALA_SEO_OLD_PATH, false ) );
	foreach ( array_slice( $paths, 0, max( 0, count( $paths ) - TALA_SEO_OLD_PATHS_KEPT ) ) as $stale ) {
		delete_metadata( $meta_type, $id, TALA_SEO_OLD_PATH, $stale );
	}
}

add_action( 'post_updated', 'tala_seo_page_moved', 10, 3 );

/**
 * A published page whose address changed (new slug or new parent).
 *
 * @param int     $post_id     Post ID.
 * @param WP_Post $post_after  The page as saved.
 * @param WP_Post $post_before The page before the save.
 */
function tala_seo_page_moved( $post_id, $post_after, $post_before ): void {
	if ( 'publish' !== $post_before->post_status || 'publish' !== $post_after->post_status
		|| ! in_array( $post_after->post_type, tala_seo_redirect_post_types(), true ) ) {
		return;
	}
	// get_permalink() builds the address from the object it gets, so $post_before gives the old one.
	tala_seo_remember_move( 'post', (int) $post_id, (string) get_permalink( $post_before ), (string) get_permalink( $post_after ) );
}

add_action( 'edit_terms', 'tala_seo_term_before_edit', 10, 2 );

/**
 * Notes a term's address just before it is saved.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy.
 */
function tala_seo_term_before_edit( $term_id, $taxonomy ): void {
	if ( in_array( $taxonomy, tala_seo_redirect_taxonomies(), true ) ) {
		$link = get_term_link( (int) $term_id, $taxonomy );
		tala_seo_term_link_before( (int) $term_id, is_string( $link ) ? $link : '' );
	}
}

/**
 * Keeps term addresses between edit_terms (before the save) and edited_term (after it).
 *
 * @param int         $term_id Term ID.
 * @param string|null $link    Address to remember, or null to read it.
 * @return string
 */
function tala_seo_term_link_before( $term_id, $link = null ) {
	static $links = array();
	if ( null !== $link ) {
		$links[ $term_id ] = $link;
	}
	return $links[ $term_id ] ?? '';
}

add_action( 'edited_term', 'tala_seo_term_moved', 10, 3 );

/**
 * A term whose address changed (new slug or new parent).
 *
 * @param int    $term_id  Term ID.
 * @param int    $tt_id    Term taxonomy ID.
 * @param string $taxonomy Taxonomy.
 */
function tala_seo_term_moved( $term_id, $tt_id, $taxonomy ): void {
	$old = tala_seo_term_link_before( (int) $term_id );
	$new = get_term_link( (int) $term_id, $taxonomy );
	if ( '' !== $old && is_string( $new ) ) {
		tala_seo_remember_move( 'term', (int) $term_id, $old, $new );
	}
}

// Before redirect_canonical() (priority 10), which would otherwise "guess" a post with a similar
// name for the old address.
add_action( 'template_redirect', 'tala_seo_redirect_moved', 9 );

/**
 * Sends a request for an old address to the page or term's current address (301).
 */
function tala_seo_redirect_moved(): void {
	global $wp;
	if ( ! is_404() || ! ( $wp instanceof WP ) ) {
		return;
	}
	$request = trim( (string) $wp->request, '/' );
	if ( '' === $request ) {
		return;
	}
	// "a/b/c" is either an old address itself or lies below one: try a/b/c, then a/b, then a.
	$parts      = explode( '/', $request );
	$candidates = array();
	for ( $i = count( $parts ); $i > 0; $i-- ) {
		$candidates[] = implode( '/', array_slice( $parts, 0, $i ) );
	}
	$target = tala_seo_moved_target( $request, $candidates );
	if ( '' !== $target ) {
		wp_safe_redirect( $target, 301, 'Tala SEO' );
		exit;
	}
}

/**
 * The current address for a request to an old one, or ''.
 *
 * @param string   $request    Requested path.
 * @param string[] $candidates The path and its parent paths, longest first.
 * @return string
 */
function tala_seo_moved_target( $request, $candidates ) {
	// Runs only for requests that would be a 404, so the meta lookups never slow down real pages.
	$meta_query = array(
		array(
			'key'     => TALA_SEO_OLD_PATH,
			'value'   => $candidates,
			'compare' => 'IN',
		),
	);
	$moved      = array(); // Old path => array{url: string, is_post: bool}.

	$post_types = tala_seo_redirect_post_types();
	if ( $post_types ) {
		$posts = get_posts(
			array(
				'post_type'   => $post_types,
				'post_status' => 'publish',
				'meta_query'  => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- only on 404s.
				'numberposts' => 10,
			)
		);
		foreach ( $posts as $post ) {
			foreach ( (array) get_post_meta( $post->ID, TALA_SEO_OLD_PATH, false ) as $path ) {
				$moved[ (string) $path ] = array(
					'url'     => (string) get_permalink( $post ),
					'is_post' => true,
				);
			}
		}
	}

	$terms = get_terms(
		array(
			'taxonomy'   => tala_seo_redirect_taxonomies(),
			'hide_empty' => false,
			'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- only on 404s.
			'number'     => 10,
		)
	);
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		$link = get_term_link( $term );
		if ( is_string( $link ) && '' !== $link ) {
			foreach ( (array) get_term_meta( $term->term_id, TALA_SEO_OLD_PATH, false ) as $path ) {
				$moved[ (string) $path ] = array(
					'url'     => $link,
					'is_post' => false,
				);
			}
		}
	}

	foreach ( $candidates as $candidate ) {
		if ( ! isset( $moved[ $candidate ] ) || '' === $moved[ $candidate ]['url'] ) {
			continue;
		}
		$rest   = ltrim( substr( $request, strlen( $candidate ) ), '/' );
		$target = '' === $rest ? $moved[ $candidate ]['url'] : user_trailingslashit( trailingslashit( $moved[ $candidate ]['url'] ) . $rest );
		// A child page below a moved parent must exist at its new address; otherwise it stays a 404.
		if ( '' !== $rest && $moved[ $candidate ]['is_post'] && ! url_to_postid( $target ) ) {
			return '';
		}
		return tala_seo_site_path( $target ) !== $request ? $target : '';
	}
	return '';
}
