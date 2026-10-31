<?php
/**
 * The admin side: notices, Settings → SEO, the category, tag and author fields, the permalink
 * switch, the editor's settings and the SEO column.
 *
 * @package TalaTests
 */

/**
 * Admin screens.
 */
class Test_Tala_SEO_Admin extends Tala_SEO_Test_Case {

	/**
	 * Administrator.
	 *
	 * @var int
	 */
	private int $admin = 0;

	/**
	 * Logs in an administrator, with no settings messages left from another test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin );
		$GLOBALS['wp_settings_errors'] = array();
	}

	/**
	 * Clears the screen, the submitted form and the current user.
	 */
	public function tear_down(): void {
		$GLOBALS['current_screen'] = null;
		$_POST                     = array();
		$_REQUEST                  = array();
		parent::tear_down();
	}

	/**
	 * The admin notices printed on a screen.
	 *
	 * @param string $screen Screen ID.
	 * @return string
	 */
	private function notices( string $screen ): string {
		set_current_screen( $screen );
		return get_echo( 'do_action', array( 'admin_notices' ) );
	}

	/**
	 * While the site is hidden from search engines, administrators see a warning on every screen
	 * except Settings → Reading itself.
	 */
	public function test_hidden_site_notice(): void {
		update_option( 'blog_public', '0' );
		$this->assertStringContainsString( 'Search engines are blocked.', $this->notices( 'dashboard' ) );
		$this->assertStringContainsString( 'Search engines are blocked.', $this->notices( 'edit-post' ) );
		$this->assertStringNotContainsString( 'Search engines are blocked.', $this->notices( 'options-reading' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertStringNotContainsString( 'Search engines are blocked.', $this->notices( 'dashboard' ), 'Not for editors' );

		wp_set_current_user( $this->admin );
		update_option( 'blog_public', '1' );
		$this->assertStringNotContainsString( 'Search engines are blocked.', $this->notices( 'dashboard' ) );
	}

	/**
	 * The editor gets the SEO panel's settings, on the current editor API only.
	 */
	public function test_editor_settings(): void {
		update_option( 'blog_public', '0' );
		set_current_screen( 'post' );
		tala_seo_editor_assets();
		$config = (string) wp_scripts()->get_inline_script_data( 'tala-seo-editor', 'before' );
		$this->assertStringContainsString( '"sitePublic":false', $config );
		$this->assertStringContainsString( '"primary":"_tala_seo_primary_category"', $config );
		$this->assertStringContainsString( '"redirect":"_tala_seo_redirect"', $config );
		$this->assertStringContainsString( '"primaryTypes":["post"]', $config );

		$deps = wp_scripts()->registered['tala-seo-editor']->deps;
		$this->assertContains( 'wp-editor', $deps );
		$this->assertNotContains( 'wp-edit-post', $deps, 'No fallback for older WordPress' );
	}

	/**
	 * Verification codes: the code alone or the whole meta tag; anything else keeps the code saved
	 * before and is reported once.
	 */
	public function test_verification_codes(): void {
		$google = 'GoogleCode_1234567890';
		$this->assertSame( $google, tala_seo_sanitize_verification( '<meta name="google-site-verification" content="' . $google . '" />', 'google' ) );
		$this->assertSame( 'BingCode1234567890', tala_seo_sanitize_verification( ' BingCode1234567890 ', 'bing' ) );
		$this->assertSame( '', tala_seo_sanitize_verification( '', 'google' ), 'Emptying a field clears it' );

		$this->set_setting( 'verify', array( 'google' => $google ) );
		$refused = array(
			'<script>alert(1)</script>',
			'<meta name="msvalidate.01" content="BingCode1234567890" />', // Another service's tag.
			'google-site-verification=abcdefghijklmnop',                 // The DNS record.
		);
		foreach ( $refused as $value ) {
			$this->assertSame( $google, tala_seo_sanitize_verification( $value, 'google' ), $value );
		}
		$errors = wp_list_pluck( get_settings_errors( 'tala_seo' ), 'code' );
		$this->assertSame( array( 'tala_seo_verify_google' ), $errors, 'Reported once' );
		$this->assertStringContainsString( 'The code saved before was kept.', get_settings_errors( 'tala_seo' )[0]['message'] );
	}

	/**
	 * The other settings are cleaned on save.
	 */
	public function test_settings_are_cleaned(): void {
		$clean = tala_seo_sanitize_settings(
			array(
				'publisher_type'   => 'robot',
				'publisher_name'   => '<b>Guro Notes</b>',
				'same_as'          => "https://www.facebook.com/guronotes\nnot a link\nhttps://www.facebook.com/guronotes\njavascript:alert(1)",
				'home_description' => "Guides\nfor <em>teachers</em>.",
				'default_image'    => '12abc',
				'tag_min_posts'    => '500',
			)
		);
		$this->assertSame( 'organization', $clean['publisher_type'] );
		$this->assertSame( 'Guro Notes', $clean['publisher_name'] );
		$this->assertSame( array( 'https://www.facebook.com/guronotes' ), $clean['same_as'] );
		$this->assertSame( 'Guides for teachers.', $clean['home_description'] );
		$this->assertSame( 12, $clean['default_image'] );
		$this->assertSame( 100, $clean['tag_min_posts'] );
	}

	/**
	 * Category and tag forms save the SEO fields; Quick Edit (no form nonce) leaves them alone.
	 */
	public function test_term_fields(): void {
		$term  = self::factory()->category->create();
		$_POST = array(
			'tag_ID'               => (string) $term,
			'tala_seo_term_nonce'  => wp_create_nonce( 'tala_seo_term_' . $term ),
			'tala_seo_title'       => 'Full guides for teachers',
			'tala_seo_description' => "Every full guide\nin one place.",
			'tala_seo_noindex'     => '1',
		);
		tala_seo_save_term_fields( $term );
		$this->assertSame( 'Full guides for teachers', get_term_meta( $term, TALA_SEO_TITLE, true ) );
		$this->assertSame( 'Every full guide in one place.', get_term_meta( $term, TALA_SEO_DESCRIPTION, true ) );
		$this->assertSame( '1', get_term_meta( $term, TALA_SEO_NOINDEX, true ) );

		$_POST = array( 'tala_seo_title' => 'From Quick Edit' );
		tala_seo_save_term_fields( $term );
		$this->assertSame( 'Full guides for teachers', get_term_meta( $term, TALA_SEO_TITLE, true ), 'Quick Edit' );

		$_POST = array(
			'tag_ID'              => (string) $term,
			'tala_seo_term_nonce' => wp_create_nonce( 'tala_seo_term_' . $term ),
		);
		tala_seo_save_term_fields( $term );
		$this->assertSame( array(), get_term_meta( $term, TALA_SEO_TITLE, false ), 'Emptied fields are deleted.' );
		$this->assertSame( array(), get_term_meta( $term, TALA_SEO_NOINDEX, false ) );
	}

	/**
	 * A category's edit form saves to that category only, and its nonce is good for that category
	 * only: another term WordPress updates in the same request (an alias group) keeps its fields.
	 * The "Add New" form saves the term it creates.
	 */
	public function test_term_fields_save_to_their_own_term(): void {
		$edited = self::factory()->category->create();
		$other  = self::factory()->category->create();
		update_term_meta( $other, TALA_SEO_TITLE, 'Other title' );
		$form  = get_echo( 'tala_seo_term_edit_fields', array( get_term( $edited ) ) );
		$nonce = preg_match( '/name="tala_seo_term_nonce" value="([^"]+)"/', $form, $match ) ? $match[1] : '';
		$_POST = array(
			'tag_ID'              => (string) $edited,
			'tala_seo_term_nonce' => $nonce,
			'tala_seo_title'      => 'Edited title',
		);
		tala_seo_save_term_fields( $other );
		tala_seo_save_term_fields( $edited );
		$this->assertSame( 'Other title', get_term_meta( $other, TALA_SEO_TITLE, true ) );
		$this->assertSame( 'Edited title', get_term_meta( $edited, TALA_SEO_TITLE, true ) );

		$_POST['tag_ID'] = (string) $other;
		tala_seo_save_term_fields( $other );
		$this->assertSame( 'Other title', get_term_meta( $other, TALA_SEO_TITLE, true ), 'Not with another term\'s nonce' );

		$added = self::factory()->category->create();
		$_POST = array(
			'tala_seo_term_nonce' => wp_create_nonce( 'tala_seo_term' ),
			'tala_seo_title'      => 'New title',
		);
		tala_seo_term_field_hooks();
		tala_seo_save_term_fields( $other ); // Not from the "created" hook: another term.
		do_action( 'created_category', $added );
		$this->assertSame( 'Other title', get_term_meta( $other, TALA_SEO_TITLE, true ) );
		$this->assertSame( 'New title', get_term_meta( $added, TALA_SEO_TITLE, true ) );
	}

	/**
	 * The profile's author section, through WordPress's own profile save: valid values are saved,
	 * an invalid Fediverse handle is refused and the profile isn't saved.
	 *
	 * @param string $handle Fediverse handle to submit.
	 * @return WP_Error|int What edit_user() returned.
	 */
	private function save_profile( string $handle ) {
		$user  = get_userdata( $this->admin );
		$_POST = array(
			'email'                       => $user->user_email,
			'nickname'                    => $user->nickname,
			'display_name'                => $user->display_name,
			'tala_seo_author_nonce'       => wp_create_nonce( 'tala_seo_author_' . $this->admin ),
			'tala_seo_author_title'       => 'Tala Tester, teacher',
			'tala_seo_author_description' => 'Guides for teachers by Tala.',
			'tala_seo_author_noindex'     => '1',
			'tala_seo_fediverse'          => $handle,
		);
		return edit_user( $this->admin );
	}

	/**
	 * Author fields and the Fediverse handle.
	 */
	public function test_author_fields(): void {
		$this->assertSame( $this->admin, $this->save_profile( '@Tala@Mastodon.Social' ) );
		$this->assertSame( 'Tala Tester, teacher', get_user_meta( $this->admin, TALA_SEO_TITLE, true ) );
		$this->assertSame( 'Guides for teachers by Tala.', get_user_meta( $this->admin, TALA_SEO_DESCRIPTION, true ) );
		$this->assertSame( '1', get_user_meta( $this->admin, TALA_SEO_NOINDEX, true ) );
		$this->assertSame( '@Tala@mastodon.social', get_user_meta( $this->admin, 'tala_seo_fediverse', true ), 'Server name lowercased' );

		$result = $this->save_profile( 'https://mastodon.social/@tala' );
		$this->assertWPError( $result );
		$this->assertStringContainsString( 'should look like @name@mastodon.social', $result->get_error_message( 'tala_seo_fediverse' ) );
		$this->assertSame( '@Tala@mastodon.social', get_user_meta( $this->admin, 'tala_seo_fediverse', true ), 'The saved handle stays.' );
	}

	/**
	 * Fediverse handles: "@name@server" in any of its usual spellings, nothing else.
	 */
	public function test_fediverse_handles(): void {
		$cases = array(
			'@Tala@Mastodon.Social'         => '@Tala@mastodon.social',
			'tala@mastodon.social'          => '@tala@mastodon.social',
			'acct:tala@mastodon.social'     => '@tala@mastodon.social',
			'https://mastodon.social/@tala' => '',
			'tala'                          => '',
			'@tala@localhost'               => '',
			'@ta la@x.social'               => '',
		);
		foreach ( $cases as $input => $expected ) {
			$this->assertSame( $expected, tala_seo_sanitize_fediverse_handle( $input ), $input );
		}
	}

	/**
	 * The checklist's switch to post-name URLs: only for administrators, with the form's nonce, on a
	 * server that can serve readable URLs.
	 */
	public function test_permalink_switch(): void {
		$this->set_permalink_structure( '' );
		add_filter( 'got_url_rewrite', '__return_true' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		try {
			tala_seo_pretty_permalinks();
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'not allowed to change the permalinks', $e->getMessage() );
		}
		$this->assertSame( '', get_option( 'permalink_structure' ), 'Editors are refused.' );

		wp_set_current_user( $this->admin );
		try {
			tala_seo_pretty_permalinks();
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'The link you followed has expired.', $e->getMessage() );
		}
		$this->assertSame( '', get_option( 'permalink_structure' ), 'No nonce, no change.' );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'tala_seo_pretty_permalinks' );
		$redirect             = $this->redirect_of( 'tala_seo_pretty_permalinks' );
		$this->assertSame( admin_url( 'options-general.php?page=tala-seo&settings-updated=true' ), $redirect?->location );
		$this->assertSame( '/%postname%/', get_option( 'permalink_structure' ) );
		$this->assertSame( 'Readable URLs are on: every post now has a post-name address.', get_transient( 'settings_errors' )[0]['message'] );
	}

