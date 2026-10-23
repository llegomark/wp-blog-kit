<?php
/**
 * Structured data (JSON-LD) as Google Search Central documents it: WebSite and Organization on the
 * home page only, BlogPosting on posts, ProfilePage on author pages, and a BreadcrumbList that
 * matches the visible trail.
 *
 * @package TalaTests
 */

/**
 * Structured data.
 */
class Test_Tala_SEO_Schema extends Tala_SEO_Test_Case {

	/**
	 * Readable URLs, as on a real site.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_pretty_permalinks();
		update_option( 'blogname', 'Guro Notes' );
		update_option( 'blogdescription', 'Practical guides for teachers' );
	}

	/**
	 * The home page: WebSite and Organization, the Organization described like the home page.
	 */
	public function test_home_page(): void {
		$this->set_setting( 'same_as', array( 'https://www.facebook.com/guronotes' ) );
		update_option( 'site_icon', self::$image_id );
		$this->go_to( home_url( '/' ) );
		$nodes = $this->schema();
		$this->assertSame( array( 'WebSite', 'Organization' ), array_keys( $nodes ) );
		$this->assertSame( 'Guro Notes', $nodes['WebSite']['name'] );
		$this->assertSame( array( '@id' => home_url( '/#publisher' ) ), $nodes['WebSite']['publisher'] );
		$this->assertSame( 'Practical guides for teachers', $nodes['Organization']['description'] );
		$this->assertSame( array( 'https://www.facebook.com/guronotes' ), $nodes['Organization']['sameAs'] );
		$this->assertSame( 'ImageObject', $nodes['Organization']['logo']['@type'], 'The site icon stands in for a logo.' );

		$this->set_setting( 'publisher_type', 'person' );
		$this->set_setting( 'publisher_name', 'Tala Tester' );
		$this->go_to( home_url( '/' ) );
		$nodes = $this->schema();
		$this->assertSame( 'Tala Tester', $nodes['Person']['name'] );
		$this->assertArrayHasKey( 'image', $nodes['Person'], 'A person has an image, not a logo.' );
	}

	/**
	 * WebSite and Organization are on the home page (its first page) only; search results and 404s
	 * have no markup.
	 */
	public function test_site_nodes_only_on_the_home_page(): void {
		update_option( 'posts_per_page', 1 );
		$this->publish_post();
		$this->go_to( home_url( '/page/2/' ) );
		$this->assertArrayNotHasKey( 'WebSite', $this->schema(), 'Not on page 2 of the home page' );

		$post = $this->publish_post();
		$this->go_to( get_permalink( $post ) );
		$this->assertArrayNotHasKey( 'WebSite', $this->schema() );
		$this->assertArrayNotHasKey( 'Organization', $this->schema() );

		$this->go_to( home_url( '/?s=lesson' ) );
		$this->assertSame( array(), tala_seo_schema_nodes() );
		$this->go_to( home_url( '/no-such-page/' ) );
		$this->assertSame( array(), tala_seo_schema_nodes() );
	}

