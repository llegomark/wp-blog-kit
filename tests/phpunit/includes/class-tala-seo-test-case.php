<?php
/**
 * Base class for the Tala SEO and Tala Blog tests: publishable posts, and the plugin's head output,
 * robots directives, structured data and redirects as tests can read them.
 *
 * @package TalaTests
 */

/**
 * Shared helpers.
 */
abstract class Tala_SEO_Test_Case extends WP_UnitTestCase {

	/**
	 * A 1600 × 900 image with alternative text, usable as a featured image.
	 *
	 * @var int
	 */
	protected static int $image_id = 0;

	/**
	 * Creates the shared image.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$image_id = self::upload_image( $factory, 'cover-1.jpg', 'A test cover image' );
	}

	/**
	 * Deletes the shared image and its files.
	 */
	public static function wpTearDownAfterClass(): void {
		wp_delete_attachment( self::$image_id, true );
	}

	/**
	 * Uploads one of the demo images in dev/media, with WordPress making its crops as on upload.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 * @param string              $file    File name in dev/media.
	 * @param string              $alt     Alternative text ('' for none).
	 * @return int Attachment ID.
	 */
	protected static function upload_image( WP_UnitTest_Factory $factory, string $file, string $alt = '' ): int {
		$id = (int) $factory->attachment->create_upload_object( dirname( __DIR__, 3 ) . '/dev/media/' . $file );
		if ( '' !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}
		return $id;
	}

	/**
	 * A published post (or page) that meets the publish check: a meta description and, for posts, a
	 * featured image with alternative text.
	 *
	 * @param array<string, mixed> $args Arguments for wp_insert_post(); meta_input is merged.
	 * @return int Post ID.
	 */
	protected function publish_post( array $args = array() ): int {
		$type     = (string) ( $args['post_type'] ?? 'post' );
		$defaults = array(
			'post_type'   => $type,
			'post_status' => 'publish',
			'meta_input'  => array( TALA_SEO_DESCRIPTION => 'A test description.' ),
		);
		if ( 'post' === $type ) {
			$defaults['_thumbnail_id'] = self::$image_id;
		}
		$args['meta_input'] = array_merge( $defaults['meta_input'], (array) ( $args['meta_input'] ?? array() ) );
		$id                 = self::factory()->post->create( array_merge( $defaults, $args ) );
		$this->assertSame( 'publish', get_post_status( $id ), 'The test post should pass the publish check.' );
		return $id;
	}

	/**
	 * Readable URLs, as on a real site, for posts and for category and tag pages. (The framework's
	 * set_permalink_structure() leaves the taxonomies with the plain-URL setup they started with.)
	 *
	 * @param string $structure Permalink structure.
	 */
	protected function set_pretty_permalinks( string $structure = '/%postname%/' ): void {
		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		flush_rewrite_rules();
	}

	/**
	 * What the plugin prints in <head> for the current page (description, canonical, Open Graph).
	 *
	 * @return string
	 */
	protected function head(): string {
		return get_echo( 'tala_seo_print_head' );
	}

	/**
	 * A <meta name> or <meta property> value from head(), or null when the tag isn't there.
	 *
	 * @param string $name Name or property.
	 * @return string|null
	 */
	protected function meta( string $name ): ?string {
		$pattern = '/<meta (?:name|property)="' . preg_quote( $name, '/' ) . '" content="([^"]*)">/';
		return preg_match( $pattern, $this->head(), $match ) ? html_entity_decode( $match[1], ENT_QUOTES ) : null;
	}

	/**
	 * The canonical URL printed for the current page, or '' when there is none.
	 *
	 * @return string
	 */
	protected function canonical(): string {
		return preg_match( '/<link rel="canonical" href="([^"]*)">/', $this->head(), $match ) ? $match[1] : '';
	}

	/**
	 * The robots directives of the current page, sorted ("follow, max-image-preview:large, noindex").
	 *
	 * @return string
	 */
	protected function robots(): string {
		if ( ! preg_match( "/<meta name='robots' content='([^']*)'/", get_echo( 'wp_robots' ), $match ) ) {
			return '';
		}
		$directives = array_map( 'trim', explode( ',', $match[1] ) );
		sort( $directives );
		return implode( ', ', $directives );
	}

	/**
	 * The structured data nodes of the current page, by @type.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function schema(): array {
		$nodes = array();
		foreach ( tala_seo_schema_nodes() as $node ) {
			$nodes[ (string) $node['@type'] ] = $node;
		}
		return $nodes;
	}

	/**
	 * Runs $callback and returns the redirect it would have sent, or null when it sent none.
	 *
	 * @param callable $callback Code that may redirect (and exit).
	 * @return Tala_SEO_Redirect_Caught|null
	 */
	protected function redirect_of( callable $callback ): ?Tala_SEO_Redirect_Caught {
		$catch = static function ( $by, $status, $location ): never {
			throw new Tala_SEO_Redirect_Caught( (string) $location, (int) $status, (string) $by ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught below, never printed.
		};
		add_filter( 'x_redirect_by', $catch, PHP_INT_MAX, 3 );
		try {
			$callback();
		} catch ( Tala_SEO_Redirect_Caught $redirect ) {
			return $redirect;
		} finally {
			remove_filter( 'x_redirect_by', $catch, PHP_INT_MAX );
		}
		return null;
	}

	/**
	 * Sets a Settings → SEO value, keeping the others.
	 *
	 * @param string $key   Setting.
	 * @param mixed  $value Value.
	 */
	protected function set_setting( string $key, $value ): void {
		$settings         = (array) get_option( 'tala_seo', array() );
		$settings[ $key ] = $value;
		update_option( 'tala_seo', $settings );
	}
}
