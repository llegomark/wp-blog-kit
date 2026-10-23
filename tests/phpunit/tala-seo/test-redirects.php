<?php
/**
 * Redirects: pages, categories and tags that moved answer their old addresses with a 301 to the
 * new one, and a post can send its visitors elsewhere on purpose.
 *
 * @package TalaTests
 */

/**
 * Redirects.
 */
class Test_Tala_SEO_Redirects extends Tala_SEO_Test_Case {

	/**
	 * Readable URLs, as on a real site.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_pretty_permalinks();
	}

	/**
	 * Where a request for $path would be sent by Tala SEO's 404 handler, or null.
	 *
	 * @param string $path Path on the site.
	 * @return Tala_SEO_Redirect_Caught|null
	 */
	private function moved( string $path ): ?Tala_SEO_Redirect_Caught {
		$this->go_to( home_url( $path ) );
		return $this->redirect_of( 'tala_seo_redirect_moved' );
	}

	/**
	 * Renames a post or page.
	 *
	 * @param int    $id   Post ID.
	 * @param string $slug New slug.
	 */
	private function rename( int $id, string $slug ): void {
		wp_update_post(
			array(
				'ID'        => $id,
				'post_name' => $slug,
			)
		);
	}

	/**
	 * A page that moves takes its old address with it, its child pages too; a chain of moves ends
	 * at the latest address; moving back makes the old address the real one again.
	 */
	public function test_moved_pages(): void {
		$parent = $this->publish_post(
			array(
				'post_type' => 'page',
				'post_name' => 'tala-parent',
			)
		);
		$this->publish_post(
			array(
				'post_type'   => 'page',
				'post_name'   => 'tala-child',
				'post_parent' => $parent,
			)
		);
		$this->assertNull( $this->moved( '/tala-parent/' ), 'The page itself answers.' );

		$this->rename( $parent, 'tala-moved' );
		$redirect = $this->moved( '/tala-parent/' );
		$this->assertSame( home_url( '/tala-moved/' ), $redirect?->location );
		$this->assertSame( 301, $redirect?->status );
		$this->assertSame( 'Tala SEO', $redirect?->by );
		$this->assertSame( home_url( '/tala-moved/tala-child/' ), $this->moved( '/tala-parent/tala-child/' )?->location );
		$this->assertNull( $this->moved( '/tala-parent/no-such-child/' ), 'A made-up child stays a 404.' );

		$this->rename( $parent, 'tala-final' );
		$this->assertSame( home_url( '/tala-final/' ), $this->moved( '/tala-parent/' )?->location, 'No chain' );
		$this->assertSame( home_url( '/tala-final/' ), $this->moved( '/tala-moved/' )?->location );

		$this->rename( $parent, 'tala-parent' );
		$this->assertNull( $this->moved( '/tala-parent/' ), 'Renamed back: the address is the page again.' );
		$this->assertSame( home_url( '/tala-parent/' ), $this->moved( '/tala-final/' )?->location );
		$this->assertNotContains( 'tala-parent', get_post_meta( $parent, TALA_SEO_OLD_PATH, false ) );
	}