	/**
	 * Posts: BlogPosting with dates, description, author (without photo) and Google's three crops.
	 */
	public function test_blog_posting(): void {
		$author = self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Tala Tester',
			)
		);
		update_user_meta( $author, 'tala_tiktok', 'https://www.tiktok.com/@tala' );
		update_user_meta( $author, 'tala_x', 'not a link' );
		$post = $this->publish_post(
			array(
				'post_title'  => 'Planning a lesson',
				'post_author' => $author,
			)
		);
		$this->go_to( get_permalink( $post ) );
		$article = $this->schema()['BlogPosting'];
		$this->assertSame( 'Planning a lesson', $article['headline'] );
		$this->assertSame( 'A test description.', $article['description'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/', $article['datePublished'] );
		$this->assertSame( 'Tala Tester', $article['author'][0]['name'] );
		$this->assertSame( array( 'https://www.tiktok.com/@tala' ), $article['author'][0]['sameAs'] );
		$this->assertArrayNotHasKey( 'image', $article['author'][0], 'The photo is for the author page.' );

		$this->assertCount( 3, $article['image'] );
		$this->assertStringEndsWith( '-1200x675.jpg', $article['image'][0], '16:9' );
		$this->assertStringEndsWith( '-900x675.jpg', $article['image'][1], '4:3' );
		$this->assertStringEndsWith( '-675x675.jpg', $article['image'][2], '1:1' );
	}

	/**
	 * Author pages: ProfilePage with the author's bio, profiles and real photo.
	 */
	public function test_profile_page(): void {
		$author = self::factory()->user->create(
			array(
				'role'        => 'author',
				'description' => 'Teaches Grade 5.',
			)
		);
		update_user_meta( $author, 'tala_fediverse_url', 'https://mastodon.social/@tala' );
		update_user_meta( $author, 'tala_seo_photo', self::$image_id );
		$this->publish_post( array( 'post_author' => $author ) );
		$this->go_to( get_author_posts_url( $author ) );
		$person = $this->schema()['ProfilePage']['mainEntity'];
		$this->assertSame( 'Teaches Grade 5.', $person['description'] );
		$this->assertSame( array( 'https://mastodon.social/@tala' ), $person['sameAs'] );
		$this->assertStringEndsWith( '-300x169.jpg', $person['image'], 'The medium size of the photo' );

		delete_user_meta( $author, 'tala_seo_photo' );
		$this->go_to( get_author_posts_url( $author ) );
		$this->assertArrayNotHasKey( 'image', $this->schema()['ProfilePage']['mainEntity'], 'Never a placeholder image' );
	}

	/**
	 * The BreadcrumbList matches the visible trail: Home › Category › Post, the last without a URL.
	 */
	public function test_breadcrumbs(): void {
		$parent = self::factory()->category->create( array( 'name' => 'Guides' ) );
		$child  = self::factory()->category->create(
			array(
				'name'   => 'Classroom',
				'parent' => $parent,
			)
		);
		$post   = $this->publish_post(
			array(
				'post_title'    => 'Planning a lesson',
				'post_category' => array( $child ),
			)
		);
		$this->go_to( get_permalink( $post ) );
		$this->show_breadcrumbs();
		$items = $this->schema()['BreadcrumbList']['itemListElement'];
		$this->assertSame( array( 'Home', 'Guides', 'Classroom', 'Planning a lesson' ), wp_list_pluck( $items, 'name' ) );
		$this->assertSame( array( 1, 2, 3, 4 ), wp_list_pluck( $items, 'position' ) );
		$this->assertSame( get_term_link( $child ), $items[2]['item'] );
		$this->assertArrayNotHasKey( 'item', $items[3], 'The current page needs no URL.' );

		$this->go_to( home_url( '/' ) );
		$this->show_breadcrumbs();
		$this->assertArrayNotHasKey( 'BreadcrumbList', $this->schema(), 'Not on the home page' );
	}

	/**
	 * Breadcrumb markup describes the trail readers see: none on a page (or with a theme) that doesn't
	 * show the Breadcrumbs block.
	 */
	public function test_breadcrumbs_only_where_shown(): void {
		$post = $this->publish_post( array( 'post_category' => array( self::factory()->category->create() ) ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertArrayNotHasKey( 'BreadcrumbList', $this->schema() );
		$this->assertSame( '', get_echo( 'tala_seo_print_late_breadcrumbs' ) );

		$this->go_to( get_permalink( $post ) );
		$this->show_breadcrumbs();
		$this->assertArrayHasKey( 'BreadcrumbList', $this->schema() );
	}

	/**
	 * A block theme renders the page, breadcrumbs included, before <head>: the BreadcrumbList is in
	 * the graph there, once. A classic theme renders its content after <head>: the BreadcrumbList of
	 * a trail shown there comes at the end of the page, once.
	 */
	public function test_breadcrumbs_in_head_or_at_the_end(): void {
		$post   = $this->publish_post( array( 'post_category' => array( self::factory()->category->create() ) ) );
		$script = '/<script type="application\/ld\+json">\{"@context":"https:\/\/schema.org","@graph":\[\{"@type":"BreadcrumbList"/';

		$this->go_to( get_permalink( $post ) );
		$this->show_breadcrumbs();
		$this->assertStringContainsString( '"BreadcrumbList"', get_echo( 'tala_seo_print_schema' ) );
		$this->assertSame( '', get_echo( 'tala_seo_print_late_breadcrumbs' ), 'Not twice' );

		$this->go_to( get_permalink( $post ) );
		$this->assertStringNotContainsString( 'BreadcrumbList', get_echo( 'tala_seo_print_schema' ) );
		$this->show_breadcrumbs();
		$this->assertNotFalse( has_action( 'wp_footer', 'tala_seo_print_late_breadcrumbs' ), 'Printed at the end of the page' );
		$this->assertMatchesRegularExpression( $script, get_echo( 'tala_seo_print_late_breadcrumbs' ) );
		$this->assertSame( '', get_echo( 'tala_seo_print_late_breadcrumbs' ), 'Once' );
	}

	/**
	 * The primary category leads the breadcrumbs: chosen here, or in a previous SEO plugin, as long
	 * as the post is still in it; otherwise the first category that isn't the default one.
	 */
	public function test_primary_category(): void {
		$first  = self::factory()->category->create( array( 'name' => 'Alpha' ) );
		$second = self::factory()->category->create( array( 'name' => 'Beta' ) );
		$other  = self::factory()->category->create( array( 'name' => 'Gamma' ) );
		$post   = $this->publish_post( array( 'post_category' => array( $first, $second ) ) );

		$crumbs = function () use ( $post ): array {
			$this->go_to( get_permalink( $post ) );
			$this->show_breadcrumbs();
			return wp_list_pluck( $this->schema()['BreadcrumbList']['itemListElement'], 'name' );
		};
		$this->assertContains( 'Alpha', $crumbs() );

		update_post_meta( $post, TALA_SEO_PRIMARY_CATEGORY, $second );
		$this->assertContains( 'Beta', $crumbs() );
		$this->assertNotContains( 'Alpha', $crumbs() );

		update_post_meta( $post, TALA_SEO_PRIMARY_CATEGORY, $other );
		$this->assertContains( 'Alpha', $crumbs(), 'A category the post is not in is ignored.' );

		delete_post_meta( $post, TALA_SEO_PRIMARY_CATEGORY );
		update_post_meta( $post, '_yoast_wpseo_primary_category', $second );
		$this->assertContains( 'Beta', $crumbs(), "A previous plugin's choice is honoured." );
	}

	/**
	 * The primary category also goes into %category% addresses, but only when someone chose one.
	 */
	public function test_primary_category_in_permalinks(): void {
		$this->set_pretty_permalinks( '/%category%/%postname%/' );
		$first  = self::factory()->category->create( array( 'slug' => 'alpha' ) );
		$second = self::factory()->category->create( array( 'slug' => 'beta' ) );
		$post   = $this->publish_post(
			array(
				'post_name'     => 'primary-test',
				'post_category' => array( $first, $second ),
			)
		);
		$this->assertSame( home_url( '/alpha/primary-test/' ), get_permalink( $post ), "WordPress's own pick" );
		update_post_meta( $post, TALA_SEO_PRIMARY_CATEGORY, $second );
		$this->assertSame( home_url( '/beta/primary-test/' ), get_permalink( $post ) );
	}

	/**
	 * Text can't close the <script> element the JSON-LD sits in.
	 */
	public function test_json_ld_is_safe_to_print(): void {
		$post = $this->publish_post( array( 'post_title' => 'Tags like </script><script>alert(1)</script>' ) );
		$this->go_to( get_permalink( $post ) );
		$printed = get_echo( 'tala_seo_print_schema' );
		$this->assertSame( 1, substr_count( $printed, '</script>' ) );
		$this->assertStringStartsWith( '<script type="application/ld+json">', $printed );
		$this->assertIsArray( json_decode( (string) preg_replace( '#^<script[^>]*>|</script>\s*$#', '', $printed ), true ) );
	}
}
