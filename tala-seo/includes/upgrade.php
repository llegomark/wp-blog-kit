<?php
/**
 * What an update of the plugin does once on each site: the version that last ran is kept in an
 * option, and a newer one runs the steps the versions in between ask for.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * The option that holds the version of Tala SEO that last ran on this site.
 */
const TALA_SEO_VERSION_OPTION = 'tala_seo_version';

// After the post types and the plugin's meta are registered (init, 10 and earlier).
add_action( 'init', 'tala_seo_upgrade', 30 );

/**
 * Runs the steps of the versions since the one that last ran here, once.
 */
function tala_seo_upgrade(): void {
	$from = get_option( TALA_SEO_VERSION_OPTION, '' );
	$from = is_string( $from ) ? $from : '';
	if ( TALA_SEO_VERSION === $from ) {
		return;
	}
	// 1.20.0: only people who publish may set a post's redirect or canonical. Sites that ran an
	// earlier version (which kept no version, so '' as well) may hold ones set before that.
	if ( '' === $from || version_compare( $from, '1.20.0', '<' ) ) {
		tala_seo_drop_unreviewed_targets();
	}

	// Noted once the steps are done: a request that was cut short runs them again (each can be
	// repeated safely), where noting it first would leave them half done for good.
	update_option( TALA_SEO_VERSION_OPTION, TALA_SEO_VERSION );
}

/**
 * Takes the redirect and the canonical off posts that aren't published and whose author can't
 * publish: until 1.20.0 anyone who could edit a post could fill them, so on such a post nobody who
 * publishes may have seen them, and they would go live with it. (The import applies the same rule
 * to another plugin's redirects.) Published posts keep theirs, as do the posts of people who
 * publish; someone who publishes can set them again in the SEO panel.
 *
 * @return int How many posts lost a value.
 */
function tala_seo_drop_unreviewed_targets() {
	$ids = get_posts(
		array(
			'post_type'      => tala_seo_post_types(),
			'post_status'    => array( 'draft', 'pending', 'auto-draft', 'trash' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- once per site, on update.
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => TALA_SEO_REDIRECT,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => TALA_SEO_CANONICAL,
					'compare' => 'EXISTS',
				),
			),
		)
	);

	$changed = 0;
	foreach ( $ids as $id ) {
		$id     = (int) $id;
		$type   = get_post_type_object( (string) get_post_type( $id ) );
		$author = (int) get_post_field( 'post_author', $id );
		if ( $type && user_can( $author, $type->cap->edit_published_posts ) ) {
			continue;
		}
		$redirect  = delete_post_meta( $id, TALA_SEO_REDIRECT );
		$canonical = delete_post_meta( $id, TALA_SEO_CANONICAL );
		if ( $redirect || $canonical ) {
			++$changed;
		}
	}
	return $changed;
}