	/**
	 * Drafts have no address worth keeping.
	 */
	public function test_drafts_are_not_tracked(): void {
		$draft = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'tala-draft',
			)
		);
		$this->rename( $draft, 'tala-draft-2' );
		$this->assertSame( array(), get_post_meta( $draft, TALA_SEO_OLD_PATH, false ) );
	}

	/**
	 * Categories and tags that move: the old address and its numbered pages go to the new ones.
	 */
	public function test_moved_terms(): void {
		$category = self::factory()->category->create( array( 'slug' => 'tala-cat' ) );
		$tag      = self::factory()->tag->create( array( 'slug' => 'tala-tag' ) );
		$this->publish_post(
			array(
				'post_category' => array( $category ),
				'tags_input'    => array( 'tala-tag' ),
			)
		);

		wp_update_term( $category, 'category', array( 'slug' => 'tala-cat-moved' ) );
		$this->assertSame( home_url( '/category/tala-cat-moved/' ), $this->moved( '/category/tala-cat/' )?->location );
		$this->assertSame( home_url( '/category/tala-cat-moved/page/2/' ), $this->moved( '/category/tala-cat/page/2/' )?->location );

		wp_update_term( $tag, 'post_tag', array( 'slug' => 'tala-tag-moved' ) );
		$this->assertSame( home_url( '/tag/tala-tag-moved/' ), $this->moved( '/tag/tala-tag/' )?->location );
	}

	/**
	 * A page keeps at most 20 old addresses, dropping the oldest.
	 */
	public function test_old_addresses_are_limited(): void {
		$page = $this->publish_post(
			array(
				'post_type' => 'page',
				'post_name' => 'tala-move-0',
			)
		);
		for ( $i = 1; $i <= 22; $i++ ) {
			$this->rename( $page, "tala-move-$i" );
		}
		$old = get_post_meta( $page, TALA_SEO_OLD_PATH, false );
		$this->assertCount( TALA_SEO_OLD_PATHS_KEPT, $old );
		$this->assertNotContains( 'tala-move-0', $old, 'The oldest go first.' );
		$this->assertContains( 'tala-move-21', $old );
	}

	/**
	 * A post's "Redirect to" field: a 301 for visitors (also for ?preview=true), none for its
	 * editors' previews, none to itself.
	 */
	public function test_post_redirect(): void {
		$post = $this->publish_post( array( 'meta_input' => array( TALA_SEO_REDIRECT => 'https://example.com/merged-guide/' ) ) );

		$this->go_to( get_permalink( $post ) );
		$redirect = $this->redirect_of( 'tala_seo_redirect_post' );
		$this->assertSame( 'https://example.com/merged-guide/', $redirect?->location );
		$this->assertSame( 301, $redirect?->status );
		$this->assertSame( 'Tala SEO', $redirect?->by );

		$preview = add_query_arg(
			array(
				'p'       => $post,
				'preview' => 'true',
			),
			home_url( '/' )
		);
		$this->go_to( $preview );
		$this->assertNotNull( $this->redirect_of( 'tala_seo_redirect_post' ), 'Visitors can\'t skip it with ?preview=true.' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->go_to( $preview );
		$this->assertNull( $this->redirect_of( 'tala_seo_redirect_post' ), 'Editors see the preview.' );
		wp_set_current_user( 0 );

		update_post_meta( $post, TALA_SEO_REDIRECT, get_permalink( $post ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertNull( $this->redirect_of( 'tala_seo_redirect_post' ), 'A redirect to itself is ignored.' );
	}

	/**
	 * The field takes absolute http(s) addresses only; a domain without "http://" gets it.
	 */
	public function test_post_redirect_values(): void {
		$post = $this->publish_post();
		foreach ( array( 'javascript:alert(1)', '/relative/path/', 'ftp://example.com/file', 'merged guide' ) as $value ) {
			update_post_meta( $post, TALA_SEO_REDIRECT, $value );
			$this->assertSame( '', get_post_meta( $post, TALA_SEO_REDIRECT, true ), $value );
		}
		update_post_meta( $post, TALA_SEO_REDIRECT, ' https://example.com/new/ ' );
		$this->assertSame( 'https://example.com/new/', get_post_meta( $post, TALA_SEO_REDIRECT, true ) );
		update_post_meta( $post, TALA_SEO_REDIRECT, 'example.com/new/' );
		$this->assertSame( 'http://example.com/new/', get_post_meta( $post, TALA_SEO_REDIRECT, true ) );
	}

	/**
	 * Renamed posts are WordPress's own job (old slugs), and it still does it.
	 */
	public function test_renamed_posts_use_core_redirects(): void {
		$post = $this->publish_post( array( 'post_name' => 'tala-old-name' ) );
		$this->rename( $post, 'tala-new-name' );
		$this->go_to( home_url( '/tala-old-name/' ) );
		$this->assertNull( $this->redirect_of( 'tala_seo_redirect_moved' ), 'Tala SEO leaves posts alone.' );
		$this->assertSame( get_permalink( $post ), $this->redirect_of( 'wp_old_slug_redirect' )?->location );
	}
}
