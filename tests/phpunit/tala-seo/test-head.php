<?php
/**
 * What Tala SEO puts in <head>: title, meta description, canonical, robots, Open Graph,
 * verification tags and the Fediverse tags.
 *
 * @package TalaTests
 */

/**
 * Head output.
 */
class Test_Tala_SEO_Head extends Tala_SEO_Test_Case {

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
	 * Titles: "Title | Site", the SEO title when set, and never "Name | Name".
	 */
	public function test_titles(): void {
		$post = $this->publish_post( array( 'post_title' => 'Planning a lesson' ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( 'Planning a lesson | Guro Notes', wp_get_document_title() );

		update_post_meta( $post, TALA_SEO_TITLE, 'How to plan a lesson in 10 minutes' );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( 'How to plan a lesson in 10 minutes | Guro Notes', wp_get_document_title() );

		$author = self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Guro Notes',
			)
		);
		$this->publish_post( array( 'post_author' => $author ) );
		$this->go_to( get_author_posts_url( $author ) );
		$this->assertSame( 'Guro Notes', wp_get_document_title(), 'An author named like the site gets the name once.' );
	}

	/**
	 * Category, tag and author pages use their own SEO title and description when set.
	 */
	public function test_archive_title_and_description(): void {
		$category = self::factory()->category->create( array( 'name' => 'Full guides' ) );
		$this->publish_post( array( 'post_category' => array( $category ) ) );
		update_term_meta( $category, TALA_SEO_TITLE, 'Full guides for teachers' );
		update_term_meta( $category, TALA_SEO_DESCRIPTION, 'Every full guide in one place.' );
		$this->go_to( get_term_link( $category ) );
		$this->assertSame( 'Full guides for teachers | Guro Notes', wp_get_document_title() );
		$this->assertSame( 'Every full guide in one place.', $this->meta( 'description' ) );

		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->publish_post( array( 'post_author' => $author ) );
		update_user_meta( $author, TALA_SEO_TITLE, 'Tala Tester, teacher' );
		update_user_meta( $author, TALA_SEO_DESCRIPTION, 'Guides for teachers by Tala.' );
		$this->go_to( get_author_posts_url( $author ) );
		$this->assertSame( 'Tala Tester, teacher | Guro Notes', wp_get_document_title() );
		$this->assertSame( 'Guides for teachers by Tala.', $this->meta( 'description' ) );
	}

