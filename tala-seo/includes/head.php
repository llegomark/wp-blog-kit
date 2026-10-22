<?php
/**
 * Everything in <head>: title, meta description, canonical, robots, Open Graph.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'tala_seo_clean_head' );

/**
 * Removes head output that is useless for SEO or leaks the WordPress version, and core's
 * canonical tag (this plugin prints one for every indexable page, not only single posts).
 */
function tala_seo_clean_head(): void {
	remove_action( 'wp_head', 'rel_canonical' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
}
add_filter( 'the_generator', '__return_empty_string' );

// Google: put the site name at the start or end, separated by a hyphen, colon or pipe.
add_filter(
	'document_title_separator',
	static function () {
		return '|';
	}
);

add_filter( 'document_title_parts', 'tala_seo_title_parts' );

/**
 * Uses the post's SEO title (when set) in place of the post title; the site name stays at the end.
 *
 * @param array<string, string> $parts Title parts.
 * @return array<string, string>
 */
function tala_seo_title_parts( $parts ) {
	if ( is_singular( tala_seo_post_types() ) && ! is_front_page() ) {
		$custom = tala_seo_post_title( get_queried_object_id() );
		if ( '' !== $custom ) {
			$parts['title'] = $custom;
		}
	}
	return $parts;
}

/**
 * The description for the current page, or '' when there is none.
 *
 * @return string
 */
function tala_seo_current_description() {
	if ( is_singular() ) {
		$id   = get_queried_object_id();
		$text = tala_seo_post_description( $id );
		if ( '' === $text && has_excerpt( $id ) ) {
			$text = tala_seo_clean_text( (string) get_post( $id )?->post_excerpt );
		}
		return $text;
	}
	if ( is_front_page() || is_home() ) {
		$text = tala_seo_settings()['home_description'];
		return '' !== $text ? $text : tala_seo_clean_text( get_bloginfo( 'description' ) );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		return tala_seo_clean_text( term_description(), 60 );
	}
	if ( is_author() ) {
		return tala_seo_clean_text( get_the_author_meta( 'description', get_queried_object_id() ), 60 );
	}
	return '';
}

/**
 * The canonical URL of the current page, or '' when the page shouldn't be indexed.
 *
 * @return string
 */
function tala_seo_current_canonical() {
	if ( is_404() || is_search() || is_date() || is_preview() ) {
		return '';
	}
	if ( is_singular() ) {
		$url = wp_get_canonical_url( get_queried_object_id() );
		return $url ? $url : '';
	}
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_home() ) {
		$url = get_permalink( (int) get_option( 'page_for_posts' ) );
	} elseif ( ( is_category() || is_tag() || is_tax() ) && get_queried_object() instanceof WP_Term ) {
		$url = get_term_link( get_queried_object() );
	} elseif ( is_author() ) {
		$url = get_author_posts_url( get_queried_object_id() );
	} else {
		return '';
	}
	return is_wp_error( $url ) || ! $url ? '' : tala_seo_paged_url( $url, $paged );
}

add_filter( 'get_canonical_url', 'tala_seo_custom_canonical', 10, 2 );

/**
 * Applies a post's custom canonical URL, if one was set in the SEO panel.
 *
 * @param string  $url  Canonical URL.
 * @param WP_Post $post Post.
 * @return string
 */
function tala_seo_custom_canonical( $url, $post ) {
	$custom = (string) get_post_meta( $post->ID, TALA_SEO_CANONICAL, true );
	if ( '' === $custom ) {
		$custom = tala_seo_legacy_value( $post->ID, 'canonical' );
	}
	return '' !== $custom ? $custom : $url;
}

add_filter( 'wp_robots', 'tala_seo_robots' );

/**
 * Noindex for pages that shouldn't appear in search: internal search results, 404s, date
 * archives (duplicates of the post list) and posts the author marked as hidden.
 *
 * @param array<string, bool|string> $robots Robots directives.
 * @return array<string, bool|string>
 */
