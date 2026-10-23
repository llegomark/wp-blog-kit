<?php
/**
 * SEO fields for archive pages: categories, tags (and other public taxonomies) and authors each
 * get a custom title, meta description and "hide from search engines" switch. The forms are in
 * admin-fields.php; this file registers the fields and reads them. Terms fall back to what another
 * SEO plugin left behind until `wp tala-seo import` copies it; author values come over with the
 * import only, so the author sitemap can filter on our own field alone.
 *
 * Term and user meta use the same keys as posts: _tala_seo_title, _tala_seo_description and
 * _tala_seo_noindex.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Taxonomies whose archives get SEO fields: public ones with an admin screen, without formats.
 *
 * @return string[]
 */
function tala_seo_term_taxonomies() {
	$taxonomies = get_taxonomies(
		array(
			'public'  => true,
			'show_ui' => true,
		)
	);
	return array_values( array_diff( (array) apply_filters( 'tala_seo_term_taxonomies', $taxonomies ), array( 'post_format' ) ) );
}

add_action( 'init', 'tala_seo_register_archive_meta', 20 );

/**
 * Registers the term and user fields, so values saved over REST or WP-CLI are sanitized too.
 */
function tala_seo_register_archive_meta(): void {
	$fields = array(
		TALA_SEO_TITLE       => array( 'string', 'tala_seo_sanitize_line' ),
		TALA_SEO_DESCRIPTION => array( 'string', 'tala_seo_sanitize_line' ),
		TALA_SEO_NOINDEX     => array( 'boolean', 'rest_sanitize_boolean' ),
	);
	foreach ( $fields as $key => $field ) {
		$args = array(
			'type'              => $field[0],
			'single'            => true,
			'sanitize_callback' => $field[1],
			'show_in_rest'      => true,
		);
		foreach ( tala_seo_term_taxonomies() as $taxonomy ) {
			register_term_meta(
				$taxonomy,
				$key,
				$args + array(
					'auth_callback' => static function ( $allowed, $meta_key, $term_id ) {
						return current_user_can( 'edit_term', $term_id );
					},
				)
			);
		}
		register_meta(
			'user',
			$key,
			$args + array(
				'auth_callback' => static function ( $allowed, $meta_key, $user_id ) {
					return current_user_can( 'edit_user', $user_id );
				},
			)
		);
	}
	register_meta(
		'user',
		'tala_seo_fediverse',
		array(
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'tala_seo_sanitize_fediverse_handle',
			'show_in_rest'      => false,
		)
	);
}

/**
 * A term's SEO value: its own, else one migrated from another SEO plugin.
 *
 * @param WP_Term $term  Term.
 * @param string  $field title | description | noindex ('1' or '').
 * @return string
 */
function tala_seo_term_value( $term, $field ) {
	$keys = array(
		'title'       => TALA_SEO_TITLE,
		'description' => TALA_SEO_DESCRIPTION,
		'noindex'     => TALA_SEO_NOINDEX,
	);
	if ( ! isset( $keys[ $field ] ) ) {
		return '';
	}
	$own = get_term_meta( $term->term_id, $keys[ $field ], true );
	if ( 'noindex' === $field ) {
		return $own ? '1' : tala_seo_legacy_term_value( $term, 'noindex' );
	}
	$own = tala_seo_clean_text( (string) $own );
	return '' !== $own ? $own : tala_seo_legacy_term_value( $term, $field );
}

/**
 * A term's SEO value from Yoast SEO, Rank Math, The SEO Framework or SEOPress, or ''.
 *
 * @param WP_Term $term  Term.
 * @param string  $field title | description | noindex.
 * @return string
 */
