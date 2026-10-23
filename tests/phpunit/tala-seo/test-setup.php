<?php
/**
 * The test environment itself: Tala SEO active, Tala Blog the theme.
 *
 * @package TalaTests
 */

/**
 * Setup checks.
 */
class Test_Tala_SEO_Setup extends Tala_SEO_Test_Case {

	/**
	 * The plugin and the theme are loaded from this repository.
	 */
	public function test_plugin_and_theme_are_active(): void {
		$this->assertTrue( function_exists( 'tala_seo_settings' ) );
		$this->assertSame( 'tala-blog', get_stylesheet() );
		$this->assertTrue( wp_is_block_theme() );
	}

	/**
	 * Published test posts pass the publish check.
	 */
	public function test_publish_post_helper(): void {
		$id = $this->publish_post();
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( self::$image_id, (int) get_post_thumbnail_id( $id ) );
	}
}
