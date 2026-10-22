<?php
/**
 * Shared helpers: settings, text cleaning, per-post SEO values.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings (Settings → SEO), with defaults.
 *
 * @return array{publisher_type:string,publisher_name:string,same_as:string[],home_description:string,default_image:int}
 */
function tala_seo_settings() {
	$saved = get_option( 'tala_seo', array() );
	$saved = is_array( $saved ) ? $saved : array();
	// Each value is cast, so a damaged option can never hand the wrong type to the rest of the plugin.
	return array(
		'publisher_type'   => 'person' === ( $saved['publisher_type'] ?? '' ) ? 'person' : 'organization',
		'publisher_name'   => (string) ( $saved['publisher_name'] ?? '' ),
		'same_as'          => array_values( array_map( 'strval', (array) ( $saved['same_as'] ?? array() ) ) ),
		'home_description' => (string) ( $saved['home_description'] ?? '' ),
		'default_image'    => (int) ( $saved['default_image'] ?? 0 ),
	);
}

/**
 * Sanitizes a one-line text value: no tags, no line breaks, collapsed spaces.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function tala_seo_sanitize_line( $value ) {
	return tala_seo_clean_text( (string) $value );
}

/**
 * Plain text for meta tags and structured data.
 *
 * @param string $text  Text that may contain HTML, entities or extra whitespace.
 * @param int    $words Optional word limit (0 = none).
 * @return string
 */
function tala_seo_clean_text( $text, $words = 0 ) {
	$text = wp_strip_all_tags( strip_shortcodes( (string) $text ), true );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	if ( $words > 0 ) {
		$text = wp_trim_words( $text, $words, '…' );
	}
	return $text;
}

/**
 * Site name, as used in titles, og:site_name and WebSite markup.
 *
 * @return string
 */
function tala_seo_site_name() {
	return tala_seo_clean_text( get_bloginfo( 'name' ) );
}

/**
 * Values another SEO plugin may have left on a post (Yoast SEO, Rank Math), so a migrated
 * site keeps its descriptions until they are imported with `wp tala-seo import`.
 *
 * @param int    $post_id Post ID.
 * @param string $field   title | description | canonical | noindex.
 * @return string
 */
function tala_seo_legacy_value( $post_id, $field ) {
	$keys = array(
		'title'       => array( '_yoast_wpseo_title', 'rank_math_title' ),
		'description' => array( '_yoast_wpseo_metadesc', 'rank_math_description' ),
		'canonical'   => array( '_yoast_wpseo_canonical', 'rank_math_canonical_url' ),
	);
	if ( 'noindex' === $field ) {
		if ( '1' === get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			return '1';
		}
		$rank = get_post_meta( $post_id, 'rank_math_robots', true );
		return ( is_array( $rank ) && in_array( 'noindex', $rank, true ) ) ? '1' : '';
	}
	foreach ( $keys[ $field ] ?? array() as $key ) {
		$value = (string) get_post_meta( $post_id, $key, true );
		// Template variables (%%title%%, %sitename%) can't be resolved here; skip them.
		if ( '' !== $value && ! preg_match( '/%%?[a-z_]+%%?/i', $value ) ) {
			return tala_seo_clean_text( $value );
		}
	}
	return '';
}

/**
 * The meta description a post was given (ours first, then a migrated one).
 *
 * @param int $post_id Post ID.
 * @return string
 */
function tala_seo_post_description( $post_id ) {
	$value = tala_seo_clean_text( get_post_meta( $post_id, TALA_SEO_DESCRIPTION, true ) );
	return '' !== $value ? $value : tala_seo_legacy_value( $post_id, 'description' );
}

/**
 * The SEO title a post was given, without the site name (empty = use the post title).
 *
 * @param int $post_id Post ID.
 * @return string
 */
function tala_seo_post_title( $post_id ) {
	$value = tala_seo_clean_text( get_post_meta( $post_id, TALA_SEO_TITLE, true ) );
	return '' !== $value ? $value : tala_seo_legacy_value( $post_id, 'title' );
}

/**
 * Whether a post is hidden from search engines.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function tala_seo_post_noindex( $post_id ) {
	return (bool) get_post_meta( $post_id, TALA_SEO_NOINDEX, true ) || '1' === tala_seo_legacy_value( $post_id, 'noindex' );
}

/**
 * Image data for structured data and Open Graph.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $size          Image size name.
 * @return array{url:string,width:int,height:int,alt:string}|null
 */
