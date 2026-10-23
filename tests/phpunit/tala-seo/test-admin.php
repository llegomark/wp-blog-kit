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
			'tala_seo_term_nonce'  => wp_create_nonce( 'tala_seo_term' ),
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

		$_POST = array( 'tala_seo_term_nonce' => wp_create_nonce( 'tala_seo_term' ) );
		tala_seo_save_term_fields( $term );
		$this->assertSame( array(), get_term_meta( $term, TALA_SEO_TITLE, false ), 'Emptied fields are deleted.' );
		$this->assertSame( array(), get_term_meta( $term, TALA_SEO_NOINDEX, false ) );
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
			'tala_seo_author_nonce'       => wp_create_nonce( 'tala_seo_author' ),
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
}