function tala_seo_legacy_term_value( $term, $field ) {
	$id     = $term->term_id;
	$yoast  = get_option( 'wpseo_taxonomy_meta' );
	$yoast  = is_array( $yoast ) && isset( $yoast[ $term->taxonomy ][ $id ] ) && is_array( $yoast[ $term->taxonomy ][ $id ] ) ? $yoast[ $term->taxonomy ][ $id ] : array();
	$tsf    = get_term_meta( $id, 'autodescription-term-settings', true );
	$tsf    = is_array( $tsf ) ? $tsf : array();
	$robots = get_term_meta( $id, 'rank_math_robots', true );

	if ( 'noindex' === $field ) {
		// The SEO Framework stores 1 (noindex), 0 (default) or -1 (always index).
		$hidden = 'noindex' === ( $yoast['wpseo_noindex'] ?? '' )
			|| ( is_array( $robots ) && in_array( 'noindex', $robots, true ) )
			|| 1 === (int) ( $tsf['noindex'] ?? 0 )
			|| 'yes' === get_term_meta( $id, '_seopress_robots_index', true );
		return $hidden ? '1' : '';
	}
	return tala_seo_first_plain_value(
		'title' === $field
			? array( $yoast['wpseo_title'] ?? '', get_term_meta( $id, 'rank_math_title', true ), $tsf['doctitle'] ?? '', get_term_meta( $id, '_seopress_titles_title', true ) )
			: array( $yoast['wpseo_desc'] ?? '', get_term_meta( $id, 'rank_math_description', true ), $tsf['description'] ?? '', get_term_meta( $id, '_seopress_titles_desc', true ) )
	);
}

/**
 * An author's SEO value from Yoast SEO, Rank Math or The SEO Framework, or '' (for the import).
 *
 * @param int    $user_id User ID.
 * @param string $field   title | description | noindex | fediverse | fediverse_url.
 * @return string
 */
function tala_seo_legacy_user_value( $user_id, $field ) {
	$tsf = get_user_meta( $user_id, 'autodescription-user-settings', true );
	$tsf = is_array( $tsf ) ? $tsf : array();

	switch ( $field ) {
		case 'noindex':
			$robots = get_user_meta( $user_id, 'rank_math_robots', true );
			$hidden = 'on' === get_user_meta( $user_id, 'wpseo_noindex_author', true )
				|| ( is_array( $robots ) && in_array( 'noindex', $robots, true ) )
				|| 1 === (int) ( $tsf['noindex'] ?? 0 );
			return $hidden ? '1' : '';
		case 'title':
			return tala_seo_first_plain_value( array( get_user_meta( $user_id, 'wpseo_title', true ), get_user_meta( $user_id, 'rank_math_title', true ), $tsf['doctitle'] ?? '' ) );
		case 'description':
			return tala_seo_first_plain_value( array( get_user_meta( $user_id, 'wpseo_metadesc', true ), get_user_meta( $user_id, 'rank_math_description', true ), $tsf['description'] ?? '' ) );
		case 'fediverse':
			return tala_seo_sanitize_fediverse_handle( (string) ( $tsf['fediverse_page'] ?? '' ) );
		case 'fediverse_url':
			$url = esc_url_raw( (string) ( $tsf['fediverse_page_url'] ?? '' ), array( 'https' ) );
			return (string) wp_parse_url( $url, PHP_URL_HOST ) ? $url : '';
	}
	return '';
}

/**
 * The first value that is plain text. Template variables (%%term_title%%, %sitename%) can't be
 * resolved here, so values that use them are skipped.
 *
 * @param array<int, mixed> $candidates Values in order of preference.
 * @return string
 */
function tala_seo_first_plain_value( $candidates ) {
	$plain = array_find(
		$candidates,
		static fn( mixed $value ): bool => is_scalar( $value ) && '' !== trim( (string) $value ) && ! preg_match( '/%%?[a-z_]+%%?/i', (string) $value )
	);
	return null === $plain ? '' : tala_seo_clean_text( (string) $plain );
}

/**
 * An author's SEO value for their author page.
 *
 * @param int    $user_id User ID.
 * @param string $field   title | description | noindex ('1' or '').
 * @return string
 */
function tala_seo_user_value( $user_id, $field ) {
	$keys = array(
		'title'       => TALA_SEO_TITLE,
		'description' => TALA_SEO_DESCRIPTION,
		'noindex'     => TALA_SEO_NOINDEX,
	);
	if ( ! isset( $keys[ $field ] ) ) {
		return '';
	}
	$value = get_user_meta( $user_id, $keys[ $field ], true );
	if ( 'noindex' === $field ) {
		return $value ? '1' : '';
	}
	return tala_seo_clean_text( (string) $value );
}
