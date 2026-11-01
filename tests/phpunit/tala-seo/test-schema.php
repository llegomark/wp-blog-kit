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
	 * The author photo is shown (avatar, ProfilePage) only while visitors may see the image: never one
	 * attached to a draft, even when the profile already points at it.
	 */
	public function test_author_photo_only_when_public(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$draft  = self::factory()->post->create(
			array(
				'post_author' => $author,
				'post_status' => 'draft',
			)
		);
		$image  = (int) self::factory()->attachment->create_upload_object( dirname( __DIR__, 3 ) . '/dev/media/cover-2.jpg', $draft );
		update_user_meta( $author, 'tala_seo_photo', $image );
		$this->publish_post( array( 'post_author' => $author ) );

		$this->go_to( get_author_posts_url( $author ) );
		$this->assertArrayNotHasKey( 'image', $this->schema()['ProfilePage']['mainEntity'] );
		$this->assertStringNotContainsString( 'cover-2', (string) get_avatar_url( $author ) );

		wp_publish_post( $draft );
		$this->go_to( get_author_posts_url( $author ) );
		$this->assertStringContainsString( 'cover-2', $this->schema()['ProfilePage']['mainEntity']['image'] );
		$this->assertStringContainsString( 'cover-2', (string) get_avatar_url( $author ) );

		wp_delete_attachment( $image, true );
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

	/**
	 * Members who never published (subscribers, for example) have an author page WordPress serves
	 * empty; it gets no ProfilePage and no rel="me", so it can't vouch for their profile links.
	 */
	public function test_profiles_only_for_authors_of_posts(): void {
		$member = self::factory()->user->create(
			array(
				'role'        => 'subscriber',
				'description' => 'Just a reader.',
			)
		);
		update_user_meta( $member, 'tala_fediverse_url', 'https://mastodon.social/@reader' );
		$this->go_to( get_author_posts_url( $member ) );
		$this->assertArrayNotHasKey( 'ProfilePage', $this->schema() );
		$this->assertStringNotContainsString( 'rel="me"', $this->head() );
	}

	/**
	 * A parent page that isn't public (private or a draft) stays out of its child's breadcrumbs,
	 * visible trail and markup alike.
	 */
	public function test_breadcrumbs_skip_parents_that_are_not_public(): void {
		$parent = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'private',
				'post_title'  => 'Private parent',
			)
		);
		$child  = $this->publish_post(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent,
				'post_title'  => 'Public child',
			)
		);
		$this->go_to( get_permalink( $child ) );
		$trail = $this->show_breadcrumbs();
		$this->assertStringContainsString( 'Public child', $trail );
		$this->assertStringNotContainsString( 'Private parent', $trail );
		$this->assertStringNotContainsString( 'Private parent', (string) wp_json_encode( $this->schema()['BreadcrumbList'] ) );
	}

	/**
	 * A Breadcrumbs block hidden with block visibility shows no trail, so the page gets no
	 * BreadcrumbList either.
	 */
	public function test_hidden_breadcrumbs_block_adds_no_markup(): void {
		$post = $this->publish_post();
		$this->go_to( get_permalink( $post ) );
		do_blocks( '<!-- wp:tala-seo/breadcrumbs {"metadata":{"blockVisibility":false}} /-->' );
		$this->assertArrayNotHasKey( 'BreadcrumbList', $this->schema() );

		// Hidden by a block around it, however deep: WordPress still renders the trail, then drops it.
		$inside_hidden = '<!-- wp:group {"metadata":{"blockVisibility":false}} --><div class="wp-block-group"><!-- wp:group --><div class="wp-block-group"><!-- wp:tala-seo/breadcrumbs /--></div><!-- /wp:group --></div><!-- /wp:group -->';
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( '', trim( do_blocks( $inside_hidden ) ) );
		$this->assertArrayNotHasKey( 'BreadcrumbList', $this->schema(), 'A trail inside a hidden group is not shown' );

		// A trail shown elsewhere on the page still counts.
		$this->go_to( get_permalink( $post ) );
		do_blocks( $inside_hidden . '<!-- wp:tala-seo/breadcrumbs /-->' );
		$this->assertArrayHasKey( 'BreadcrumbList', $this->schema() );
	}

	/**
	 * The organization's kind, legal name, postal address, telephone and email are in its markup,
	 * the telephone and email also as its customer support contact; a person gets none of them.
	 */
	public function test_organization_details(): void {
		update_option(
			'tala_seo',
			array(
				'organization_type' => 'EducationalOrganization',
				'legal_name'        => 'Guro Notes Learning Center Inc.',
				'street_address'    => 'Block 2 Lot 10 Venus Street, Brgy. Mayamot',
				'locality'          => 'Antipolo',
				'region'            => 'Rizal',
				'postal_code'       => '1870',
				'country'           => 'PH',
				'telephone'         => '+63 926 021 1602',
				'email'             => 'support@example.org',
			)
		);
		$this->go_to( home_url( '/' ) );
		$nodes = $this->schema();
		$this->assertSame( array( 'WebSite', 'EducationalOrganization' ), array_keys( $nodes ) );
		$organization = $nodes['EducationalOrganization'];
		$this->assertSame( 'Guro Notes Learning Center Inc.', $organization['legalName'] );
		$this->assertSame(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => 'Block 2 Lot 10 Venus Street, Brgy. Mayamot',
				'addressLocality' => 'Antipolo',
				'addressRegion'   => 'Rizal',
				'postalCode'      => '1870',
				'addressCountry'  => 'PH',
			),
			$organization['address']
		);
		$this->assertSame( '+63 926 021 1602', $organization['telephone'] );
		$this->assertSame( 'support@example.org', $organization['email'] );
		$this->assertSame(
			array(
				'@type'       => 'ContactPoint',
				'contactType' => 'customer support',
				'telephone'   => '+63 926 021 1602',
				'email'       => 'support@example.org',
			),
			$organization['contactPoint']
		);

		$this->set_setting( 'telephone', '' );
		$this->set_setting( 'locality', '' );
		$this->go_to( home_url( '/' ) );
		$organization = $this->schema()['EducationalOrganization'];
		$this->assertArrayNotHasKey( 'telephone', $organization );
		$this->assertArrayNotHasKey( 'addressLocality', $organization['address'], 'Only what is set.' );
		$this->assertSame( array( 'email' => 'support@example.org' ), array_diff_key( $organization['contactPoint'], array_flip( array( '@type', 'contactType' ) ) ) );

		$this->set_setting( 'publisher_type', 'person' );
		$this->go_to( home_url( '/' ) );
		$person = $this->schema()['Person'];
		foreach ( array( 'address', 'telephone', 'email', 'contactPoint', 'legalName' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $person, $key );
		}
	}
}