	/**
	 * Settings → Permalinks: plain URLs get a recommendation, a site with posts a warning.
	 */
	public function test_permalinks_screen_notices(): void {
		$this->set_permalink_structure( '' );
		$this->assertStringContainsString( 'Choose &quot;Post name&quot;', $this->notices( 'options-permalink' ) );

		$this->set_permalink_structure( '/%postname%/' );
		$this->publish_post();
		$this->assertStringContainsString( 'published post. A different structure gives it a new address', $this->notices( 'options-permalink' ) );
	}

	/**
	 * The SEO column: words and symbols carry the status, with a sentence for screen readers.
	 */
	public function test_seo_column(): void {
		$post = $this->publish_post( array( 'meta_input' => array( TALA_SEO_REDIRECT => 'https://example.com/merged/' ) ) );
		$cell = get_echo( 'tala_seo_post_column', array( 'tala_seo', $post ) );
		$this->assertStringContainsString( 'Redirects<span class="screen-reader-text">: sends visitors to another address.</span>', $cell );
		$this->assertStringContainsString( 'has a featured image with alternative text', $cell );

		$empty = get_term( self::factory()->category->create(), 'category' );
		$cell  = tala_seo_term_column( '', 'tala_seo', $empty->term_id );
		$this->assertStringContainsString( '<span class="tala-seo-status__mark" aria-hidden="true">!</span>Empty', $cell );
		$this->assertStringContainsString( '<span class="screen-reader-text">: no posts yet, so it stays out of search results.</span>', $cell );
	}

