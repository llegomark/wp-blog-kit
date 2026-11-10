<?php
/**
 * Who may point a post elsewhere: its "Redirect to" and canonical fields send visitors or search
 * engines to another address, so only people who could change them on the published post may set
 * them. A contributor's draft is published by someone else, who may never look at those fields.
 *
 * @package TalaTests
 */

/**
 * The redirect and canonical fields' permissions.
 */
class Test_Tala_SEO_Targets extends Tala_SEO_Test_Case {

	/**
	 * A contributor: writes drafts, can't publish.
	 *
	 * @var int
	 */
	private int $contributor = 0;

	/**
	 * An editor: publishes the contributor's drafts.
	 *
	 * @var int
	 */
	private int $editor = 0;

	/**
	 * The contributor's draft.
	 *
	 * @var int
	 */
	private int $draft = 0;

	/**
	 * A contributor with a draft, and an editor.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$this->editor      = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->draft       = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => $this->contributor,
				'post_title'  => 'A guest article',
			)
		);
	}

	/**
	 * Saves a post over the REST API, as the block editor does.
	 *
	 * @param int                  $user   Who saves.
	 * @param string               $route  Route.
	 * @param array<string, mixed> $body   Request body.
	 * @return WP_REST_Response
	 */
	private function save( int $user, string $route, array $body ): WP_REST_Response {
		wp_set_current_user( $user );
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_body_params( $body );
		return rest_do_request( $request );
	}

	/**
	 * Every SEO field as the block editor sends them: all of them, whenever one changes.
	 *
	 * @param array<string, mixed> $changed The fields that differ from an untouched post's.
	 * @return array<string, mixed>
	 */
	private function all_fields( array $changed = array() ): array {
		return array_merge(
			array(
				TALA_SEO_TITLE       => '',
				TALA_SEO_DESCRIPTION => '',
				TALA_SEO_CANONICAL   => '',
				TALA_SEO_NOINDEX     => false,
				TALA_SEO_REDIRECT    => '',
				TALA_SEO_MINOR_EDIT  => false,
			),
			$changed
		);
	}

	/**
	 * A contributor can't set either field: the save is refused whole, before anything is written.
	 */
	public function test_a_contributor_cannot_set_a_redirect_or_canonical(): void {
		$route = '/wp/v2/posts/' . $this->draft;
		foreach ( array( TALA_SEO_REDIRECT, TALA_SEO_CANONICAL ) as $key ) {
			$response = $this->save(
				$this->contributor,
				$route,
				array(
					'title' => 'Changed with it',
					'meta'  => $this->all_fields( array( $key => 'https://example.com/elsewhere/' ) ),
				)
			);
			$this->assertSame( 403, $response->get_status(), $key );
			$this->assertSame( 'tala_seo_cannot_set_target', $response->get_data()['code'], $key );
			$this->assertSame( '', get_post_meta( $this->draft, $key, true ), $key );
			$this->assertSame( 'A guest article', get_post( $this->draft )->post_title, 'Nothing of the save is kept.' );
		}

		$response = $this->save(
			$this->contributor,
			'/wp/v2/posts',
			array(
				'title' => 'A new one',
				'meta'  => array( TALA_SEO_REDIRECT => 'https://example.com/elsewhere/' ),
			)
		);
		$this->assertSame( 403, $response->get_status(), 'Nor on a new post.' );
	}

	/**
	 * The contributor's own saves still work: the block editor sends both fields along, unchanged,
	 * with every other SEO field.
	 */
	public function test_a_contributor_still_saves_the_other_fields(): void {
		$response = $this->save(
			$this->contributor,
			'/wp/v2/posts/' . $this->draft,
			array( 'meta' => $this->all_fields( array( TALA_SEO_DESCRIPTION => 'What the article is about.' ) ) )
		);
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'What the article is about.', get_post_meta( $this->draft, TALA_SEO_DESCRIPTION, true ) );

