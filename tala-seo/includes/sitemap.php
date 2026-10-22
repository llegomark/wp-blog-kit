<?php
/**
 * XML sitemap (WordPress core's /wp-sitemap.xml) and robots.txt.
 *
 * Google: sitemaps list canonical, indexable URLs; <lastmod> is used only when it is
 * consistently accurate; <priority> and <changefreq> are ignored (core doesn't add them).
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'wp_sitemaps_enabled', '__return_true' );

add_filter( 'wp_sitemaps_taxonomies', 'tala_seo_sitemap_taxonomies' );

/**
 * Leaves post formats out of the sitemap (they aren't real archive pages on a blog).
 *
 * @param WP_Taxonomy[] $taxonomies Taxonomies.
 * @return WP_Taxonomy[]
 */
function tala_seo_sitemap_taxonomies( $taxonomies ) {
	unset( $taxonomies['post_format'] );
	return $taxonomies;
}

add_filter( 'wp_sitemaps_posts_query_args', 'tala_seo_sitemap_skip_noindex' );

/**
 * Leaves posts marked "hide from search engines" out of the sitemap.
 *
 * @param array<string, mixed> $args WP_Query arguments.
 * @return array<string, mixed>
 */
function tala_seo_sitemap_skip_noindex( $args ) {
	$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sitemap pages are cached.
		'relation' => 'OR',
		array(
			'key'     => TALA_SEO_NOINDEX,
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => TALA_SEO_NOINDEX,
			'value'   => '1',
			'compare' => '!=',
		),
	);
	return $args;
}

add_filter( 'wp_sitemaps_posts_entry', 'tala_seo_sitemap_lastmod', 10, 2 );

/**
 * Adds <lastmod>: the post's last real update (minor edits don't change it, see publish-guard.php).
 *
 * @param array<string, string> $entry Sitemap entry.
 * @param WP_Post               $post  Post.
 * @return array<string, string>
 */
function tala_seo_sitemap_lastmod( $entry, $post ) {
	$modified = get_post_modified_time( DATE_W3C, true, $post );
	if ( is_string( $modified ) ) {
		$entry['lastmod'] = $modified;
	}
	return $entry;
}

add_filter( 'wp_sitemaps_posts_show_on_front_entry', 'tala_seo_sitemap_home_lastmod' );

/**
 * The home page (latest posts) changes whenever a post is published or updated.
 *
 * @param array<string, string> $entry Sitemap entry for the home page.
 * @return array<string, string>
 */
function tala_seo_sitemap_home_lastmod( $entry ) {
	$latest   = get_posts(
		array(
			'numberposts' => 1,
			'orderby'     => 'modified',
			'order'       => 'DESC',
			'post_type'   => 'post',
			'post_status' => 'publish',
		)
	);
	$modified = $latest ? get_post_modified_time( DATE_W3C, true, $latest[0] ) : false;
	if ( is_string( $modified ) ) {
		$entry['lastmod'] = $modified;
	}
	return $entry;
}

add_filter( 'robots_txt', 'tala_seo_robots_txt', 20, 2 );

/**
 * Builds robots.txt: core's rules (keep crawlers out of /wp-admin/ except admin-ajax.php) plus the
 * sitemap. Nothing else is disallowed: pages that must stay out of Google get a noindex tag,
 * which only works if crawlers may fetch the page. AI crawlers are welcome.
 *
 * @param string $output    Robots.txt content.
 * @param bool   $is_public Whether the site is visible to search engines.
 * @return string
 */
function tala_seo_robots_txt( $output, $is_public ) {
	if ( ! $is_public ) {
		return $output;
	}
	$sitemap = get_sitemap_url( 'index' );
	if ( $sitemap && false === stripos( $output, 'Sitemap:' ) ) {
		$output = rtrim( $output ) . "\n\nSitemap: " . esc_url_raw( $sitemap ) . "\n";
	}
	return $output;
}