function tala_seo_image( $attachment_id, $size = 'full' ) {
	$src = $attachment_id ? wp_get_attachment_image_src( $attachment_id, $size ) : false;
	if ( ! $src ) {
		return null;
	}
	return array(
		'url'    => $src[0],
		'width'  => (int) $src[1],
		'height' => (int) $src[2],
		'alt'    => tala_seo_clean_text( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
	);
}

/**
 * Article image URLs in the 16:9, 4:3 and 1:1 crops Google recommends; falls back to the
 * original image when it is too small for a crop.
 *
 * @param int $attachment_id Attachment ID.
 * @return string[]
 */
function tala_seo_article_images( $attachment_id ) {
	$urls = array();
	foreach ( array( 'tala-seo-16x9', 'tala-seo-4x3', 'tala-seo-1x1' ) as $size ) {
		$src = image_get_intermediate_size( $attachment_id, $size );
		if ( $src && ! empty( $src['url'] ) ) {
			$urls[] = $src['url'];
		}
	}
	if ( ! $urls ) {
		$full = tala_seo_image( $attachment_id );
		if ( $full ) {
			$urls[] = $full['url'];
		}
	}
	return array_values( array_unique( $urls ) );
}

/**
 * Logo for Organization markup: the Site Logo, else the Site Icon.
 *
 * @return array{url:string,width:int,height:int,alt:string}|null
 */
function tala_seo_logo() {
	$id = (int) get_theme_mod( 'custom_logo' );
	if ( ! $id ) {
		$id = (int) get_option( 'site_logo' );
	}
	if ( ! $id ) {
		$id = (int) get_option( 'site_icon' );
	}
	return $id ? tala_seo_image( $id ) : null;
}

/**
 * Author's real profile photo (never a Gravatar placeholder: Google asks for no default images).
 *
 * @param int $user_id User ID.
 * @return string Image URL or ''.
 */
function tala_seo_author_photo( $user_id ) {
	$id  = (int) get_user_meta( $user_id, 'tala_seo_photo', true );
	$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	return $url ? $url : '';
}

/**
 * Public profile links for an author (website and social profiles from their user profile).
 *
 * @param int $user_id User ID.
 * @return string[]
 */
function tala_seo_author_profiles( $user_id ) {
	$links = array( get_the_author_meta( 'user_url', $user_id ) );
	foreach ( array_keys( tala_seo_social_fields() ) as $field ) {
		$links[] = get_the_author_meta( $field, $user_id );
	}
	$links = array_filter( array_map( 'esc_url_raw', array_map( 'trim', $links ) ) );
	return array_values( array_unique( $links ) );
}

/**
 * Social profile fields added to user profiles.
 *
 * @return array<string,string>
 */
function tala_seo_social_fields() {
	return array(
		'tala_facebook'  => __( 'Facebook profile URL', 'tala-seo' ),
		'tala_x'         => __( 'X (Twitter) profile URL', 'tala-seo' ),
		'tala_linkedin'  => __( 'LinkedIn profile URL', 'tala-seo' ),
		'tala_youtube'   => __( 'YouTube channel URL', 'tala-seo' ),
		'tala_instagram' => __( 'Instagram profile URL', 'tala-seo' ),
	);
}

/**
 * Builds the absolute URL of page $paged of an archive (each page is its own canonical).
 *
 * @param string $url   First page URL.
 * @param int    $paged Page number.
 * @return string
 */
function tala_seo_paged_url( $url, $paged ) {
	global $wp_rewrite;
	if ( $paged < 2 ) {
		return $url;
	}
	if ( $wp_rewrite->using_permalinks() ) {
		return user_trailingslashit( trailingslashit( $url ) . $wp_rewrite->pagination_base . '/' . $paged, 'paged' );
	}
	return add_query_arg( 'paged', $paged, $url );
}

/**
 * Cleans the submitted settings.
 *
 * @param mixed $input Submitted values.
 * @return array{publisher_type: string, publisher_name: string, same_as: string[], home_description: string, default_image: int}
 */
function tala_seo_sanitize_settings( $input ) {
	$input   = is_array( $input ) ? $input : array();
	$same_as = array();
	$lines   = is_array( $input['same_as'] ?? null ) ? $input['same_as'] : (array) preg_split( '/\R/', (string) ( $input['same_as'] ?? '' ) );
	foreach ( $lines as $line ) {
		$url = esc_url_raw( trim( (string) $line ), array( 'http', 'https' ) );
		if ( '' !== $url ) {
			$same_as[] = $url;
		}
	}
	return array(
		'publisher_type'   => 'person' === ( $input['publisher_type'] ?? '' ) ? 'person' : 'organization',
		'publisher_name'   => sanitize_text_field( $input['publisher_name'] ?? '' ),
		'same_as'          => array_values( array_unique( $same_as ) ),
		'home_description' => tala_seo_clean_text( $input['home_description'] ?? '' ),
		'default_image'    => absint( $input['default_image'] ?? 0 ),
	);
}