		$response = $this->save(
			$this->contributor,
			'/wp/v2/posts',
			array(
				'title' => 'A new draft',
				'meta'  => $this->all_fields( array( TALA_SEO_DESCRIPTION => 'A new one.' ) ),
			)
		);
		$this->assertSame( 201, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'A new one.', get_post_meta( $response->get_data()['id'], TALA_SEO_DESCRIPTION, true ) );
	}

	/**
	 * An editor sets both on the contributor's article (a guest post first published elsewhere), and
	 * they stay through the contributor's later saves, who can't change or clear them.
	 */
	public function test_an_editor_sets_them_on_a_contributors_post(): void {
		$route    = '/wp/v2/posts/' . $this->draft;
		$values   = array(
			TALA_SEO_CANONICAL => 'https://example.com/first-published-here/',
			TALA_SEO_REDIRECT  => 'https://example.com/merged-guide/',
		);
		$response = $this->save( $this->editor, $route, array( 'meta' => $this->all_fields( $values ) ) );
		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( $values[ TALA_SEO_CANONICAL ], get_post_meta( $this->draft, TALA_SEO_CANONICAL, true ) );
		$this->assertSame( $values[ TALA_SEO_REDIRECT ], get_post_meta( $this->draft, TALA_SEO_REDIRECT, true ) );

		$response = $this->save(
			$this->contributor,
			$route,
			array( 'meta' => $this->all_fields( $values + array( TALA_SEO_DESCRIPTION => 'Written later.' ) ) )
		);
		$this->assertSame( 200, $response->get_status(), 'Sent along as they are, they stop nothing.' );
		$this->assertSame( 'Written later.', get_post_meta( $this->draft, TALA_SEO_DESCRIPTION, true ) );

		$response = $this->save( $this->contributor, $route, array( 'meta' => $this->all_fields() ) );
		$this->assertSame( 403, $response->get_status(), 'Clearing them is a change too.' );
		$this->assertSame( $values[ TALA_SEO_CANONICAL ], get_post_meta( $this->draft, TALA_SEO_CANONICAL, true ) );
		$this->assertSame( $values[ TALA_SEO_REDIRECT ], get_post_meta( $this->draft, TALA_SEO_REDIRECT, true ) );
	}

	/**
	 * An author publishes their own posts, so they set both on them; pages are for editors.
	 */
	public function test_people_who_publish_set_them_on_their_own_posts(): void {
		$author   = self::factory()->user->create( array( 'role' => 'author' ) );
		$own      = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => $author,
			)
		);
		$response = $this->save( $author, '/wp/v2/posts/' . $own, array( 'meta' => array( TALA_SEO_REDIRECT => 'https://example.com/merged-guide/' ) ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'https://example.com/merged-guide/', get_post_meta( $own, TALA_SEO_REDIRECT, true ) );

		$this->assertTrue( tala_seo_can_set_target( $author, $own ) );
		$this->assertFalse( tala_seo_can_set_target( $author, $this->draft ), 'Not on someone else\'s post.' );
		$this->assertFalse( tala_seo_can_set_target( $this->contributor, $this->draft ) );
		$this->assertTrue( tala_seo_can_set_target( $this->editor, $this->draft ) );
		$this->assertFalse( tala_seo_can_set_target( 0, $this->draft ), 'Nobody logged in.' );

		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		$this->assertFalse( tala_seo_can_set_target( $author, $page ) );
		$this->assertTrue( tala_seo_can_set_target( $this->editor, $page ) );
		$this->assertTrue( tala_seo_can_set_target( $author, 0, 'post' ), 'A post not saved yet.' );
		$this->assertFalse( tala_seo_can_set_target( $this->contributor, 0, 'post' ) );
		$this->assertFalse( tala_seo_can_set_target( $author, 0, 'page' ) );
	}

	/**
	 * The same rule for every other way in (custom fields, XML-RPC), which ask WordPress whether the
	 * user may edit the post's meta key; the other SEO fields stay open to whoever edits the post.
	 */
	public function test_the_meta_capability_follows_the_rule(): void {
		foreach ( array( TALA_SEO_REDIRECT, TALA_SEO_CANONICAL ) as $key ) {
			$this->assertFalse( user_can( $this->contributor, 'edit_post_meta', $this->draft, $key ), $key );
			$this->assertTrue( user_can( $this->editor, 'edit_post_meta', $this->draft, $key ), $key );
		}
		$this->assertTrue( user_can( $this->contributor, 'edit_post_meta', $this->draft, TALA_SEO_DESCRIPTION ) );
		$this->assertTrue( user_can( $this->contributor, 'edit_post_meta', $this->draft, TALA_SEO_NOINDEX ) );
	}

	/**
	 * Fields set before the rule existed: once, on update, both are taken off posts that aren't
	 * published yet and whose author can't publish, since nobody who can may have seen them.
	 * Published posts and the drafts of people who publish keep theirs.
	 */
	public function test_unreviewed_values_are_removed_once(): void {
		$values = array(
			TALA_SEO_REDIRECT  => 'https://example.com/planted/',
			TALA_SEO_CANONICAL => 'https://example.com/planted-original/',
		);
		$make   = fn( string $status, int $author ): int => self::factory()->post->create(
			array(
				'post_status' => $status,
				'post_author' => $author,
				'meta_input'  => $values,
			)
		);

		$drafted   = $make( 'draft', $this->contributor );
		$pending   = $make( 'pending', $this->contributor );
		$trashed   = $make( 'trash', $this->contributor );
		$editors   = $make( 'draft', $this->editor );
		$published = $this->publish_post(
			array(
				'post_author' => $this->contributor,
				'meta_input'  => $values,
			)
		);
		update_post_meta( $drafted, TALA_SEO_DESCRIPTION, 'Stays.' );

		delete_option( 'tala_seo_version' );
		tala_seo_upgrade();

		$this->assertSame( TALA_SEO_VERSION, get_option( 'tala_seo_version' ) );
		foreach ( array( $drafted, $pending, $trashed ) as $post ) {
			$this->assertSame( '', get_post_meta( $post, TALA_SEO_REDIRECT, true ), get_post_status( $post ) );
			$this->assertSame( '', get_post_meta( $post, TALA_SEO_CANONICAL, true ), get_post_status( $post ) );
		}
		$this->assertSame( 'Stays.', get_post_meta( $drafted, TALA_SEO_DESCRIPTION, true ), 'Only those two fields.' );
		foreach ( array( $editors, $published ) as $post ) {
			$this->assertSame( $values[ TALA_SEO_REDIRECT ], get_post_meta( $post, TALA_SEO_REDIRECT, true ) );
			$this->assertSame( $values[ TALA_SEO_CANONICAL ], get_post_meta( $post, TALA_SEO_CANONICAL, true ) );
		}

		// Later values were set under the rule, by someone who may: the clean-up doesn't run again.
		update_post_meta( $drafted, TALA_SEO_REDIRECT, 'https://example.com/set-by-an-editor/' );
		tala_seo_upgrade();
		$this->assertSame( 'https://example.com/set-by-an-editor/', get_post_meta( $drafted, TALA_SEO_REDIRECT, true ) );
	}
}
