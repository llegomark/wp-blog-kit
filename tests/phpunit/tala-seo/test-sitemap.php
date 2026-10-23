<?php
/**
 * The XML sitemap (WordPress's own, filtered) and robots.txt.
 *
 * @package TalaTests
 */

/**
 * Sitemap and robots.txt.
 */
class Test_Tala_SEO_Sitemap extends Tala_SEO_Test_Case {

	/**
	 * Readable URLs, as on a real site.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_pretty_permalinks();
	}

	/**
	 * The URLs a sitemap lists, for a fresh request.
	 *
	 * @param string $provider posts, taxonomies or users.
	 * @param string $subtype  Post type or taxonomy.
	 * @return string[]
	 */
	private function urls( string $provider, string $subtype = '' ): array {
		$this->go_to( home_url( '/' ) );
		$sitemap = wp_sitemaps_get_server()->registry->get_provider( $provider );
		$this->assertInstanceOf( WP_Sitemaps_Provider::class, $sitemap );
		return wp_list_pluck( $sitemap->get_url_list( 1, $subtype ), 'loc' );
	}

	/**
	 * The taxonomies that get a sitemap, for a fresh request.
	 *
	 * @return string[]
	 */
	private function taxonomy_sitemaps(): array {
		$this->go_to( home_url( '/' ) );
		return array_keys( wp_sitemaps_get_server()->registry->get_provider( 'taxonomies' )->get_object_subtypes() );
	}

	/**
	 * A sitemap exists exactly while search engines may index the site.
	 */
	public function test_sitemap_follows_reading_settings(): void {
		$this->assertTrue( wp_sitemaps_get_server()->sitemaps_enabled() );
		update_option( 'blog_public', '0' );
		$this->assertFalse( wp_sitemaps_get_server()->sitemaps_enabled() );
	}

	/**
	 * Robots.txt points to the sitemap and blocks nothing else; a hidden site's is left alone.
	 */
	public function test_robots_txt(): void {
		$robots = get_echo( 'do_robots' );
		$this->assertStringContainsString( 'Sitemap: ' . home_url( '/wp-sitemap.xml' ), $robots );
		$this->assertSame( array( 'Disallow: /wp-admin/' ), array_values( preg_grep( '/^Disallow:/', explode( "\n", $robots ) ) ), 'Only core\'s rule; AI crawlers are welcome.' );

		update_option( 'blog_public', '0' );
		$this->assertStringNotContainsString( 'Sitemap:', get_echo( 'do_robots' ) );
	}

	/**
	 * Posts marked hidden, and posts that redirect elsewhere, leave the sitemap.
	 */
	public function test_hidden_and_redirected_posts_are_left_out(): void {
		$kept       = $this->publish_post();
		$hidden     = $this->publish_post( array( 'meta_input' => array( TALA_SEO_NOINDEX => '1' ) ) );
		$redirected = $this->publish_post( array( 'meta_input' => array( TALA_SEO_REDIRECT => 'https://example.com/merged/' ) ) );
		$urls       = $this->urls( 'posts', 'post' );
		$this->assertContains( get_permalink( $kept ), $urls );
		$this->assertNotContains( get_permalink( $hidden ), $urls );
		$this->assertNotContains( get_permalink( $redirected ), $urls );
	}

	/**
	 * Leaving those posts out joins no post meta into the sitemap's query, so a post's other custom
	 * fields don't multiply its cost: one indexed lookup per post.
	 */
	public function test_post_sitemap_query_joins_no_meta(): void {
		global $wpdb;
		$this->publish_post( array( 'meta_input' => array( TALA_SEO_NOINDEX => '1' ) ) );
		$queries = array();
		$capture = static function ( $sql ) use ( &$queries ) {
			$queries[] = $sql;
			return $sql;
		};
		add_filter( 'posts_request', $capture );
		$this->urls( 'posts', 'post' );
		remove_filter( 'posts_request', $capture );
		$sitemap = array_values( preg_grep( '/' . preg_quote( TALA_SEO_NOINDEX, '/' ) . '/', $queries ) );
		$this->assertCount( 1, $sitemap, 'The sitemap query still leaves out hidden posts.' );
		$this->assertStringNotContainsString( "JOIN {$wpdb->postmeta}", $sitemap[0] );
	}

