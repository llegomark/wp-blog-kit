<?php
/**
 * Deleting Tala SEO from Plugins (uninstall.php): the plugin's own data goes, on every site of a
 * network, and what editors wrote stays.
 *
 * @package TalaTests
 */

/**
 * Uninstalling Tala SEO.
 */
class Test_Tala_SEO_Uninstall extends Tala_SEO_Test_Case {

	/**
	 * What the plugin keeps for itself on a post.
	 */
	private const OWN_POST_META = array( '_tala_seo_minor_edit', '_tala_seo_old_path', '_tala_seo_share_crop_tried', '_tala_seo_videos', '_tala_seo_video_failures' );

	/**
	 * What editors wrote for a post, with a value each field keeps as it is.
	 */
	private const WRITTEN_POST_META = array(
		TALA_SEO_TITLE            => 'A title for search results',
		TALA_SEO_DESCRIPTION      => 'What the post is about.',
		TALA_SEO_CANONICAL        => 'https://example.com/first-published-here/',
		TALA_SEO_NOINDEX          => '1',
		TALA_SEO_REDIRECT         => 'https://example.com/merged-guide/',
		TALA_SEO_PRIMARY_CATEGORY => '7',
		TALA_SEO_VIDEO_DATES      => array( 'aqz-KE-bpKQ' => '2014-11-10' ),
	);

	/**
	 * Fills the current site with the plugin's settings, a post and a category that carry both
	 * kinds of data.
	 *
	 * @return array{post:int,term:int}
	 */
	private function fill_site(): array {
		update_option( 'tala_seo', array( 'home_description' => 'A site about teaching.' ) );
		update_option( 'tala_seo_version', TALA_SEO_VERSION );
		$post = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		foreach ( self::OWN_POST_META as $key ) {
			update_post_meta( $post, $key, 'value' );
		}
		foreach ( self::WRITTEN_POST_META as $key => $value ) {
			update_post_meta( $post, $key, $value );
		}
		$term = self::factory()->category->create();
		update_term_meta( $term, TALA_SEO_OLD_PATH, array( 'category/old-name' ) );
		update_term_meta( $term, TALA_SEO_DESCRIPTION, 'What this category holds.' );
		return array(
			'post' => $post,
			'term' => $term,
		);
	}

	/**
	 * Checks the current site after the uninstall.
	 *
	 * @param array{post:int,term:int} $ids  What fill_site() made here.
	 * @param string                   $site Which site this is, for the messages.
	 */
	private function assert_site_cleaned( array $ids, string $site ): void {
		$this->assertNull( get_option( 'tala_seo', null ), "$site: the settings" );
		$this->assertNull( get_option( 'tala_seo_version', null ), "$site: the note of the version" );
		foreach ( self::OWN_POST_META as $key ) {
			$this->assertFalse( metadata_exists( 'post', $ids['post'], $key ), "$site: $key" );
		}
		foreach ( self::WRITTEN_POST_META as $key => $value ) {
			$this->assertEquals( $value, get_post_meta( $ids['post'], $key, true ), "$site: $key stays" );
		}
		$this->assertFalse( metadata_exists( 'term', $ids['term'], TALA_SEO_OLD_PATH ), "$site: a category's old addresses" );
		$this->assertSame( 'What this category holds.', get_term_meta( $ids['term'], TALA_SEO_DESCRIPTION, true ), "$site: a category's description stays" );
		$this->assertNotSame( '', (string) get_option( 'blogname' ), "$site: WordPress's own settings stay" );
	}

	/**
	 * The plugin's settings, its version note and its internal marks go; SEO titles, descriptions,
	 * canonicals, redirects and the other things people wrote stay, for posts, terms and authors.
	 */
	public function test_uninstall_removes_the_plugins_own_data_and_keeps_what_editors_wrote(): void {
		$ids    = $this->fill_site();
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		update_user_meta( $author, TALA_SEO_OLD_PATH, array( 'author/old-name' ) );
		update_user_meta( $author, TALA_SEO_PROFILES, array( 'https://example.com/@author' ) );

		$this->uninstall( 'tala-seo' );

		$this->assert_site_cleaned( $ids, 'The site' );
		$this->assertFalse( metadata_exists( 'user', $author, TALA_SEO_OLD_PATH ), 'An author\'s old addresses' );
		$this->assertSame( array( 'https://example.com/@author' ), get_user_meta( $author, TALA_SEO_PROFILES, true ), 'An author\'s profile links stay' );
	}

	/**
	 * On a network, every site is cleaned, not only the one the plugin was deleted from, and the
	 * request is left on the site it started on.
	 *
	 * @group ms-required
	 */
	public function test_uninstall_cleans_every_site_of_a_network(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Runs on a network: composer test:multisite.' );
		}
		$here  = get_current_blog_id();
		$first = $this->fill_site();
		$other = self::factory()->blog->create();
		switch_to_blog( $other );
		$second = $this->fill_site();
		restore_current_blog();

		$this->uninstall( 'tala-seo' );

		$this->assertSame( $here, get_current_blog_id(), 'Back on the site it started on.' );
		$this->assert_site_cleaned( $first, 'The first site' );
		switch_to_blog( $other );
		$this->assert_site_cleaned( $second, 'The other site' );
		restore_current_blog();
	}
}
