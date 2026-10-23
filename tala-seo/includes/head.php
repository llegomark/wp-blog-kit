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
		// WordPress titles the front page "Site | Tagline"; a static front page's SEO title takes
		// the place of the page's title, with the site name at the end as on every other page.
		if ( isset( $parts['tagline'] ) ) {
			unset( $parts['tagline'] );
			$parts['site'] = get_bloginfo( 'name', 'display' );
		}
	}
	if ( isset( $parts['title'], $parts['site'] )
		&& 0 === strcasecmp( tala_seo_clean_text( $parts['title'] ), tala_seo_clean_text( $parts['site'] ) ) ) {
		unset( $parts['site'] );
	}
	return $parts;
}

/**
 * The post or page the current request shows, whose SEO fields describe it: a post, a page (the
 * static front page included) or the page that lists the posts (Settings → Reading, where the
 * request is the post list but the page's title, description and "hide" switch still apply). 0 on
 * every other page.
 *
 * @return int Post ID or 0.
 */
function tala_seo_current_post_id() {
	if ( is_singular() || ( is_home() && get_queried_object() instanceof WP_Post ) ) {
		return get_queried_object_id();
	}
	return 0;
}

/**
 * The SEO title set for the current post, term or author, or '' (then its own name is used).
 *
 * @return string
 */
function tala_seo_current_custom_title() {
	$queried = get_queried_object();
	$post_id = tala_seo_current_post_id();
	if ( $post_id && in_array( (string) get_post_type( $post_id ), tala_seo_post_types(), true ) ) {
		return tala_seo_post_title( $post_id );
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
	$id = tala_seo_current_post_id();
	if ( $id ) {
		$text = tala_seo_post_description( $id );
		// A password-protected post shows its excerpt only once the password is entered.
		if ( '' === $text && has_excerpt( $id ) && ! post_password_required( $id ) ) {
			$text = tala_seo_clean_text( (string) get_post( $id )?->post_excerpt );
		}
		// A static front page or posts page without a description of its own is still the home page.
		if ( '' === $text && ( is_front_page() || is_home() ) ) {
			$text = tala_seo_home_description();
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
		// Another plugin's field: checked here, as nothing checked it when it was saved.
		$custom = tala_seo_sanitize_url( tala_seo_legacy_value( $post->ID, 'canonical' ) );
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
	$hide    = is_search() || is_404() || is_date() || tala_seo_is_hidden_archive();
	$post_id = tala_seo_current_post_id();
	if ( $post_id && tala_seo_post_noindex( $post_id ) ) {
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
 * The HTML comments that open and close what Tala SEO prints in <head>, so anyone viewing the source
 * sees which tags the plugin wrote: the meta tags first (tala_seo_print_head()), then the JSON-LD
 * (schema.php), then the closing comment. Both are left out when the "tala_seo_head_comment"
 * filter returns false.
 *
 * @param string $which 'open' or 'close'.
 * @return string The comment, or '' when it is switched off.
 */
function tala_seo_head_comment( string $which ): string {
	if ( ! apply_filters( 'tala_seo_head_comment', true ) ) {
		return '';
	}
	if ( 'close' === $which ) {
		return '<!-- / Tala SEO plugin. -->';
	}
	return '<!-- This site is optimized with the Tala SEO plugin v' . TALA_SEO_VERSION . ' - https://edventure.com.ph -->';
}

/**
 * Prints the closing comment, after the JSON-LD.
 */
function tala_seo_print_head_end(): void {
	$comment = tala_seo_head_comment( 'close' );
	if ( '' !== $comment ) {
		echo $comment . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed comment.
	}
}

/**
 * Prints the opening comment, then the meta description, canonical link and Open Graph tags.
 */
function tala_seo_print_head(): void {
	$tags = array_filter( array( tala_seo_head_comment( 'open' ) ) );
	// Search engines look for their verification tag on the home page (first page) only.
	if ( is_front_page() && ! is_paged() ) {
		$codes = tala_seo_settings()['verify'];
		foreach ( tala_seo_verification_services() as $service => $details ) {
			if ( '' !== ( $codes[ $service ] ?? '' ) ) {
				$tags[] = sprintf( '<meta name="%s" content="%s">', esc_attr( $details['meta'] ), esc_attr( $codes[ $service ] ) );
			}
		}
	}
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
	$image    = tala_seo_share_image( $image_id );
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
	// Only on the author page of someone who published: an empty one can't vouch for a profile.
	if ( is_author() && ! tala_seo_is_empty_archive() ) {
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
	$custom = tala_seo_current_custom_title();
	if ( '' !== $custom ) {
		return $custom;
	}
	$id = tala_seo_current_post_id();
	// The front page is named after the site ("Site | Tagline"), not after its page.
	if ( $id && ! is_front_page() ) {
		return tala_seo_clean_text( get_the_title( $id ) );
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
	$post_id = tala_seo_current_post_id();
	if ( $post_id ) {
		$id = (int) get_post_thumbnail_id( $post_id );
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
	// The description is plain text (entities decoded); an excerpt is HTML.
	return '' !== $description ? esc_html( $description ) : $excerpt;
}