	/**
	 * Tag pages need the minimum number of posts (2 by default; 0 lists them all).
	 */
	public function test_thin_tags(): void {
		$this->publish_post( array( 'tags_input' => array( 'tala-thin', 'tala-big' ) ) );
		$this->publish_post( array( 'tags_input' => array( 'tala-big' ) ) );
		$thin = get_term_link( 'tala-thin', 'post_tag' );
		$big  = get_term_link( 'tala-big', 'post_tag' );

		$urls = $this->urls( 'taxonomies', 'post_tag' );
		$this->assertContains( $big, $urls );
		$this->assertNotContains( $thin, $urls );

		$this->set_setting( 'tag_min_posts', 0 );
		$this->assertContains( $thin, $this->urls( 'taxonomies', 'post_tag' ) );

		$this->set_setting( 'tag_min_posts', 5 );
		$this->assertNotContains( 'post_tag', $this->taxonomy_sitemaps(), 'No tag page qualifies: no tag sitemap at all.' );
	}

	/**
	 * However many tags a site has, a sitemap request loads at most a page of them, as WordPress's own
	 * sitemap does: tags with too few posts are left out by the query, not one by one.
	 */
	public function test_tag_sitemap_loads_a_page_of_tags_at_most(): void {
		add_filter( 'wp_sitemaps_max_urls', static fn() => 5 );
		$this->publish_post( array( 'tags_input' => array_map( static fn( $i ) => "tala-few-$i", range( 1, 20 ) ) ) );
		foreach ( array( 1, 2 ) as $copy ) {
			$this->publish_post( array( 'tags_input' => array( 'tala-many-1', 'tala-many-2', 'tala-many-3' ) ) );
		}
		$this->go_to( home_url( '/' ) );
		$largest = 0;
		$measure = static function ( $terms ) use ( &$largest ) {
			$largest = max( $largest, is_array( $terms ) ? count( $terms ) : 0 );
			return $terms;
		};
		add_filter( 'get_terms', $measure );
		$sitemap = wp_sitemaps_get_server()->registry->get_provider( 'taxonomies' );
		$this->assertArrayHasKey( 'post_tag', $sitemap->get_object_subtypes() );
		$urls = wp_list_pluck( $sitemap->get_url_list( 1, 'post_tag' ), 'loc' );
		remove_filter( 'get_terms', $measure );
		$this->assertCount( 3, $urls, 'The tags with enough posts' );
		$this->assertLessThanOrEqual( 5, $largest, 'Tags loaded at once' );
	}

	/**
	 * Categories: empty and hidden ones leave the sitemap; a parent whose posts are all in a
	 * subcategory is listed, as its page is indexed (WordPress alone would leave it out).
	 */
	public function test_categories(): void {
		$empty  = self::factory()->category->create();
		$parent = self::factory()->category->create();
		$child  = self::factory()->category->create( array( 'parent' => $parent ) );
		$hidden = self::factory()->category->create();
		$this->publish_post( array( 'post_category' => array( $child, $hidden ) ) );
		update_term_meta( $hidden, TALA_SEO_NOINDEX, '1' );

		$urls = $this->urls( 'taxonomies', 'category' );
		$this->assertContains( get_term_link( $child ), $urls );
		$this->assertContains( get_term_link( $parent ), $urls );
		$this->assertNotContains( get_term_link( $empty ), $urls );
		$this->assertNotContains( get_term_link( $hidden ), $urls );
		$this->assertNotContains( 'post_format', $this->taxonomy_sitemaps() );
	}

	/**
	 * Author pages: authors of posts, unless marked hidden; not authors of pages only.
	 */
	public function test_authors(): void {
		$writer    = self::factory()->user->create( array( 'role' => 'author' ) );
		$hidden    = self::factory()->user->create( array( 'role' => 'author' ) );
		$page_only = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->publish_post( array( 'post_author' => $writer ) );
		$this->publish_post( array( 'post_author' => $hidden ) );
		$this->publish_post(
			array(
				'post_type'   => 'page',
				'post_author' => $page_only,
			)
		);
		update_user_meta( $hidden, TALA_SEO_NOINDEX, '1' );

		$urls = $this->urls( 'users' );
		$this->assertContains( get_author_posts_url( $writer ), $urls );
		$this->assertNotContains( get_author_posts_url( $hidden ), $urls );
		$this->assertNotContains( get_author_posts_url( $page_only ), $urls );
	}