	/**
	 * Descriptions: the post's own, else its excerpt; the home page uses Settings → SEO, else the tagline.
	 */
	public function test_descriptions(): void {
		$post = $this->publish_post( array( 'meta_input' => array( TALA_SEO_DESCRIPTION => 'Plan a lesson in ten minutes.' ) ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( 'Plan a lesson in ten minutes.', $this->meta( 'description' ) );
		$this->assertSame( 'Plan a lesson in ten minutes.', $this->meta( 'og:description' ) );

		$this->go_to( home_url( '/' ) );
		$this->assertSame( 'Practical guides for teachers', $this->meta( 'description' ) );
		$this->set_setting( 'home_description', 'Guides for aspiring school heads.' );
		$this->go_to( home_url( '/' ) );
		$this->assertSame( 'Guides for aspiring school heads.', $this->meta( 'description' ) );
	}

	/**
	 * Canonical links: each indexable page is its own; hidden and duplicate pages get none.
	 */
	public function test_canonical(): void {
		$post = $this->publish_post();
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( get_permalink( $post ), $this->canonical() );

		update_post_meta( $post, TALA_SEO_CANONICAL, 'https://example.com/original/' );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( 'https://example.com/original/', $this->canonical() );

		update_post_meta( $post, TALA_SEO_CANONICAL, 'not a link' );
		$this->assertSame( '', get_post_meta( $post, TALA_SEO_CANONICAL, true ), 'Words are not an address.' );

		$this->go_to( home_url( '/' ) );
		$this->assertSame( home_url( '/' ), $this->canonical() );

		$this->go_to( home_url( '/?s=lesson' ) );
		$this->assertSame( '', $this->canonical(), 'Search results' );
		$this->go_to( home_url( '/no-such-page/' ) );
		$this->assertSame( '', $this->canonical(), '404' );
		$this->go_to( get_month_link( (int) gmdate( 'Y' ), (int) gmdate( 'm' ) ) );
		$this->assertSame( '', $this->canonical(), 'Date archive' );
	}

	/**
	 * Each page of a paged archive is its own canonical.
	 */
	public function test_paged_canonical(): void {
		update_option( 'posts_per_page', 1 );
		$this->publish_post();
		$this->publish_post();
		$this->go_to( home_url( '/page/2/' ) );
		$this->assertSame( home_url( '/page/2/' ), $this->canonical() );
	}

	/**
	 * Robots: search results, 404s and date archives are noindex but followable; hidden posts too.
	 */
	public function test_robots_for_pages_that_stay_out_of_search(): void {
		$this->go_to( home_url( '/' ) );
		$this->assertSame( 'max-image-preview:large', $this->robots() );

		foreach ( array( '/?s=lesson', '/no-such-page/', '/' . gmdate( 'Y' ) . '/' ) as $path ) {
			$this->go_to( home_url( $path ) );
			$this->assertSame( 'follow, max-image-preview:large, noindex', $this->robots(), $path );
		}

		$post = $this->publish_post( array( 'meta_input' => array( TALA_SEO_NOINDEX => '1' ) ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( 'follow, max-image-preview:large, noindex', $this->robots(), 'Hidden post' );
	}

	/**
	 * A site hidden in Settings → Reading: "noindex, nofollow", never "nofollow, follow".
	 */
	public function test_robots_on_a_hidden_site(): void {
		update_option( 'blog_public', '0' );
		$this->go_to( home_url( '/?s=lesson' ) );
		$this->assertSame( 'nofollow, noindex', $this->robots() );
	}

	/**
	 * Tag pages with fewer posts than the minimum (2 by default) stay out of search results.
	 */
	public function test_thin_tag_pages(): void {
		$thin = self::factory()->tag->create( array( 'slug' => 'tala-thin' ) );
		$big  = self::factory()->tag->create( array( 'slug' => 'tala-big' ) );
		$this->publish_post( array( 'tags_input' => array( 'tala-thin', 'tala-big' ) ) );
		$this->publish_post( array( 'tags_input' => array( 'tala-big' ) ) );

		$this->go_to( get_term_link( $thin ) );
		$this->assertSame( 'follow, max-image-preview:large, noindex', $this->robots() );
		$this->assertSame( '', $this->canonical() );
		$this->go_to( get_term_link( $big ) );
		$this->assertSame( 'max-image-preview:large', $this->robots() );
		$this->assertSame( get_term_link( $big ), $this->canonical() );

		$this->set_setting( 'tag_min_posts', 0 );
		$this->go_to( get_term_link( $thin ) );
		$this->assertSame( 'max-image-preview:large', $this->robots(), 'Minimum 0: every tag page may be indexed.' );
	}

	/**
	 * Empty categories, and categories, tags and authors marked hidden, are noindex. A parent whose
	 * child has posts is not empty.
	 */
	public function test_hidden_and_empty_archives(): void {
		$empty  = self::factory()->category->create();
		$parent = self::factory()->category->create();
		$child  = self::factory()->category->create( array( 'parent' => $parent ) );
		$this->publish_post( array( 'post_category' => array( $child ) ) );

		$this->go_to( get_term_link( $empty ) );
		$this->assertSame( 'follow, max-image-preview:large, noindex', $this->robots(), 'Empty category' );
		$this->go_to( get_term_link( $parent ) );
		$this->assertSame( 'max-image-preview:large', $this->robots(), 'Parent of a used child' );

		update_term_meta( $child, TALA_SEO_NOINDEX, '1' );
		$this->go_to( get_term_link( $child ) );
		$this->assertSame( 'follow, max-image-preview:large, noindex', $this->robots(), 'Hidden category' );

		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$post   = $this->publish_post( array( 'post_author' => $author ) );
		update_user_meta( $author, TALA_SEO_NOINDEX, '1' );
		$this->go_to( get_author_posts_url( $author ) );
		$this->assertSame( 'follow, max-image-preview:large, noindex', $this->robots(), 'Hidden author page' );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( 'max-image-preview:large', $this->robots(), "The hidden author's posts stay indexable." );
	}

	/**
	 * Open Graph and the X card.
	 */
	public function test_open_graph(): void {
		$post = $this->publish_post( array( 'post_title' => 'Planning a lesson' ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( 'article', $this->meta( 'og:type' ) );
		$this->assertSame( 'Planning a lesson', $this->meta( 'og:title' ) );
		$this->assertSame( get_permalink( $post ), $this->meta( 'og:url' ) );
		$this->assertSame( 'Guro Notes', $this->meta( 'og:site_name' ) );
		$this->assertSame( 'A test cover image', $this->meta( 'og:image:alt' ) );
		$this->assertSame( 'summary_large_image', $this->meta( 'twitter:card' ) );
		$this->assertNotNull( $this->meta( 'article:published_time' ) );

		$page = $this->publish_post( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page ) );
		$this->assertSame( 'website', $this->meta( 'og:type' ) );
		$this->assertSame( 'summary', $this->meta( 'twitter:card' ), 'No image to share' );
	}

	/**
	 * Verification codes: on the home page only, also while the site is hidden.
	 */
	public function test_verification_tags(): void {
		$this->set_setting(
			'verify',
			array(
				'google'   => 'GoogleCode_1234567890',
				'bing'     => 'BingCode1234567890',
				'facebook' => 'metacode1234567890',
			)
		);
		$this->go_to( home_url( '/' ) );
		$this->assertSame( 'GoogleCode_1234567890', $this->meta( 'google-site-verification' ) );
		$this->assertSame( 'BingCode1234567890', $this->meta( 'msvalidate.01' ) );
		$this->assertSame( 'metacode1234567890', $this->meta( 'facebook-domain-verification' ) );

		$post = $this->publish_post();
		$this->go_to( get_permalink( $post ) );
		$this->assertNull( $this->meta( 'google-site-verification' ), 'Not on posts' );

		update_option( 'blog_public', '0' );
		$this->go_to( home_url( '/' ) );
		$this->assertSame( 'GoogleCode_1234567890', $this->meta( 'google-site-verification' ), 'Hidden site' );

		$this->set_setting( 'verify', array( 'google' => '' ) );
		$this->go_to( home_url( '/' ) );
		$this->assertNull( $this->meta( 'google-site-verification' ), 'No empty tags' );
	}

	/**
	 * Fediverse: fediverse:creator on the author's posts only, rel=me on their author page.
	 */
	public function test_fediverse_tags(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		update_user_meta( $author, 'tala_seo_fediverse', '@Tala@mastodon.social' );
		update_user_meta( $author, 'tala_fediverse_url', 'https://mastodon.social/@tala' );
		$theirs = $this->publish_post( array( 'post_author' => $author ) );
		$other  = $this->publish_post();

		$this->go_to( get_permalink( $theirs ) );
		$this->assertSame( '@Tala@mastodon.social', $this->meta( 'fediverse:creator' ) );
		$this->go_to( get_permalink( $other ) );
		$this->assertNull( $this->meta( 'fediverse:creator' ), "Not on other authors' posts" );
		$this->go_to( get_author_posts_url( $author ) );
		$this->assertStringContainsString( '<link rel="me" href="https://mastodon.social/@tala">', $this->head() );
	}

	/**
	 * Post lists show the meta description when a post has no excerpt of its own.
	 */
	public function test_excerpt_falls_back_to_the_description(): void {
		$post = $this->publish_post(
			array(
				'post_content' => 'Long body text.',
				'post_excerpt' => '', // The test factory writes one otherwise.
				'meta_input'   => array( TALA_SEO_DESCRIPTION => 'The meta description.' ),
			)
		);
		$this->assertSame( 'The meta description.', get_the_excerpt( $post ) );
		wp_update_post(
			array(
				'ID'           => $post,
				'post_excerpt' => 'A hand-written excerpt.',
			)
		);
		$this->assertSame( 'A hand-written excerpt.', get_the_excerpt( $post ) );
	}

	/**
	 * The description is plain text, and an excerpt is HTML: markup written into the description
	 * (encoded twice, so it survives being cleaned on save and on read) shows as text, never as a tag.
	 */
	public function test_description_in_excerpt_is_text(): void {
		$post    = $this->publish_post(
			array(
				'post_excerpt' => '',
				'meta_input'   => array( TALA_SEO_DESCRIPTION => 'Read this. &amp;lt;img src=x onerror=alert(1)&amp;gt;' ),
			)
		);
		$excerpt = get_the_excerpt( $post );
		$this->assertStringNotContainsString( '<img', $excerpt );
		$this->assertStringContainsString( '&lt;img', $excerpt, 'Shown as text' );
	}

	/**
	 * A password-protected post keeps its excerpt to itself until the password is entered, as
	 * WordPress does: without a meta description of its own, it has no description at all.
	 */
	public function test_protected_excerpt_is_not_the_description(): void {
		add_filter( 'tala_seo_publish_requirements', '__return_empty_array' ); // A post published without one.
		$post = $this->publish_post(
			array(
				'post_password' => 'secret',
				'post_excerpt'  => 'A summary of the protected post.',
				'meta_input'    => array( TALA_SEO_DESCRIPTION => '' ),
			)
		);
		$this->go_to( get_permalink( $post ) );
		$this->assertStringNotContainsString( 'A summary of the protected post.', $this->head() );
	}
}
