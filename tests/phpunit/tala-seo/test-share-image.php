<?php
/**
 * The image shown when a page is shared (og:image): the 1200 × 630 crop, made on upload, or in the
 * background for images uploaded before the crop existed.
 *
 * @package TalaTests
 */

/**
 * Share images.
 */
class Test_Tala_SEO_Share_Image extends Tala_SEO_Test_Case {

	/**
	 * Images uploaded by a test, deleted with their files afterwards.
	 *
	 * @var int[]
	 */
	private array $uploads = array();

	/**
	 * Readable URLs, and no background jobs left from another test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_pretty_permalinks();
		wp_unschedule_hook( 'tala_seo_make_share_crop' );
	}

	/**
	 * Deletes the test's images and their files.
	 */
	public function tear_down(): void {
		foreach ( $this->uploads as $id ) {
			wp_delete_attachment( $id, true );
		}
		add_image_size( 'tala-seo-share', 1200, 630, true );
		parent::tear_down();
	}

	/**
	 * Uploads a demo image for this test.
	 *
	 * @param string $file File name in dev/media.
	 * @return int Attachment ID.
	 */
	private function upload( string $file ): int {
		$id              = self::upload_image( self::factory(), $file, 'An image for the share test' );
		$this->uploads[] = $id;
		return $id;
	}

	/**
	 * The og:image of the post with this featured image: file name ending and size.
	 *
	 * @param int $image Attachment ID.
	 * @return string
	 */
	private function shared( int $image ): string {
		$post = $this->publish_post( array( '_thumbnail_id' => $image ) );
		$this->go_to( get_permalink( $post ) );
		return basename( (string) $this->meta( 'og:image' ) ) . ' ' . $this->meta( 'og:image:width' ) . 'x' . $this->meta( 'og:image:height' );
	}

	/**
	 * A new upload gets the share crop, and shares it.
	 */
	public function test_new_upload_shares_its_crop(): void {
		$image = $this->upload( 'cover-2.jpg' );
		$this->assertStringEndsWith( '-1200x630.jpg 1200x630', $this->shared( $image ) );
		$this->assertSame( 'An image for the share test', $this->meta( 'og:image:alt' ) );
		$this->assertSame( 'summary_large_image', $this->meta( 'twitter:card' ) );
		$this->assertFalse( wp_next_scheduled( 'tala_seo_make_share_crop', array( $image ) ), 'Nothing to make later' );

		$article = $this->schema()['BlogPosting']['image'];
		$this->assertCount( 3, $article, "Google's 16:9, 4:3 and 1:1 crops" );
		$this->assertSame( array(), preg_grep( '/-1200x630\./', $article ), 'The share crop is not in the markup.' );
	}

	/**
	 * An image from before the crop existed: shares its 16:9 crop meanwhile, and the background
	 * job makes the crop, once.
	 */
	public function test_older_image_gets_its_crop_in_the_background(): void {
		remove_image_size( 'tala-seo-share' );
		$image = $this->upload( 'cover-3.jpg' );
		add_image_size( 'tala-seo-share', 1200, 630, true );
		$this->assertFalse( image_get_intermediate_size( $image, 'tala-seo-share' ) );

		$this->assertStringEndsWith( '-1200x675.jpg 1200x675', $this->shared( $image ) );
		$this->assertIsInt( wp_next_scheduled( 'tala_seo_make_share_crop', array( $image ) ), 'Queued for WP-Cron' );

		do_action( 'tala_seo_make_share_crop', $image );
		$this->assertSame( '1', get_post_meta( $image, TALA_SEO_SHARE_CROP_TRIED, true ) );
		$this->assertStringEndsWith( '-1200x630.jpg 1200x630', $this->shared( $image ) );

		wp_unschedule_hook( 'tala_seo_make_share_crop' );
		tala_seo_share_image( $image );
		$this->assertFalse( wp_next_scheduled( 'tala_seo_make_share_crop', array( $image ) ), 'Not queued again' );
	}

	/**
	 * A small image (900 × 500) is shared as it is, and nothing is queued for it.
	 */
	public function test_small_image_is_shared_as_it_is(): void {
		$image = $this->upload( 'small.jpg' );
		$this->assertSame( 'small.jpg 900x500', $this->shared( $image ) );
		$this->assertFalse( wp_next_scheduled( 'tala_seo_make_share_crop', array( $image ) ) );
	}

	/**
	 * The home page shares the default image from Settings → SEO.
	 */
	public function test_default_image(): void {
		$this->set_setting( 'default_image', self::$image_id );
		$this->go_to( home_url( '/' ) );
		$this->assertStringEndsWith( '-1200x630.jpg', (string) $this->meta( 'og:image' ) );
	}

	/**
	 * Deactivating the plugin drops its pending background jobs.
	 */
	public function test_deactivation_drops_pending_jobs(): void {
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'tala_seo_make_share_crop', array( 999999 ) );
		tala_seo_deactivate();
		$this->assertFalse( wp_next_scheduled( 'tala_seo_make_share_crop', array( 999999 ) ) );
	}
}
