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
	 * The blocks' script and styles carry the plugin's version in their address, so an update of the
	 * plugin reaches browsers and caches (they once stayed at 1.0.0 while the files changed).
	 */
	public function test_blocks_carry_the_plugin_version(): void {
		foreach ( array( 'event', 'course', 'breadcrumbs' ) as $block ) {
			// Registered again, as on a page load: an earlier test may have emptied the script queue.
			unregister_block_type( "tala-seo/$block" );
			wp_deregister_script( "tala-seo-$block-editor-script" );
			wp_deregister_style( "tala-seo-$block-style" );
			call_user_func( "tala_seo_register_{$block}_block" );

			$type = WP_Block_Type_Registry::get_instance()->get_registered( "tala-seo/$block" );
			$this->assertNotNull( $type, $block );
			$this->assertSame( array( "tala-seo-$block-editor-script" ), $type->editor_script_handles, $block );
			$this->assertSame( array( "tala-seo-$block-style" ), $type->style_handles, $block );
			$this->assertSame( TALA_SEO_VERSION, wp_scripts()->registered[ "tala-seo-$block-editor-script" ]->ver, "$block: editor script" );
			$this->assertSame( TALA_SEO_VERSION, wp_styles()->registered[ "tala-seo-$block-style" ]->ver, "$block: style" );
		}
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
