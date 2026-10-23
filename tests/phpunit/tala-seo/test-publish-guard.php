<?php
/**
 * The publish check: posts need a meta description and a featured image with alternative text,
 * pages a description, before they can go live; enforced on the server for every way of saving.
 *
 * @package TalaTests
 */

/**
 * Publish check.
 */
class Test_Tala_SEO_Publish_Guard extends Tala_SEO_Test_Case {

	/**
	 * An administrator saves.
	 *
	 * @var int
	 */
	private int $admin = 0;

	/**
	 * Logs in an administrator.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin );
	}

	/**
	 * Saves a post over the REST API, as the block editor does.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  Route.
	 * @param array<string, mixed> $body   Request body.
	 * @return WP_REST_Response
	 */
	private function rest( string $method, string $route, array $body ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		$request->set_body_params( $body );
		return rest_do_request( $request );
	}

	/**
	 * Other ways of saving (Quick Edit, WP-CLI, plugins) keep the post as a draft and say why.
	 */
	public function test_saves_without_the_essentials_stay_drafts(): void {
		$missing = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->assertSame( 'draft', get_post_status( $missing ) );
		$notice = (string) get_transient( 'tala_seo_notice_' . $this->admin );
		$this->assertStringContainsString( 'Kept as a draft.', $notice );
		$this->assertStringContainsString( 'Add a meta description', $notice );
		$this->assertStringContainsString( 'Set a featured image.', $notice );

		$no_alt = self::upload_image( self::factory(), 'cover-2.jpg' );
		$post   = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'_thumbnail_id' => $no_alt,
				'meta_input'    => array( TALA_SEO_DESCRIPTION => 'A description.' ),
			)
		);
		$this->assertSame( 'draft', get_post_status( $post ), 'An image without alternative text' );
		wp_delete_attachment( $no_alt, true );

		$this->assertSame( 'publish', get_post_status( $this->publish_post() ), 'With everything' );
	}

	/**
	 * Pages need a description only.
	 */
	public function test_pages_need_a_description_only(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'draft', get_post_status( $page ) );
		$this->assertSame( 'publish', get_post_status( $this->publish_post( array( 'post_type' => 'page' ) ) ) );
	}

	/**
	 * Posts that are already live stay editable.
	 */
	public function test_live_posts_stay_editable(): void {
		$post = $this->publish_post();
		delete_post_meta( $post, TALA_SEO_DESCRIPTION );
		wp_update_post(
			array(
				'ID'         => $post,
				'post_title' => 'A new title',
			)
		);
		$this->assertSame( 'publish', get_post_status( $post ) );
	}

	/**
	 * The block editor (REST): publishing is refused with a clear message and the missing items.
	 */
	public function test_rest_publish_is_refused_until_ready(): void {
		$response = $this->rest(
			'POST',
			'/wp/v2/posts',
			array(
				'title'  => 'Not ready',
				'status' => 'publish',
			)
		);
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'tala_seo_not_ready', $response->get_data()['code'] );
		$this->assertStringStartsWith( 'To publish:', $response->get_data()['message'] );
		$this->assertSame( array( 'description', 'featured_image' ), $response->get_data()['data']['problems'] );

		$response = $this->rest(
			'POST',
			'/wp/v2/posts',
			array(
				'title'          => 'Ready',
				'status'         => 'publish',
				'featured_media' => self::$image_id,
				'meta'           => array( TALA_SEO_DESCRIPTION => 'A description.' ),
			)
		);
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'publish', $response->get_data()['status'] );
	}

	/**
	 * A live post is only stopped when a save breaks what it touches; autosaves are never stopped.
	 */
	public function test_rest_edits_of_live_posts(): void {
		$post  = $this->publish_post();
		$route = '/wp/v2/posts/' . $post;

		$this->assertSame( 200, $this->rest( 'POST', $route, array( 'title' => 'Edited' ) )->get_status() );

		$response = $this->rest( 'POST', $route, array( 'meta' => array( TALA_SEO_DESCRIPTION => '' ) ) );
		$this->assertSame( 400, $response->get_status() );
		$this->assertStringStartsWith( 'This post is live, so it still needs this:', $response->get_data()['message'] );
		$this->assertSame( 'A test description.', get_post_meta( $post, TALA_SEO_DESCRIPTION, true ) );

		$autosave = $this->rest( 'POST', $route . '/autosaves', array( 'meta' => array( TALA_SEO_DESCRIPTION => '' ) ) );
		$this->assertContains( $autosave->get_status(), array( 200, 201 ) );
	}

	/**
	 * An autosave never takes a post live. WordPress writes an author's autosave of their own draft
	 * straight into the post, status included, so a status sent with it would skip the check.
	 */
	public function test_autosaves_cannot_publish(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$draft  = self::factory()->post->create(
			array(
				'post_author' => $author,
				'post_status' => 'draft',
			)
		);
		wp_set_current_user( $author );
		$response = $this->rest(
			'POST',
			"/wp/v2/posts/$draft/autosaves",
			array(
				'status'  => 'publish',
				'content' => 'Saved by the editor.',
			)
		);
		$this->assertContains( $response->get_status(), array( 200, 201 ), 'The autosave itself still works.' );
		$this->assertSame( 'draft', get_post_status( $draft ) );
		$this->assertSame( 'Saved by the editor.', get_post_field( 'post_content', $draft ) );
	}

	/**
	 * A minor edit keeps the post's updated date; the switch applies to one save only.
	 */
	public function test_minor_edit_keeps_the_updated_date(): void {
		global $wpdb;
		$post = $this->publish_post();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- a modified date WordPress itself won't write.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => '2026-01-01 08:00:00',
				'post_modified_gmt' => '2026-01-01 08:00:00',
			),
			array( 'ID' => $post )
		);
		clean_post_cache( $post );

		$this->rest(
			'POST',
			'/wp/v2/posts/' . $post,
			array(
				'content' => 'Fixed a typo.',
				'meta'    => array( TALA_SEO_MINOR_EDIT => true ),
			)
		);
		$this->assertSame( '2026-01-01 08:00:00', get_post( $post )->post_modified_gmt );
		$this->assertSame( '', get_post_meta( $post, TALA_SEO_MINOR_EDIT, true ), 'Reset after the save' );

		$this->rest( 'POST', '/wp/v2/posts/' . $post, array( 'content' => 'A real update.' ) );
		$this->assertNotSame( '2026-01-01 08:00:00', get_post( $post )->post_modified_gmt );
	}
}
