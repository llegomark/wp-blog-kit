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
 * Uses the SEO title of the post, category, tag or author (when set) in place of its name; the site
 * name stays at the end. A page whose name is the site name (say, an author named after the brand)
 * gets the name once, not "Name | Name".
 *
 * @param array<string, string> $parts Title parts.
 * @return array<string, string>
 */
function tala_seo_title_parts( $parts ) {
	$custom = tala_seo_current_custom_title();
	if ( '' !== $custom ) {
		$parts['title'] = $custom;
	}
	if ( isset( $parts['title'], $parts['site'] )
		&& 0 === strcasecmp( tala_seo_clean_text( $parts['title'] ), tala_seo_clean_text( $parts['site'] ) ) ) {
		unset( $parts['site'] );
	}
	return $parts;
}

/**
 * The SEO title set for the current post, term or author, or '' (then its own name is used).
 *
 * @return string
 */
function tala_seo_current_custom_title() {
	$queried = get_queried_object();
	if ( is_singular( tala_seo_post_types() ) && ! is_front_page() ) {
		return tala_seo_post_title( get_queried_object_id() );
	}
	if ( ( is_category() || is_tag() || is_tax() ) && $queried instanceof WP_Term ) {
		return tala_seo_term_value( $queried, 'title' );
	}
	if ( is_author() ) {
		return tala_seo_user_value( get_queried_object_id(), 'title' );
	}
	return '';
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
		return tala_seo_home_description();
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$term   = get_queried_object();
		$custom = $term instanceof WP_Term ? tala_seo_term_value( $term, 'description' ) : '';
		return '' !== $custom ? $custom : tala_seo_clean_text( term_description(), 60 );
	}
	if ( is_author() ) {
		$custom = tala_seo_user_value( get_queried_object_id(), 'description' );
		return '' !== $custom ? $custom : tala_seo_clean_text( get_the_author_meta( 'description', get_queried_object_id() ), 60 );
	}
	return '';
}

/**
 * The canonical URL of the current page, or '' when the page shouldn't be indexed.
 *
 * @return string
 */
function tala_seo_current_canonical() {
	if ( is_404() || is_search() || is_date() || is_preview() || tala_seo_is_hidden_archive() ) {
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
 * archives (duplicates of the post list), hidden or thin archives (see tala_seo_is_hidden_archive())
 * and posts the author marked as hidden.
 *
 * @param array<string, bool|string> $robots Robots directives.
 * @return array<string, bool|string>
 */
function tala_seo_robots( $robots ) {
	$hide = is_search() || is_404() || is_date() || tala_seo_is_hidden_archive();
	if ( is_singular() && tala_seo_post_noindex( get_queried_object_id() ) ) {
		$hide = true;
	}
	if ( $hide ) {
		$robots['noindex'] = true;
		// Links on the page stay followable, unless the whole site is hidden (Settings → Reading
		// adds "nofollow"); "nofollow, follow" would contradict itself.
		if ( empty( $robots['nofollow'] ) ) {
			$robots['follow'] = true;
		}
	}
	return $robots;
}

/**
 * Whether the current archive page should stay out of search results: a category, tag or author
 * marked hidden, a tag page with too few posts, or an archive that lists no posts at all.
 *
 * @return bool
 */
function tala_seo_is_hidden_archive() {
	$queried = get_queried_object();
	if ( ( is_category() || is_tag() || is_tax() ) && $queried instanceof WP_Term
		&& ( tala_seo_is_thin_term( $queried ) || '1' === tala_seo_term_value( $queried, 'noindex' ) ) ) {
		return true;
	}
	if ( is_author() && '1' === tala_seo_user_value( get_queried_object_id(), 'noindex' ) ) {
		return true;
	}
	return tala_seo_is_empty_archive();
}

/**
 * Whether the current page is an archive that lists no posts, such as an empty category. It counts
 * the posts the page shows, so a parent category that only lists its children's posts is not empty.
 * Never the home page, whose content can come from the template rather than the post list.
 *
 * @return bool
 */
function tala_seo_is_empty_archive() {
	global $wp_query;
	return is_archive() && ! is_front_page() && ! is_home() && $wp_query instanceof WP_Query && 0 === (int) $wp_query->post_count;
}

add_action( 'template_redirect', 'tala_seo_noindex_headers', 0 );

/**
 * Feeds and XML sitemaps stay usable by readers and search engines, but out of search results.
 * They have no <head> for a robots meta tag, so they get an X-Robots-Tag header instead.
 */
function tala_seo_noindex_headers(): void {
	$is_sitemap = '' !== (string) get_query_var( 'sitemap' ) || '' !== (string) get_query_var( 'sitemap-stylesheet' );
	if ( ( is_feed() || $is_sitemap ) && ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, follow', true );
	}
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

	// Fediverse (Mastodon): credits the author when an article is shared there, and lets the author's
	// profile verify that their author page belongs to them.
	if ( is_singular( 'post' ) && $post instanceof WP_Post ) {
		$handle = tala_seo_sanitize_fediverse_handle( get_user_meta( (int) $post->post_author, 'tala_seo_fediverse', true ) );
		if ( '' !== $handle ) {
			$tags[] = sprintf( '<meta name="fediverse:creator" content="%s">', esc_attr( $handle ) );
		}
	}
	if ( is_author() ) {
		$profile = esc_url_raw( (string) get_the_author_meta( 'tala_fediverse_url', get_queried_object_id() ), array( 'https' ) );
		if ( '' !== $profile ) {
			$tags[] = sprintf( '<link rel="me" href="%s">', esc_url( $profile ) );
		}
	}

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
