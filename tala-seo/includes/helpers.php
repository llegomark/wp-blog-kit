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
 * @return array{publisher_type:string,publisher_name:string,same_as:string[],home_description:string,default_image:int,tag_min_posts:int,verify:array<string,string>}
 */
function tala_seo_settings() {
	$saved       = get_option( 'tala_seo', array() );
	$saved       = is_array( $saved ) ? $saved : array();
	$saved_codes = is_array( $saved['verify'] ?? null ) ? $saved['verify'] : array();
	$codes       = array();
	foreach ( array_keys( tala_seo_verification_services() ) as $service ) {
		$codes[ $service ] = is_string( $saved_codes[ $service ] ?? null ) ? $saved_codes[ $service ] : '';
	}
	// Each value is cast, so a damaged option can never hand the wrong type to the rest of the plugin.
	return array(
		'publisher_type'   => 'person' === ( $saved['publisher_type'] ?? '' ) ? 'person' : 'organization',
		'publisher_name'   => (string) ( $saved['publisher_name'] ?? '' ),
		'same_as'          => array_values( array_map( strval( ... ), (array) ( $saved['same_as'] ?? array() ) ) ),
		'home_description' => (string) ( $saved['home_description'] ?? '' ),
		'default_image'    => (int) ( $saved['default_image'] ?? 0 ),
		'tag_min_posts'    => min( 100, max( 0, (int) ( $saved['tag_min_posts'] ?? 2 ) ) ),
		'verify'           => $codes,
	);
}

/**
 * The description of the site as a whole: Settings → SEO, else the tagline.
 * Used for the home page meta description and the Organization (or Person) markup.
 *
 * @return string
 */
function tala_seo_home_description() {
	$text = tala_seo_settings()['home_description'];
	return '' !== $text ? $text : tala_seo_clean_text( get_bloginfo( 'description' ) );
}

/**
 * Whether a tag page has too few posts to be worth showing in search results. Such pages get
 * noindex and stay out of the sitemap until they reach the minimum set in Settings → SEO.
 *
 * @param WP_Term $term Term.
 * @return bool
 */
function tala_seo_is_thin_term( $term ) {
	return (int) $term->count < tala_seo_min_posts( $term->taxonomy );
}

/**
 * How many posts a term of this taxonomy needs for its page to be indexed (0: no minimum).
 *
 * @param string $taxonomy Taxonomy.
 * @return int
 */
function tala_seo_min_posts( $taxonomy ) {
	$min = tala_seo_settings()['tag_min_posts'];
	return $min > 1 && in_array( $taxonomy, tala_seo_thin_taxonomies(), true ) ? $min : 0;
}

/**
 * Taxonomies whose archives need a minimum number of posts to be indexed (tags by default).
 *
 * @return string[]
 */
