<?php
/**
 * The SEO column in the Posts, Pages, Categories and Tags lists: what each item has and still needs,
 * at a glance (the admin counterpart of `wp tala-seo audit`). Every item pairs a symbol with a word,
 * so its meaning never depends on colour, and screen readers hear a full sentence.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', 'tala_seo_column_hooks' );

/**
 * Adds the column to the lists of SEO post types and taxonomies.
 */
function tala_seo_column_hooks(): void {
	foreach ( tala_seo_post_types() as $type ) {
		add_filter( "manage_{$type}_posts_columns", 'tala_seo_add_column' );
		add_action( "manage_{$type}_posts_custom_column", 'tala_seo_post_column', 10, 2 );
	}
	foreach ( tala_seo_term_taxonomies() as $taxonomy ) {
		add_filter( "manage_edit-{$taxonomy}_columns", 'tala_seo_add_column' );
		add_filter( "manage_{$taxonomy}_custom_column", 'tala_seo_term_column', 10, 3 );
	}
}

/**
 * Inserts the SEO column after the title (posts) or name (terms).
 *
 * @param array<string, string> $columns Columns.
 * @return array<string, string>
 */
function tala_seo_add_column( $columns ) {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'title' === $key || 'name' === $key ) {
			$out['tala_seo'] = __( 'SEO', 'tala-seo' );
		}
	}
	if ( ! isset( $out['tala_seo'] ) ) {
		$out['tala_seo'] = __( 'SEO', 'tala-seo' );
	}
	return $out;
}

/**
 * The SEO column for a post or page.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function tala_seo_post_column( $column, $post_id ): void {
	if ( 'tala_seo' !== $column ) {
		return;
	}
	tala_seo_prime_thumbnails();
	$type     = (string) get_post_type( $post_id );
	$needed   = tala_seo_publish_requirements( $type );
	$problems = tala_seo_publish_problems( $type, tala_seo_post_description( $post_id ), (int) get_post_thumbnail_id( $post_id ) );
	$items    = array();

	if ( isset( $problems['description'] ) ) {
		$items[] = array( 'bad', __( 'Description', 'tala-seo' ), __( 'no meta description; it is needed to publish', 'tala-seo' ) );
	} elseif ( '' === tala_seo_post_description( $post_id ) ) {
		$items[] = array( 'warn', __( 'Description', 'tala-seo' ), __( 'no meta description; Google writes its own snippet', 'tala-seo' ) );
	} else {
		$items[] = array( 'good', __( 'Description', 'tala-seo' ), __( 'has a meta description', 'tala-seo' ) );
	}

	if ( in_array( 'featured_image', $needed, true ) ) {
		if ( isset( $problems['featured_image'] ) ) {
			$items[] = array( 'bad', __( 'Image', 'tala-seo' ), __( 'no featured image; it is needed to publish', 'tala-seo' ) );
		} elseif ( isset( $problems['featured_image_alt'] ) ) {
			$items[] = array( 'bad', __( 'Alt text', 'tala-seo' ), __( 'the featured image has no alternative text; it is needed to publish', 'tala-seo' ) );
		} else {
			$items[] = array( 'good', __( 'Image', 'tala-seo' ), __( 'has a featured image with alternative text', 'tala-seo' ) );
		}
	}

	$title = tala_seo_post_title( $post_id );
	$title = ( '' !== $title ? $title : tala_seo_clean_text( get_the_title( $post_id ) ) ) . ' ' . tala_seo_settings()['separator'] . ' ' . tala_seo_site_name();
	if ( mb_strlen( $title ) > 70 ) {
		$items[] = array( 'warn', __( 'Long title', 'tala-seo' ), __( 'the title may be cut short in Google; the SEO panel measures it', 'tala-seo' ) );
	}

	if ( tala_seo_post_noindex( $post_id ) ) {
		$items[] = array( 'warn', __( 'Hidden', 'tala-seo' ), __( 'hidden from search engines and left out of the sitemap', 'tala-seo' ) );
	}
	if ( '' !== tala_seo_post_redirect( $post_id ) ) {
		$items[] = array( 'info', __( 'Redirects', 'tala-seo' ), __( 'sends visitors to another address', 'tala-seo' ) );
	}
	$canonical = (string) get_post_meta( $post_id, TALA_SEO_CANONICAL, true );
	if ( '' !== $canonical || '' !== tala_seo_legacy_value( $post_id, 'canonical' ) ) {
		$items[] = array( 'info', __( 'Canonical', 'tala-seo' ), __( 'points search engines to another address as the original', 'tala-seo' ) );
	}

	echo tala_seo_status_list( $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in tala_seo_status_list().
}

/**
 * The SEO column for a category or tag.
 *
 * @param string $content Column content so far.
 * @param string $column  Column name.
 * @param int    $term_id Term ID.
 * @return string
 */
