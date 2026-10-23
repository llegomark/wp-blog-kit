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
		$any = get_terms(
			array_merge(
				array(
					'taxonomy'   => (string) $taxonomy,
					'hide_empty' => true,
					'fields'     => 'ids',
					'number'     => 1,
				),
				tala_seo_indexable_term_args( (string) $taxonomy )
			)
		);
		if ( ! $any ) {
			unset( $taxonomies[ $taxonomy ] );
		}
	}
	return $taxonomies;
}

add_filter( 'wp_sitemaps_taxonomies_query_args', 'tala_seo_sitemap_skip_thin_terms', 10, 2 );

/**
 * Lists exactly the category and tag pages that may be indexed: not the ones marked hidden, nor tag
 * pages with too few posts, but parent categories whose posts are all in subcategories (their page
 * lists those posts, so it isn't empty; WordPress would only count a term's own posts). Core counts
 * the sitemap's pages with the same arguments, so paging stays correct.
 *
 * @param array<string, mixed> $args     WP_Term_Query arguments.
 * @param string               $taxonomy Taxonomy.
 * @return array<string, mixed>
 */
function tala_seo_sitemap_skip_thin_terms( $args, $taxonomy ) {
	if ( is_taxonomy_hierarchical( $taxonomy ) ) {
		$args['hierarchical'] = true;
	}
	return array_merge( $args, tala_seo_indexable_term_args( $taxonomy ) );
}

/**
 * Term query arguments that leave out the category and tag pages that may not be indexed: the ones
 * marked hidden, and tag pages with too few posts (applied by tala_seo_indexable_terms_clause()).
 * The query itself does the leaving out, so a request reads only the terms it lists, however many
 * a site has.
 *
 * @param string $taxonomy Taxonomy.
 * @return array<string, mixed>
 */
function tala_seo_indexable_term_args( $taxonomy ) {
	return array(
		'tala_seo_hidden'    => tala_seo_hidden_term_ids( $taxonomy ),
		'tala_seo_min_posts' => tala_seo_min_posts( $taxonomy ),
	);
}

/**
 * IDs of a taxonomy's terms marked "hide from search engines", here or in another SEO plugin. Only
 * terms that carry such a mark are read: as many as editors marked, not as many as the site has.
 *
 * @param string $taxonomy Taxonomy.
 * @return int[]
 */
function tala_seo_hidden_term_ids( $taxonomy ) {
	global $wp_query;
	/**
	 * Results by main query and taxonomy: core asks again for every page of a sitemap.
	 *
	 * @var WeakMap<WP_Query, array<string, int[]>> $memo
	 */
	static $memo = new WeakMap();
	$query       = $wp_query instanceof WP_Query ? $wp_query : new WP_Query();
	$known       = $memo[ $query ] ?? array();
	if ( ! array_key_exists( $taxonomy, $known ) ) {
		$marked = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- runs on sitemap requests only.
				'meta_query' => array_merge(
					array( 'relation' => 'OR' ),
					array_map(
						static fn( $key ) => array(
							'key'     => $key,
							'compare' => 'EXISTS',
						),
						tala_seo_term_noindex_keys()
					)
				),
			)
		);
		$yoast  = get_option( 'wpseo_taxonomy_meta' );
		$ids    = array_merge(
			is_array( $marked ) ? array_map( intval( ... ), $marked ) : array(),
			is_array( $yoast ) && is_array( $yoast[ $taxonomy ] ?? null ) ? array_map( intval( ... ), array_keys( $yoast[ $taxonomy ] ) ) : array()
		);
		$hidden = array();
		foreach ( array_unique( $ids ) as $id ) {
			$term = get_term( $id, $taxonomy );
			if ( $term instanceof WP_Term && '1' === tala_seo_term_value( $term, 'noindex' ) ) {
				$hidden[] = $id;
			}
		}
		$known[ $taxonomy ] = $hidden;
		$memo[ $query ]     = $known;
	}
	return $known[ $taxonomy ];
}

add_filter( 'terms_clauses', 'tala_seo_indexable_terms_clause', 10, 3 );

/**
 * For the sitemap's term queries (tala_seo_indexable_term_args()): leaves out the terms marked
 * hidden, and terms with fewer posts than their pages need.
 *
 * @param array<string, string> $clauses    Query clauses.
 * @param string[]              $taxonomies Taxonomies.
 * @param array<string, mixed>  $args       Query arguments.
 * @return array<string, string>
 */
function tala_seo_indexable_terms_clause( $clauses, $taxonomies, $args ) {
	global $wpdb;
	$hidden = array_map( intval( ... ), (array) ( $args['tala_seo_hidden'] ?? array() ) );
	if ( $hidden ) {
		$clauses['where'] .= $wpdb->prepare( ' AND t.term_id NOT IN (' . implode( ',', array_fill( 0, count( $hidden ), '%d' ) ) . ')', $hidden );
	}
	$min = (int) ( $args['tala_seo_min_posts'] ?? 0 );
	if ( $min > 1 ) {
		$clauses['where'] .= $wpdb->prepare( ' AND tt.count >= %d', $min );
	}
	return $clauses;
}

add_filter( 'wp_sitemaps_posts_query_args', 'tala_seo_sitemap_skip_noindex' );

/**
 * Leaves out of the sitemap the posts marked "hide from search engines", and the ones that redirect
 * elsewhere (their address is no longer a page of their own). The exclusion itself is added by
 * tala_seo_sitemap_where().
 *
 * @param array<string, mixed> $args WP_Query arguments.
 * @return array<string, mixed>
 */
function tala_seo_sitemap_skip_noindex( $args ) {
	$args['tala_seo_sitemap'] = true;
	// With %category% in the address, every URL reads the post's primary category from its meta:
	// load the meta of the whole page in one query instead of one per post.
	if ( str_contains( (string) get_option( 'permalink_structure' ), '%category%' ) ) {
		$args['update_post_meta_cache'] = true;
	}
	return $args;
}

add_filter( 'posts_where', 'tala_seo_sitemap_where', 10, 2 );

/**
 * For the sitemap's post queries: leaves out posts whose noindex is set or that redirect. One
 * indexed lookup per post, where meta query clauses would join a post's meta rows with each other.
 *
 * @param string   $where WHERE clause.
 * @param WP_Query $query Query.
 * @return string
 */
function tala_seo_sitemap_where( $where, $query ) {
	if ( ! $query->get( 'tala_seo_sitemap' ) ) {
		return $where;
	}
	global $wpdb;
	return $where . $wpdb->prepare(
		" AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS tala_seo_meta WHERE tala_seo_meta.post_id = {$wpdb->posts}.ID AND ( ( tala_seo_meta.meta_key = %s AND tala_seo_meta.meta_value = '1' ) OR ( tala_seo_meta.meta_key = %s AND tala_seo_meta.meta_value <> '' ) ) )",
		TALA_SEO_NOINDEX,
		TALA_SEO_REDIRECT
	);
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