function tala_seo_thin_taxonomies() {
	return array_map( strval( ... ), (array) apply_filters( 'tala_seo_thin_taxonomies', array( 'post_tag' ) ) );
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
 * Values another SEO plugin may have left on a post (Yoast SEO, Rank Math, The SEO Framework,
 * SEOPress), so a migrated site keeps its titles and descriptions until they are imported with
 * `wp tala-seo import`.
 *
 * @param int    $post_id Post ID.
 * @param string $field   title | description | canonical | noindex.
 * @return string
 */
function tala_seo_legacy_value( $post_id, $field ) {
	$keys = array(
		'title'       => array( '_yoast_wpseo_title', 'rank_math_title', '_genesis_title', '_seopress_titles_title' ),
		'description' => array( '_yoast_wpseo_metadesc', 'rank_math_description', '_genesis_description', '_seopress_titles_desc' ),
		'canonical'   => array( '_yoast_wpseo_canonical', 'rank_math_canonical_url', '_genesis_canonical_uri', '_seopress_robots_canonical' ),
	);
	if ( 'noindex' === $field ) {
		if ( '1' === get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true )
			|| '1' === (string) get_post_meta( $post_id, '_genesis_noindex', true )
			|| 'yes' === get_post_meta( $post_id, '_seopress_robots_index', true ) ) {
			return '1';
		}
		$rank = get_post_meta( $post_id, 'rank_math_robots', true );
		return ( is_array( $rank ) && in_array( 'noindex', $rank, true ) ) ? '1' : '';
	}
	return tala_seo_first_plain_value(
		array_map( static fn( string $key ): mixed => get_post_meta( $post_id, $key, true ), $keys[ $field ] ?? array() )
	);
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
 * The image shown when a page is shared (og:image): the 1200 × 630 (1.91:1) crop that Facebook,
 * LinkedIn, X and messaging apps display. Images uploaded before that crop existed use the 16:9
 * crop, then the original, until tala_seo_make_share_crop() has made it. Only sizes that exist as
 * files are used, so the width and height sent with the image are the file's own.
 *
 * @param int $attachment_id Attachment ID (0 for none).
 * @return array{url: string, width: int, height: int, alt: string}|null
 */
function tala_seo_share_image( $attachment_id ) {
	if ( ! $attachment_id ) {
		return null;
	}
	if ( image_get_intermediate_size( $attachment_id, 'tala-seo-share' ) ) {
		return tala_seo_image( $attachment_id, 'tala-seo-share' );
	}
	tala_seo_queue_share_crop( $attachment_id );
	$size = image_get_intermediate_size( $attachment_id, 'tala-seo-16x9' ) ? 'tala-seo-16x9' : 'full';
	return tala_seo_image( $attachment_id, $size );
}

/**
 * Schedules the share crop of an image uploaded before it existed: once per image, in the
 * background (WP-Cron), never while a visitor waits. Images smaller than 1200 × 630 can't have it.
 *
 * @param int $attachment_id Attachment ID.
 */
function tala_seo_queue_share_crop( $attachment_id ): void {
	$original = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( ! $original || $original[1] < 1200 || $original[2] < 630
		|| get_post_meta( $attachment_id, TALA_SEO_SHARE_CROP_TRIED, true ) ) {
		return;
	}
	$args = array( (int) $attachment_id );
	if ( ! wp_next_scheduled( 'tala_seo_make_share_crop', $args ) ) {
		wp_schedule_single_event( time(), 'tala_seo_make_share_crop', $args );
	}
}

add_action( 'tala_seo_make_share_crop', 'tala_seo_make_share_crop' );

/**
 * Makes an image's missing crops, the share crop among them, the way WordPress does on upload.
 * Tried once per image: one whose crop can't be made keeps using the 16:9 crop or the original.
 *
 * @param int $attachment_id Attachment ID.
 */
function tala_seo_make_share_crop( $attachment_id ): void {
	$attachment_id = (int) $attachment_id;
	update_post_meta( $attachment_id, TALA_SEO_SHARE_CROP_TRIED, 1 );
	if ( ! wp_attachment_is_image( $attachment_id ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_image_subsizes( $attachment_id );
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
 * The user meta key that holds a user's author photo on this site. An attachment ID belongs to one
 * site's media library, so on a network each site keeps its own choice.
 *
 * @return string
 */
function tala_seo_photo_key() {
	global $wpdb;
	return is_multisite() ? $wpdb->get_blog_prefix() . 'tala_seo_photo' : 'tala_seo_photo';
}

/**
 * The author photo's attachment ID while visitors may see it, else 0. An image attached to a draft or
 * private post stays hidden, as WordPress hides it, until that post is published.
 *
 * @param int $user_id User ID.
 * @return int
 */
function tala_seo_photo_id( $user_id ) {
	$id = (int) get_user_meta( (int) $user_id, tala_seo_photo_key(), true );
	return $id && wp_attachment_is_image( $id ) && 'publish' === get_post_status( $id ) ? $id : 0;
}

/**
 * Author's real profile photo (never a Gravatar placeholder: Google asks for no default images).
 *
 * @param int $user_id User ID.
 * @return string Image URL or ''.
 */
function tala_seo_author_photo( $user_id ) {
	$id  = tala_seo_photo_id( $user_id );
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
	$links = array_filter( array_map( tala_seo_sanitize_url( ... ), $links ) );
	return array_values( array_unique( $links ) );
}

/**
 * Social profile fields added to user profiles.
 *
 * @return array<string,string>
 */
function tala_seo_social_fields() {
	return array(
		'tala_facebook'      => __( 'Facebook profile URL', 'tala-seo' ),
		'tala_x'             => __( 'X (Twitter) profile URL', 'tala-seo' ),
		'tala_linkedin'      => __( 'LinkedIn profile URL', 'tala-seo' ),
		'tala_youtube'       => __( 'YouTube channel URL', 'tala-seo' ),
		'tala_instagram'     => __( 'Instagram profile URL', 'tala-seo' ),
		'tala_tiktok'        => __( 'TikTok profile URL', 'tala-seo' ),
		'tala_fediverse_url' => __( 'Mastodon (Fediverse) profile URL', 'tala-seo' ),
	);
}

/**
 * An absolute http(s) address on a named host, or ''. "example.com/page" becomes
 * "http://example.com/page"; plain words, which esc_url_raw() alone would turn into an address
 * such as "http://not%20a%20link", are refused.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function tala_seo_sanitize_url( $value ) {
	$url  = esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	return preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z][a-z0-9-]*[a-z0-9]$/i', $host ) ? $url : '';
}

/**
 * A redirect target: an absolute http(s) address, or ''.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function tala_seo_sanitize_redirect( $value ) {
	return tala_seo_sanitize_url( $value );
}

/**
 * The address a post sends its visitors to (its redirect field), or ''.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function tala_seo_post_redirect( $post_id ) {
	return tala_seo_sanitize_redirect( get_post_meta( $post_id, TALA_SEO_REDIRECT, true ) );
}

/**
 * A Fediverse handle in the "@user@instance" form Mastodon expects for fediverse:creator, or ''.
 * Accepts "user@instance", "@user@instance" and "acct:user@instance". A profile URL is not
 * accepted: Mastodon reads a URL there as a handle and can drop the whole link preview, and
 * guessing the handle from a URL can be wrong (the profile URL has a field of its own).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function tala_seo_sanitize_fediverse_handle( $value ) {
	$value = trim( (string) $value );
	if ( str_starts_with( $value, 'acct:' ) ) {
		$value = substr( $value, 5 );
	}
	if ( ! preg_match( '/^@?([a-z0-9_][a-z0-9_.-]*)@((?:[a-z0-9-]+\.)+[a-z]{2,})$/i', $value, $parts ) ) {
		return '';
	}
	return '@' . $parts[1] . '@' . strtolower( $parts[2] );
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
 * @return array{publisher_type: string, publisher_name: string, same_as: string[], home_description: string, default_image: int, tag_min_posts: int, verify: array<string, string>}
 */
function tala_seo_sanitize_settings( $input ) {
	$input  = is_array( $input ) ? $input : array();
	$codes  = is_array( $input['verify'] ?? null ) ? $input['verify'] : array();
	$verify = array();
	foreach ( array_keys( tala_seo_verification_services() ) as $service ) {
		$verify[ $service ] = tala_seo_sanitize_verification( $codes[ $service ] ?? '', $service );
	}
	$same_as = array();
	$lines   = is_array( $input['same_as'] ?? null ) ? $input['same_as'] : (array) preg_split( '/\R/', (string) ( $input['same_as'] ?? '' ) );
	foreach ( $lines as $line ) {
		$url = tala_seo_sanitize_url( $line );
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
		'tag_min_posts'    => min( 100, absint( $input['tag_min_posts'] ?? 2 ) ),
		'verify'           => $verify,
	);
}

/**
 * Search engines whose site verification codes Settings → SEO accepts: the meta tag name the code
 * goes in, what a code looks like, and the tool's name.
 *
 * @return array<string, array{meta: string, pattern: string, label: string}>
 */
function tala_seo_verification_services() {
	return array(
		'google'   => array(
			'meta'    => 'google-site-verification',
			'pattern' => '/^[A-Za-z0-9_-]{10,100}$/',
			'label'   => 'Google Search Console',
		),
		'bing'     => array(
			'meta'    => 'msvalidate.01',
			'pattern' => '/^[A-Za-z0-9]{10,100}$/',
			'label'   => 'Bing Webmaster Tools',
		),
		'facebook' => array(
			'meta'    => 'facebook-domain-verification',
			'pattern' => '/^[A-Za-z0-9]{10,100}$/',
			'label'   => 'Meta (Facebook)',
		),
	);
}

/**
 * A verification code from what was pasted: the code alone, or the whole <meta> tag the tool shows.
 * Anything else keeps the code saved before, and the settings screen says why.
 *
 * @param mixed  $value   Pasted value.
 * @param string $service Key in tala_seo_verification_services().
 * @return string
 */
function tala_seo_sanitize_verification( $value, $service ) {
	$services = tala_seo_verification_services();
	$value    = trim( is_scalar( $value ) ? (string) $value : '' );
	if ( '' === $value || ! isset( $services[ $service ] ) ) {
		return '';
	}
	$expected = $services[ $service ];
	$problem  = '';
	if ( preg_match( '/<meta\b/i', $value ) ) {
		$name  = preg_match( '/\bname\s*=\s*["\']([^"\']*)["\']/i', $value, $m ) ? strtolower( $m[1] ) : '';
		$value = preg_match( '/\bcontent\s*=\s*["\']([^"\']*)["\']/i', $value, $m ) ? trim( $m[1] ) : '';
		if ( $name !== $expected['meta'] ) {
			/* translators: %s: name of a webmaster tool, e.g. Google Search Console. */
			$problem = __( 'The pasted tag is not a %s verification tag.', 'tala-seo' );
		}
	}
	if ( '' === $problem && ! preg_match( $expected['pattern'], $value ) ) {
		/* translators: %s: name of a webmaster tool, e.g. Google Search Console. */
		$problem = __( 'That doesn\'t look like a %s verification code. Paste the code, or the whole meta tag.', 'tala-seo' );
	}
	if ( '' === $problem ) {
		return $value;
	}
	// The Settings API can run this twice in one save; report the problem once.
	$code = 'tala_seo_verify_' . $service;
	if ( function_exists( 'add_settings_error' ) && ! in_array( $code, wp_list_pluck( get_settings_errors( 'tala_seo' ), 'code' ), true ) ) {
		add_settings_error( 'tala_seo', $code, sprintf( $problem, $expected['label'] ) . ' ' . __( 'The code saved before was kept.', 'tala-seo' ) );
	}
	return tala_seo_settings()['verify'][ $service ] ?? '';
}