function tala_seo_term_column( $content, $column, $term_id ) {
	$term = get_term( (int) $term_id );
	if ( 'tala_seo' !== $column || ! $term instanceof WP_Term ) {
		return $content;
	}
	$items = array();

	if ( '' !== tala_seo_term_value( $term, 'description' ) ) {
		$items[] = array( 'good', __( 'Description', 'tala-seo' ), __( 'has an SEO description', 'tala-seo' ) );
	} elseif ( '' !== tala_seo_clean_text( $term->description ) ) {
		$items[] = array( 'good', __( 'Description', 'tala-seo' ), __( 'its description doubles as the meta description', 'tala-seo' ) );
	} else {
		$items[] = array( 'warn', __( 'Description', 'tala-seo' ), __( 'no description; Google writes its own snippet', 'tala-seo' ) );
	}

	if ( '1' === tala_seo_term_value( $term, 'noindex' ) ) {
		$items[] = array( 'warn', __( 'Hidden', 'tala-seo' ), __( 'hidden from search engines and left out of the sitemap', 'tala-seo' ) );
	} elseif ( tala_seo_term_is_empty( $term ) ) {
		$items[] = array( 'warn', __( 'Empty', 'tala-seo' ), __( 'no posts yet, so it stays out of search results', 'tala-seo' ) );
	} elseif ( tala_seo_is_thin_term( $term ) ) {
		$items[] = array( 'warn', __( 'Few posts', 'tala-seo' ), __( 'too few posts for search results yet (Settings → SEO)', 'tala-seo' ) );
	} else {
		$items[] = array( 'good', __( 'Indexed', 'tala-seo' ), __( 'search engines may show this page', 'tala-seo' ) );
	}

	return $content . tala_seo_status_list( $items );
}

/**
 * Whether a term's archive lists no posts: none of its own and, for categories, none in a child.
 *
 * @param WP_Term $term Term.
 * @return bool
 */
function tala_seo_term_is_empty( $term ) {
	if ( (int) $term->count > 0 ) {
		return false;
	}
	if ( ! is_taxonomy_hierarchical( $term->taxonomy ) ) {
		return true;
	}
	$children = get_terms(
		array(
			'taxonomy'   => $term->taxonomy,
			'child_of'   => $term->term_id,
			'hide_empty' => true,
			'fields'     => 'ids',
			'number'     => 1,
		)
	);
	return ! $children;
}

/**
 * The status items as a list.
 *
 * @param array<int, array{0:string,1:string,2:string}> $items Status (good|warn|bad|info), label, sentence.
 * @return string
 */
function tala_seo_status_list( $items ) {
	$marks = array(
		'good' => '✓',
		'warn' => '!',
		'bad'  => '✕',
		'info' => '↪',
	);
	$html  = '';
	foreach ( $items as $item ) {
		list( $status, $label, $sentence ) = $item;
		$html                             .= sprintf(
			'<li class="tala-seo-status__item is-%1$s" title="%4$s"><span class="tala-seo-status__mark" aria-hidden="true">%2$s</span>%3$s<span class="screen-reader-text">: %4$s.</span></li>',
			esc_attr( $status ),
			esc_html( $marks[ $status ] ?? '' ),
			esc_html( $label ),
			esc_attr( $sentence )
		);
	}
	return '<ul class="tala-seo-status">' . $html . '</ul>';
}

/**
 * Loads the featured images (and their alt text) of the whole list in one query instead of one
 * per row. A Quick Edit save shows a single row without a list query: nothing to load then.
 */
function tala_seo_prime_thumbnails(): void {
	static $done = false;
	global $wp_query;
	if ( ! $done && $wp_query instanceof WP_Query && is_array( $wp_query->posts ) ) {
		update_post_thumbnail_cache( $wp_query );
	}
	$done = true;
}

add_action( 'admin_enqueue_scripts', 'tala_seo_column_styles' );

/**
 * The column's small stylesheet, on list screens only.
 *
 * @param string $hook Admin page.
 */
function tala_seo_column_styles( $hook ): void {
	if ( in_array( $hook, array( 'edit.php', 'edit-tags.php' ), true ) ) {
		wp_enqueue_style( 'tala-seo-lists', plugins_url( 'assets/lists.css', TALA_SEO_FILE ), array(), TALA_SEO_VERSION );
	}
}