	/**
	 * Lastmod: a post's last update, and for the home page the latest update of any post.
	 */
	public function test_lastmod(): void {
		$older = $this->publish_post( array( 'post_date' => '2026-01-10 08:00:00' ) );
		$newer = $this->publish_post( array( 'post_date' => '2026-02-20 09:30:00' ) );

		$this->go_to( home_url( '/' ) );
		$entries = wp_sitemaps_get_server()->registry->get_provider( 'posts' )->get_url_list( 1, 'post' );
		$by_url  = array_column( $entries, 'lastmod', 'loc' );
		$this->assertSame( get_post_modified_time( DATE_W3C, true, $older ), $by_url[ get_permalink( $older ) ] );

		$home = wp_sitemaps_get_server()->registry->get_provider( 'posts' )->get_url_list( 1, 'page' )[0];
		$this->assertSame( home_url( '/' ), $home['loc'] );
		$this->assertSame( get_post_modified_time( DATE_W3C, true, $newer ), $home['lastmod'] );
	}

	/**
	 * "Hide from search engines" and "minor edit" are yes/no fields however they are written (a
	 * custom field, XML-RPC), so a hidden post is hidden in its head and in the sitemap alike.
	 */
	public function test_switches_are_booleans(): void {
		tala_seo_guard_setup(); // Registers the minor-edit field, as on a site.
		$post = $this->publish_post();
		update_post_meta( $post, TALA_SEO_NOINDEX, 'yes' );
		update_post_meta( $post, TALA_SEO_MINOR_EDIT, 'yes' );
		$this->assertSame( '1', get_post_meta( $post, TALA_SEO_NOINDEX, true ) );
		$this->assertSame( '1', get_post_meta( $post, TALA_SEO_MINOR_EDIT, true ) );
		$this->assertNotContains( get_permalink( $post ), $this->urls( 'posts', 'post' ) );
	}


	/**
	 * Posts hidden by a setting from Yoast SEO, Rank Math, The SEO Framework or SEOPress get noindex
	 * on the page itself, so they leave the sitemap too, until `wp tala-seo import` copies the setting.
	 */
	public function test_posts_hidden_by_another_seo_plugin_are_left_out(): void {
		$hidden = array(
			'yoast'    => $this->publish_post( array( 'meta_input' => array( '_yoast_wpseo_meta-robots-noindex' => '1' ) ) ),
			'rank'     => $this->publish_post( array( 'meta_input' => array( 'rank_math_robots' => array( 'noindex', 'nofollow' ) ) ) ),
			'tsf'      => $this->publish_post( array( 'meta_input' => array( '_genesis_noindex' => '1' ) ) ),
			'seopress' => $this->publish_post( array( 'meta_input' => array( '_seopress_robots_index' => 'yes' ) ) ),
		);
		$kept   = array(
			'plain'  => $this->publish_post(),
			'yoast'  => $this->publish_post( array( 'meta_input' => array( '_yoast_wpseo_meta-robots-noindex' => '2' ) ) ), // "Index".
			'rank'   => $this->publish_post( array( 'meta_input' => array( 'rank_math_robots' => array( 'index', 'nofollow' ) ) ) ),
			'tsf'    => $this->publish_post( array( 'meta_input' => array( '_genesis_noindex' => '-1' ) ) ), // "Always index".
			'custom' => $this->publish_post( array( 'meta_input' => array( 'noindex' => '1' ) ) ), // Not an SEO plugin's field.
		);
		$urls   = $this->urls( 'posts', 'post' );
		foreach ( $hidden as $plugin => $post ) {
			$this->assertTrue( tala_seo_post_noindex( $post ), "$plugin: the page is noindex" );
			$this->assertNotContains( get_permalink( $post ), $urls, "$plugin: out of the sitemap" );
		}
		foreach ( $kept as $what => $post ) {
			$this->assertContains( get_permalink( $post ), $urls, $what );
		}
	}
}