	/**
	 * Author profiles get the social profile fields that feed the Person markup.
	 */
	public function test_contact_methods(): void {
		$methods = wp_get_user_contact_methods();
		$this->assertArrayHasKey( 'tala_tiktok', $methods );
		$this->assertArrayHasKey( 'tala_fediverse_url', $methods );
	}

	/**
	 * The author photo can only be an image the person saving the profile may see: not one attached
	 * to someone else's draft.
	 */
	public function test_author_photo_must_be_readable(): void {
		$editor     = self::factory()->user->create( array( 'role' => 'editor' ) );
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$draft      = self::factory()->post->create(
			array(
				'post_author' => $editor,
				'post_status' => 'draft',
			)
		);
		$hidden     = (int) self::factory()->attachment->create_upload_object( dirname( __DIR__, 3 ) . '/dev/media/cover-2.jpg', $draft );

		wp_set_current_user( $subscriber );
		$_POST = array(
			'tala_seo_photo'       => (string) $hidden,
			'tala_seo_photo_nonce' => wp_create_nonce( 'tala_seo_photo_' . $subscriber ),
		);
		tala_seo_save_photo( $subscriber );
		$this->assertSame( '', get_user_meta( $subscriber, 'tala_seo_photo', true ), 'An image of an unpublished post' );

		$_POST['tala_seo_photo'] = (string) self::$image_id;
		tala_seo_save_photo( $subscriber );
		$this->assertSame( (string) self::$image_id, get_user_meta( $subscriber, 'tala_seo_photo', true ), 'A public image' );

		wp_delete_attachment( $hidden, true );
	}

