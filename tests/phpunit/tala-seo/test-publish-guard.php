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
	 * A featured image can also come with the post's meta (meta_input), as WP-CLI's --meta_input and
	 * other plugins pass it: WordPress stores it as the featured image either way. The one named as
	 * the post's own field wins, as it does in WordPress.
	 */
	public function test_a_featured_image_given_as_meta_counts(): void {
		$post = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'meta_input'  => array(
					TALA_SEO_DESCRIPTION => 'A description.',
					'_thumbnail_id'      => (string) self::$image_id,
				),
			)
		);
		$this->assertSame( 'publish', get_post_status( $post ) );
		$this->assertSame( self::$image_id, (int) get_post_thumbnail_id( $post ) );

		$removed = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'_thumbnail_id' => -1, // WordPress removes the featured image for -1.
				'meta_input'    => array(
					TALA_SEO_DESCRIPTION => 'A description.',
					'_thumbnail_id'      => (string) self::$image_id,
				),
			)
		);
		$this->assertSame( 'draft', get_post_status( $removed ), 'The post\'s own field is applied last.' );
	}

	/**
	 * Outside the block editor, a refused publish leaves the post where it was: one waiting for review
	 * stays in the queue and a private one stays private; only a new post becomes a draft.
	 */
	public function test_a_refused_publish_keeps_the_status(): void {
		$cases = array(
			'pending' => 'Kept as pending review.',
			'private' => 'Kept private.',
			'draft'   => 'Kept as a draft.',
		);
		foreach ( $cases as $status => $says ) {
			$post = self::factory()->post->create( array( 'post_status' => $status ) );
			delete_transient( 'tala_seo_notice_' . $this->admin );
			wp_update_post(
				array(
					'ID'          => $post,
					'post_status' => 'publish',
				)
			);
			$this->assertSame( $status, get_post_status( $post ) );
			$notice = (string) get_transient( 'tala_seo_notice_' . $this->admin );
			$this->assertStringStartsWith( $says, $notice );
			$this->assertStringContainsString( 'Add a meta description', $notice );
		}

		$trashed = self::factory()->post->create( array( 'post_status' => 'trash' ) );
		wp_update_post(
			array(
				'ID'          => $trashed,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'draft', get_post_status( $trashed ), 'Out of the trash, but not live.' );
	}

	/**
	 * WordPress dates a post the moment it is asked to publish it. A refused publish gives a draft
	 * or a post waiting for review its open date back, so it is dated when it does go live, not when
	 * the refused try was made. A date the author set is kept.
	 */
	public function test_a_refused_publish_keeps_the_date_open(): void {
		global $wpdb;
		$open = '0000-00-00 00:00:00';
		foreach ( array( 'draft', 'pending' ) as $status ) {
			$post = self::factory()->post->create( array( 'post_status' => $status ) );
			$this->assertSame( $open, get_post( $post )->post_date_gmt );
			wp_update_post(
				array(
					'ID'          => $post,
					'post_status' => 'publish',
				)
			);
			$this->assertSame( $status, get_post_status( $post ) );
			$this->assertSame( $open, get_post( $post )->post_date_gmt, "A $status post's date stays open." );
		}

		// Days later the post is ready: it goes out with that day's date, not the refused try's.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- a local date in the past, as if the try was days ago.
		$wpdb->update( $wpdb->posts, array( 'post_date' => '2026-01-01 08:00:00' ), array( 'ID' => $post ) );
		clean_post_cache( $post );
		set_post_thumbnail( $post, self::$image_id );
		update_post_meta( $post, TALA_SEO_DESCRIPTION, 'A description.' );
		wp_update_post(
			array(
				'ID'          => $post,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'publish', get_post_status( $post ) );
		$this->assertSame( current_time( 'Y-m-d' ), substr( get_post( $post )->post_date, 0, 10 ) );

		$new = wp_insert_post(
			array(
				'post_title'  => 'A new post',
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'draft', get_post_status( $new ) );
		$this->assertSame( $open, get_post( $new )->post_date_gmt, 'A new post\'s date is open too.' );

		$dated = self::factory()->post->create(
			array(
				'post_status'   => 'draft',
				'post_date'     => '2026-01-05 08:00:00',
				'post_date_gmt' => '2026-01-05 08:00:00',
			)
		);
		wp_update_post(
			array(
				'ID'          => $dated,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'draft', get_post_status( $dated ) );
		$this->assertSame( '2026-01-05 08:00:00', get_post( $dated )->post_date_gmt, 'A date the author set is kept.' );
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
	 * A live post that never had a description (an old post, or one whose description another SEO
	 * plugin holds) still saves its other fields. The block editor sends every meta field when one
	 * changes, the empty description included, so only a description the save changes counts.
	 */
	public function test_rest_meta_saves_of_live_posts_without_a_description(): void {
		$post = $this->publish_post();
		delete_post_meta( $post, TALA_SEO_DESCRIPTION );
		$route = '/wp/v2/posts/' . $post;

		$response = $this->rest(
			'POST',
			$route,
			array(
				'meta' => array(
					TALA_SEO_DESCRIPTION => '',
					TALA_SEO_TITLE       => '',
					TALA_SEO_NOINDEX     => true,
				),
			)
		);
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( '1', get_post_meta( $post, TALA_SEO_NOINDEX, true ), 'The field that changed is saved.' );

		$response = $this->rest( 'POST', $route, array( 'meta' => array( TALA_SEO_DESCRIPTION => 123 ) ) );
		$this->assertSame( 400, $response->get_status(), 'A description that isn\'t text is a change, and no description.' );
		$this->assertSame( 'tala_seo_not_ready', $response->get_data()['code'] );

		$response = $this->rest( 'POST', $route, array( 'meta' => array( TALA_SEO_DESCRIPTION => 'Now it has one.' ) ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Now it has one.', get_post_meta( $post, TALA_SEO_DESCRIPTION, true ) );

		$migrated = $this->publish_post();
		delete_post_meta( $migrated, TALA_SEO_DESCRIPTION );
		update_post_meta( $migrated, '_yoast_wpseo_metadesc', 'Written in another plugin.' );
		$response = $this->rest(
			'POST',
			'/wp/v2/posts/' . $migrated,
			array(
				'meta' => array(
					TALA_SEO_DESCRIPTION => '',
					TALA_SEO_NOINDEX     => true,
				),
			)
		);
		$this->assertSame( 200, $response->get_status(), 'The other plugin\'s description still counts.' );
	}

	/**
	 * The same for the featured image: sent along unchanged, it doesn't stop the save of a live post
	 * that has none.
	 */
	public function test_rest_saves_of_live_posts_without_a_featured_image(): void {
		$post = $this->publish_post();
		delete_post_thumbnail( $post );
		$route = '/wp/v2/posts/' . $post;

		$response = $this->rest(
			'POST',
			$route,
			array(
				'title'          => 'Edited',
				'featured_media' => 0,
			)
		);
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'Edited', get_post( $post )->post_title );

		set_post_thumbnail( $post, self::$image_id );
		$response = $this->rest( 'POST', $route, array( 'featured_media' => 0 ) );
		$this->assertSame( 400, $response->get_status(), 'Taking the image away from a live post is still refused.' );
		$this->assertSame( array( 'featured_image' ), $response->get_data()['data']['problems'] );
	}

	/**
	 * A scheduled post counts as live: it stays editable, can't lose what it needs, and a draft can't
	 * be scheduled without it.
	 */
	public function test_scheduled_posts_count_as_live(): void {
		$soon = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
		$post = self::factory()->post->create(
			array(
				'post_status'   => 'future',
				'post_date'     => get_date_from_gmt( $soon ),
				'post_date_gmt' => $soon,
				'_thumbnail_id' => self::$image_id,
				'meta_input'    => array( TALA_SEO_DESCRIPTION => 'A test description.' ),
			)
		);
		$this->assertSame( 'future', get_post_status( $post ) );
		$route = '/wp/v2/posts/' . $post;

		$this->assertSame( 200, $this->rest( 'POST', $route, array( 'title' => 'Edited while scheduled' ) )->get_status() );
		$response = $this->rest( 'POST', $route, array( 'meta' => array( TALA_SEO_DESCRIPTION => '' ) ) );
		$this->assertSame( 400, $response->get_status() );
		$this->assertStringStartsWith( 'This post is live, so it still needs this:', $response->get_data()['message'] );

		$draft    = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$response = $this->rest(
			'POST',
			'/wp/v2/posts/' . $draft,
			array(
				'status'   => 'future',
				'date_gmt' => str_replace( ' ', 'T', $soon ),
			)
		);
		$this->assertSame( 400, $response->get_status() );
		$this->assertStringStartsWith( 'To publish:', $response->get_data()['message'] );
		$this->assertSame( 'draft', get_post_status( $draft ) );

		$unready = self::factory()->post->create(
			array(
				'post_status'   => 'future',
				'post_date'     => get_date_from_gmt( $soon ),
				'post_date_gmt' => $soon,
			)
		);
		$this->assertSame( 'draft', get_post_status( $unready ), 'Nor outside the block editor.' );
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

	/**
	 * A description that isn't text (a number, a list) is no description: WordPress saves the post
	 * before it rejects such meta, so the check must refuse the publish itself, or the post would go
	 * live with no description stored. Nor is one made of spaces, no-break spaces included.
	 */
	public function test_rest_publish_needs_a_text_description(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$draft = self::factory()->post->create(
			array(
				'post_status'   => 'draft',
				'post_author'   => $author,
				'_thumbnail_id' => self::$image_id,
			)
		);
		foreach ( array( 123, array( 'A description.' ), true, "\u{00A0} \u{2003}", '&nbsp;' ) as $value ) {
			$response = $this->rest(
				'POST',
				'/wp/v2/posts/' . $draft,
				array(
					'status' => 'publish',
					'meta'   => array( TALA_SEO_DESCRIPTION => $value ),
				)
			);
			$this->assertSame( 400, $response->get_status(), wp_json_encode( $value ) );
			$this->assertSame( 'tala_seo_not_ready', $response->get_data()['code'], wp_json_encode( $value ) );
			$this->assertSame( 'draft', get_post_status( $draft ), 'Still a draft: ' . wp_json_encode( $value ) );
		}
	}
}