function tala_seo_robots( $robots ) {
	$hide = is_search() || is_404() || is_date();
	if ( is_singular() && tala_seo_post_noindex( get_queried_object_id() ) ) {
		$hide = true;
	}
	if ( $hide ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}

add_action( 'wp_head', 'tala_seo_print_head', 1 );

/**
 * Prints the meta description, canonical link and Open Graph tags.
 */
function tala_seo_print_head(): void {
	$tags        = array();
	$description = tala_seo_current_description();
	$canonical   = tala_seo_current_canonical();

	if ( '' !== $description ) {
		$tags[] = sprintf( '<meta name="description" content="%s">', esc_attr( $description ) );
	}
	if ( '' !== $canonical ) {
		$tags[] = sprintf( '<link rel="canonical" href="%s">', esc_url( $canonical ) );
	}

	// Open Graph: how links look when shared (Facebook, Messenger, Viber, X, LinkedIn).
	$og = array(
		'og:locale'    => get_locale(),
		'og:site_name' => tala_seo_site_name(),
		'og:type'      => is_singular( 'post' ) ? 'article' : 'website',
		'og:title'     => tala_seo_og_title(),
		'og:url'       => $canonical,
	);
	if ( '' !== $description ) {
		$og['og:description'] = $description;
	}
	$image_id = tala_seo_og_image_id();
	$image    = $image_id ? tala_seo_image( $image_id, 'tala-seo-16x9' ) : null;
	if ( $image ) {
		$og['og:image']        = $image['url'];
		$og['og:image:width']  = (string) $image['width'];
		$og['og:image:height'] = (string) $image['height'];
		if ( '' !== $image['alt'] ) {
			$og['og:image:alt'] = $image['alt'];
		}
	}
	$post = get_queried_object();
	if ( is_singular( 'post' ) && $post instanceof WP_Post ) {
		$og['article:published_time'] = (string) get_post_time( 'c', true, $post );
		$og['article:modified_time']  = (string) get_post_modified_time( 'c', true, $post );
	}
	foreach ( $og as $property => $value ) {
		if ( '' !== (string) $value ) {
			$tags[] = sprintf( '<meta property="%s" content="%s">', esc_attr( $property ), esc_attr( (string) $value ) );
		}
	}
	$tags[] = sprintf( '<meta name="twitter:card" content="%s">', $image ? 'summary_large_image' : 'summary' );

	echo implode( "\n", $tags ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value escaped above.
}

/**
 * The title used when the page is shared (no site name; og:site_name carries it).
 *
 * @return string
 */
function tala_seo_og_title() {
	if ( is_singular() && ! is_front_page() ) {
		$id     = get_queried_object_id();
		$custom = tala_seo_post_title( $id );
		return '' !== $custom ? $custom : tala_seo_clean_text( get_the_title( $id ) );
	}
	return tala_seo_clean_text( wp_get_document_title() );
}

/**
 * The image to show when the page is shared: the featured image, else the default image
 * from Settings → SEO. Never the logo (Google asks for article images, not logos).
 *
 * @return int Attachment ID or 0.
 */
function tala_seo_og_image_id() {
	if ( is_singular() ) {
		$id = (int) get_post_thumbnail_id( get_queried_object_id() );
		if ( $id ) {
			return $id;
		}
	}
	return (int) tala_seo_settings()['default_image'];
}

add_filter( 'get_the_excerpt', 'tala_seo_excerpt_fallback', 9, 2 );

/**
 * Post lists show the meta description when a post has no hand-written excerpt, so the
 * text people see on the blog matches what Google shows.
 *
 * @param string  $excerpt Excerpt.
 * @param WP_Post $post    Post.
 * @return string
 */
function tala_seo_excerpt_fallback( $excerpt, $post = null ) {
	if ( '' !== trim( (string) $excerpt ) || ! $post instanceof WP_Post || is_admin() ) {
		return $excerpt;
	}
	$description = tala_seo_post_description( $post->ID );
	return '' !== $description ? $description : $excerpt;
}