	/**
	 * Multisite: an attachment ID belongs to one site's media library, so the photo chosen on one
	 * site is never looked up in another site's (where the same ID can be any other image).
	 *
	 * @group ms-required
	 */
	public function test_author_photo_is_per_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Runs on a network: composer test:multisite.' );
		}
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$site   = self::factory()->blog->create();
		add_user_to_blog( $site, $author, 'author' );
		switch_to_blog( $site );
		// An image on the second site with the ID the shared image has on the main site.
		$image = self::factory()->attachment->create_object(
			array(
				'file'           => 'photo.jpg',
				'post_mime_type' => 'image/jpeg',
				'import_id'      => self::$image_id,
			)
		);
		$this->assertSame( self::$image_id, $image );
		wp_set_current_user( $author );
		$_POST = array(
			'tala_seo_photo'       => (string) $image,
			'tala_seo_photo_nonce' => wp_create_nonce( 'tala_seo_photo_' . $author ),
		);
		tala_seo_save_photo( $author );
		$this->assertSame( $image, tala_seo_photo_id( $author ), 'Shown on the site it was chosen on' );
		restore_current_blog();

		$this->assertSame( 0, tala_seo_photo_id( $author ), 'Not on another site' );
	}

	/**
	 * The profile forms' nonces are good for the profile they were printed on only: one taken from
	 * another user's form saves nothing, even for someone who may edit both.
	 */
	public function test_profile_nonces_belong_to_their_user(): void {
		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		$nonce = static function ( string $form, string $field ): string {
			return preg_match( '/name="' . $field . '" value="([^"]+)"/', $form, $match ) ? $match[1] : '';
		};
		$admin = get_userdata( $this->admin );

		$_POST = array(
			'tala_seo_photo'       => (string) self::$image_id,
			'tala_seo_photo_nonce' => $nonce( get_echo( 'tala_seo_photo_field', array( $admin ) ), 'tala_seo_photo_nonce' ),
		);
		tala_seo_save_photo( $other );
		$this->assertSame( '', get_user_meta( $other, 'tala_seo_photo', true ), 'Photo' );

		$_POST = array(
			'tala_seo_author_nonce' => $nonce( get_echo( 'tala_seo_author_fields', array( $admin ) ), 'tala_seo_author_nonce' ),
			'tala_seo_author_title' => 'Not theirs',
		);
		tala_seo_save_author_fields( $other );
		$this->assertSame( '', get_user_meta( $other, TALA_SEO_TITLE, true ), 'Author fields' );
	}

	/**
	 * The photo picker is WordPress's media library, which only people who may upload files can
	 * browse: others don't get a picker that can't show them anything.
	 */
	public function test_photo_picker_needs_upload_rights(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		$this->assertStringNotContainsString( 'data-tala-media=', get_echo( 'tala_seo_photo_field', array( get_userdata( $subscriber ) ) ) );
		tala_seo_admin_assets( 'profile.php' );
		$this->assertFalse( wp_script_is( 'tala-seo-admin' ) );

		wp_set_current_user( $this->admin );
		$this->assertStringContainsString( 'data-tala-media=', get_echo( 'tala_seo_photo_field', array( get_userdata( $subscriber ) ) ) );
	}

	/**
	 * The editor's settings can't end their script element or start markup, whatever the site is
	 * called.
	 */
	public function test_editor_settings_are_safe_in_a_script(): void {
		update_option( 'blogname', 'Notes & <3 tips' );
		set_current_screen( 'post' );
		tala_seo_editor_assets();
		$config = (string) wp_scripts()->get_inline_script_data( 'tala-seo-editor', 'before' );
		$this->assertStringContainsString( '\\u003C3', $config );
		$this->assertStringNotContainsString( '<3', $config );
	}

	/**
	 * Who may edit the SEO fields is decided for the user asked about, not whoever is logged in: an
	 * administrator may edit them while a subscriber's request is running.
	 */
	public function test_meta_permissions_are_for_the_user_asked_about(): void {
		$post = $this->publish_post();
		$term = self::factory()->category->create();
		$user = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertTrue( user_can( $this->admin, 'edit_post_meta', $post, TALA_SEO_DESCRIPTION ) );
		$this->assertTrue( user_can( $this->admin, 'edit_post_meta', $post, TALA_SEO_MINOR_EDIT ) );
		$this->assertTrue( user_can( $this->admin, 'edit_term_meta', $term, TALA_SEO_TITLE ) );
		$this->assertTrue( user_can( $this->admin, 'edit_user_meta', $user, TALA_SEO_TITLE ) );
	}


	/**
	 * A damaged option (a list where text belongs, a word where a URL belongs) never reaches the
	 * rest of the plugin: the settings hand out URLs and text only, without a PHP warning.
	 */
	public function test_damaged_settings_are_harmless(): void {
		// Saving cleans the option; only a direct database write could leave it like this.
		$damaged  = static fn() => array(
			'same_as' => array( 'https://www.facebook.com/guronotes', array( 'nested' ), 'not a link', 7 ),
			'verify'  => 'words',
		);
		add_filter( 'pre_option_tala_seo', $damaged );
		$settings = tala_seo_settings();
		remove_filter( 'pre_option_tala_seo', $damaged );
		$this->assertSame( array( 'https://www.facebook.com/guronotes' ), $settings['same_as'] );
		$this->assertSame( '', $settings['verify']['google'] );
	}

	/**
	 * Settings → SEO offers the title separators, and keeps only those.
	 */
	public function test_separator_setting(): void {
		$this->assertSame( '-', tala_seo_sanitize_settings( array( 'separator' => '-' ) )['separator'] );
		$this->assertSame( '•', tala_seo_sanitize_settings( array( 'separator' => '&bull;' ) )['separator'] );
		$this->assertSame( '|', tala_seo_sanitize_settings( array( 'separator' => '"><script>' ) )['separator'] );
		$this->assertSame( '|', tala_seo_sanitize_settings( array() )['separator'] );

		update_option( 'tala_seo', array( 'separator' => '–' ) );
		$field = get_echo( 'tala_seo_field_separator' );
		$this->assertSame( count( tala_seo_separators() ), substr_count( $field, '<option ' ) );
		$this->assertMatchesRegularExpression( '#<option value="–"\s+selected=\'selected\'>Article title – #', $field );
	}

	/**
	 * The checklist asks to turn off attachment pages while they are on, and its button does it
	 * for administrators only, with a valid nonce.
	 */
	public function test_attachment_pages_switch(): void {
		update_option( 'wp_attachment_pages_enabled', '1' );
		$check = array_values( array_filter( tala_seo_readiness(), static fn( $item ) => 'attachment_pages' === ( $item['action'] ?? '' ) ) );
		$this->assertCount( 1, $check );
		$this->assertFalse( $check[0]['ok'] );
		$this->assertStringContainsString( 'value="tala_seo_attachment_pages"', get_echo( 'tala_seo_settings_page' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		try {
			tala_seo_attachment_pages();
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'not allowed to change this setting', $e->getMessage() );
		}
		$this->assertSame( '1', get_option( 'wp_attachment_pages_enabled' ), 'Editors are refused.' );

		wp_set_current_user( $this->admin );
		try {
			tala_seo_attachment_pages();
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'The link you followed has expired.', $e->getMessage() );
		}
		$this->assertSame( '1', get_option( 'wp_attachment_pages_enabled' ), 'No nonce, no change.' );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'tala_seo_attachment_pages' );
		$redirect             = $this->redirect_of( 'tala_seo_attachment_pages' );
		$this->assertSame( admin_url( 'options-general.php?page=tala-seo&settings-updated=true' ), $redirect?->location );
		$this->assertSame( '0', get_option( 'wp_attachment_pages_enabled' ) );
		$ok = array_values( array_filter( tala_seo_readiness(), static fn( $item ) => str_starts_with( $item['text'], 'Attachment pages are off' ) ) );
		$this->assertTrue( $ok[0]['ok'] ?? false );
	}
}
