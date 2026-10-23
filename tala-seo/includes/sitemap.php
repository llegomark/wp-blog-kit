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

add_filter( 'wp_sitemaps_enabled', 'tala_seo_sitemaps_enabled' );

/**
 * The sitemap exists exactly when search engines may index the site (Settings → Reading), even if
 * a previously used SEO plugin switched WordPress's sitemap off. A hidden site publishes none.
 *
 * @return bool
 */
function tala_seo_sitemaps_enabled() {
	return (bool) get_option( 'blog_public' );
}

add_filter( 'wp_sitemaps_taxonomies', 'tala_seo_sitemap_taxonomies' );

/**
 * Leaves post formats out of the sitemap (they aren't real archive pages on a blog), and tags
 * altogether while no tag page has enough posts to be indexed (Settings → SEO).
 *
 * @param WP_Taxonomy[] $taxonomies Taxonomies.
 * @return WP_Taxonomy[]
 */
function tala_seo_sitemap_taxonomies( $taxonomies ) {
	unset( $taxonomies['post_format'] );
	foreach ( array_keys( $taxonomies ) as $taxonomy ) {
		if ( array() === tala_seo_indexable_term_ids( (string) $taxonomy ) ) {
			unset( $taxonomies[ $taxonomy ] );
		}
	}
	return $taxonomies;
}

add_filter( 'wp_sitemaps_taxonomies_query_args', 'tala_seo_sitemap_skip_thin_terms', 10, 2 );

/**
 * Lists only the category and tag pages that may be indexed: not the ones marked hidden, nor tag
 * pages with too few posts. Core counts the sitemap's pages with the same arguments, so paging
 * stays correct.
 *
 * @param array<string, mixed> $args     WP_Term_Query arguments.
 * @param string               $taxonomy Taxonomy.
 * @return array<string, mixed>
 */
function tala_seo_sitemap_skip_thin_terms( $args, $taxonomy ) {
	$ids = tala_seo_indexable_term_ids( $taxonomy );
	if ( $ids ) {
		$args['include'] = $ids;
	}
	return $args;
}

/**
 * IDs of the terms whose archives may be indexed, or null when all of them may (nothing to leave
 * out). A term is left out when it is marked hidden or, for tags, has too few posts.
 *
 * @param string $taxonomy Taxonomy.
 * @return int[]|null
 */
function tala_seo_indexable_term_ids( $taxonomy ) {
	static $cache = array();
	if ( ! array_key_exists( $taxonomy, $cache ) ) {
		$ids    = array();
		$hidden = 0;
		$terms  = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( tala_seo_is_thin_term( $term ) || '1' === tala_seo_term_value( $term, 'noindex' ) ) {
				++$hidden;
			} else {
				$ids[] = $term->term_id;
			}
		}
		$cache[ $taxonomy ] = $hidden ? $ids : null;
	}
	return $cache[ $taxonomy ];
}

add_filter( 'wp_sitemaps_posts_query_args', 'tala_seo_sitemap_skip_noindex' );

/**
 * Leaves out of the sitemap the posts marked "hide from search engines", and the ones that redirect
 * elsewhere (their address is no longer a page of their own).
 *
 * @param array<string, mixed> $args WP_Query arguments.
 * @return array<string, mixed>
 */
function tala_seo_sitemap_skip_noindex( $args ) {
	$args = tala_seo_add_meta_query(
		$args,
		array(
			'relation' => 'AND',
			tala_seo_meta_unset_query( TALA_SEO_NOINDEX, '1', '!=' ),
			tala_seo_meta_unset_query( TALA_SEO_REDIRECT, '', '=' ),
		)
	);
	// With %category% in the address, every URL reads the post's primary category from its meta:
	// load the meta of the whole page in one query instead of one per post.
	if ( str_contains( (string) get_option( 'permalink_structure' ), '%category%' ) ) {
		$args['update_post_meta_cache'] = true;
	}
	return $args;
}

add_filter( 'wp_sitemaps_users_query_args', 'tala_seo_sitemap_skip_hidden_authors' );

/**
 * Lists the author pages that have something to show: authors of published posts (author
 * archives list posts only, so someone who only wrote pages has an empty, noindexed one), without
 * the ones marked "hide from search engines".
 *
 * @param array<string, mixed> $args WP_User_Query arguments.
 * @return array<string, mixed>
 */
function tala_seo_sitemap_skip_hidden_authors( $args ) {
	$args['has_published_posts'] = array( 'post' );
	return tala_seo_add_meta_query( $args, tala_seo_meta_unset_query( TALA_SEO_NOINDEX, '1', '!=' ) );
}

/**
 * Adds a meta query clause to query arguments, keeping any clause another plugin set.
 *
 * @param array<string, mixed>     $args   WP_Query or WP_User_Query arguments.
 * @param array<int|string, mixed> $clause Meta query clause.
 * @return array<string, mixed>
 */
function tala_seo_add_meta_query( $args, $clause ) {
	$existing = $args['meta_query'] ?? array();
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- runs on sitemap requests only.
	$args['meta_query'] = is_array( $existing ) && $existing ? array(
		'relation' => 'AND',
		$existing,
		$clause,
	) : $clause;
	return $args;
}

/**
 * A meta query clause: the key isn't set, or its value compares as given (e.g. noindex != '1').
 *
 * @param string $key     Meta key.
 * @param string $value   Value.
 * @param string $compare Comparison operator.
 * @return array<int|string, mixed>
 */
function tala_seo_meta_unset_query( $key, $value, $compare ) {
	return array(
		'relation' => 'OR',
		array(
			'key'     => $key,
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => $key,
			'value'   => $value,
			'compare' => $compare,
		),
	);
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
	$latest   = array_first(
		get_posts(
			array(
				'numberposts' => 1,
				'orderby'     => 'modified',
				'order'       => 'DESC',
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		)
	);
	$modified = $latest ? get_post_modified_time( DATE_W3C, true, $latest ) : false;
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
