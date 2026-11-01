<?php
/**
 * Tala Guard: the keys keep Turnstile's shape and the secret is never shown, the widget goes into
 * the comment form only with both keys, and a comment from the public form is saved only when
 * Cloudflare's Siteverify confirms its token for the comment action on the site's own hostname.
 *
 * @package TalaTests
 */

/**
 * Turnstile on the comment form.
 */
class Test_Tala_Guard extends Tala_SEO_Test_Case {

	private const SITE_KEY   = '0x4AAAAAAABkMYinukE8nzYS';
	private const SECRET_KEY = '0x4AAAAAAABkMYinukE8nzYSecretKeyAbCdEf';
	private const TOKEN      = '0.Kk3jT2xjUq1x-token_from_the_widget';

	/**
	 * The Siteverify requests a test made: URL and body.
	 *
	 * @var array<int, array{url:string,body:array<string,string>}>
	 */
	private array $requests = array();

	/**
	 * What the mocked Siteverify answers, one entry per request: an answer, or a WP_Error.
	 *
	 * @var array<int, array<string, mixed>|WP_Error>
	 */
	private array $answers = array();

	/**
	 * Each test starts without keys, with no token sent, a visitor's IP, no flood limit and
	 * Siteverify mocked.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( 'tala_guard' );
		tala_guard_form_comment( false );
		$GLOBALS['wp_scripts']  = new WP_Scripts();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		unset( $_POST['cf-turnstile-response'] );
		add_filter( 'wp_is_comment_flood', '__return_false', 99 );
		add_filter( 'pre_http_request', array( $this, 'siteverify' ), 10, 3 );
	}

	/**
	 * Puts the request back.
	 */
	public function tear_down(): void {
		unset( $_POST['cf-turnstile-response'], $_SERVER['REMOTE_ADDR'] );
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		parent::tear_down();
	}

	/**
	 * The mocked Siteverify: records the request and gives the next answer.
	 *
	 * @param false|array<string, mixed>|WP_Error $preempt Response so far.
	 * @param array<string, mixed>                $args    Request arguments.
	 * @param string                              $url     Request URL.
	 * @return false|array<string, mixed>|WP_Error
	 */
	public function siteverify( $preempt, $args, $url ) {
		if ( TALA_GUARD_SITEVERIFY !== $url ) {
			return $preempt;
		}
		$this->requests[] = array(
			'url'  => $url,
			'body' => $args['body'],
		);
		$answer           = array_shift( $this->answers ) ?? new WP_Error( 'http_request_failed', 'No answer left.' );
		if ( is_wp_error( $answer ) ) {
			return $answer;
		}
		return array(
			'headers'  => array(),
			'body'     => (string) wp_json_encode( $answer ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Saves both keys.
	 *
	 * @param string $secret Secret key.
	 */
	private function set_keys( string $secret = self::SECRET_KEY ): void {
		update_option(
			'tala_guard',
			array(
				'site_key'   => self::SITE_KEY,
				'secret_key' => $secret,
			)
		);
	}

	/**
	 * A successful answer from Siteverify for this site's comment form.
	 *
	 * @param array<string, mixed> $changes Fields to change.
	 * @return array<string, mixed>
	 */
	private function passed( array $changes = array() ): array {
		return array_merge(
			array(
				'success'      => true,
				'challenge_ts' => gmdate( 'Y-m-d\TH:i:s.000\Z' ),
				'hostname'     => wp_parse_url( home_url(), PHP_URL_HOST ),
				'error-codes'  => array(),
				'action'       => 'comment',
				'cdata'        => '',
			),
			$changes
		);
	}

	/**
	 * Sends a comment through the public comment form (as wp-comments-post.php does).
	 *
	 * @param string|null          $token  The form's token, or null for none.
	 * @param array<string, mixed> $fields Form fields to change.
	 * @return WP_Comment|WP_Error
	 */
	private function send_comment( ?string $token, array $fields = array() ) {
		if ( null !== $token ) {
			$_POST['cf-turnstile-response'] = $token;
		}
		$post = $this->publish_post( array( 'comment_status' => 'open' ) );
		return wp_handle_comment_submission(
			array_merge(
				array(
					'comment_post_ID' => $post,
					'comment'         => 'Thank you, this helped my class. ' . wp_generate_password( 8, false ),
					'author'          => 'Ana Reyes',
					'email'           => 'ana' . wp_rand() . '@example.com',
					'url'             => '',
				),
				$fields
			)
		);
	}

	/**
	 * The keys keep Turnstile's shape, from the key alone or from the widget's code; anything else
	 * is no key.
	 */
	public function test_keys_keep_their_shape(): void {
		$this->assertSame( self::SITE_KEY, tala_guard_sanitize_site_key( '  ' . self::SITE_KEY . ' ' ) );
		$this->assertSame( self::SITE_KEY, tala_guard_sanitize_site_key( '<div class="cf-turnstile" data-sitekey="' . self::SITE_KEY . '"></div>' ), 'From the widget\'s code.' );
		$this->assertSame( '1x00000000000000000000AA', tala_guard_sanitize_site_key( '1x00000000000000000000AA' ), 'A test key.' );
		$this->assertSame( self::SECRET_KEY, tala_guard_sanitize_secret_key( self::SECRET_KEY ) );
		foreach ( array( '', 'abc', '0x4AAA"><script>alert(1)</script>', "0x4AAAAAAA\nBkMYinukE8nzYS", 'x' . self::SITE_KEY, array( self::SITE_KEY ), 42 ) as $bad ) {
			$this->assertSame( '', tala_guard_sanitize_site_key( $bad ), 'Not a site key: ' . wp_json_encode( $bad ) );
			$this->assertSame( '', tala_guard_sanitize_secret_key( $bad ), 'Not a secret key: ' . wp_json_encode( $bad ) );
		}
	}

	/**
	 * Saving the form keeps the saved secret when its field is left empty, replaces it with a new
	 * one, and forgets it when the site key is emptied (which turns the check off).
	 */
	public function test_saving_keeps_the_secret(): void {
		$this->set_keys();
		$this->assertTrue( tala_guard_active() );

		$this->assertSame(
			array(
				'site_key'   => self::SITE_KEY,
				'secret_key' => self::SECRET_KEY,
			),
			tala_guard_save_settings(
				array(
					'site_key'   => self::SITE_KEY,
					'secret_key' => '',
				)
			),
			'An empty secret field keeps the secret.'
		);
		$this->assertSame(
			self::SECRET_KEY,
			tala_guard_save_settings(
				array(
					'site_key'   => self::SITE_KEY,
					'secret_key' => 'not a key',
				)
			)['secret_key'],
			'A wrong secret doesn\'t replace the saved one.'
		);
		$this->assertSame(
			'0x4AAAAAAANewSecretKeyNewSecretKey00',
			tala_guard_save_settings(
				array(
					'site_key'   => self::SITE_KEY,
					'secret_key' => '0x4AAAAAAANewSecretKeyNewSecretKey00',
				)
			)['secret_key']
		);
		$this->assertSame(
			array(
				'site_key'   => '',
				'secret_key' => '',
			),
			tala_guard_save_settings(
				array(
					'site_key'   => '',
					'secret_key' => '',
				)
			),
			'No site key: off, and the secret goes too.'
		);

		// Stored without the form's cleaning (as a hand edit or a damaged option would be).
		remove_all_filters( 'sanitize_option_tala_guard' );
		update_option(
			'tala_guard',
			array(
				'site_key'   => '"><script>alert(1)</script>',
				'secret_key' => self::SECRET_KEY,
			)
		);
		$this->assertFalse( tala_guard_active(), 'A stored value is checked again on the way out.' );
	}

	/**
	 * The settings form never shows the secret key.
	 */
	public function test_secret_is_never_shown(): void {
		$this->set_keys();
		$field = get_echo( 'tala_guard_field_secret_key' );
		$this->assertStringContainsString( 'type="password"', $field );
		$this->assertStringContainsString( 'value=""', $field );
		$this->assertStringNotContainsString( self::SECRET_KEY, $field );
		$this->assertStringNotContainsString( self::SECRET_KEY, get_echo( 'tala_guard_section' ), 'Nor the section.' );
		$this->assertStringContainsString( self::SITE_KEY, get_echo( 'tala_guard_field_site_key' ), 'The site key is public.' );
	}

	/**
	 * Without both keys the comment form is WordPress's own, nothing is loaded, and comments are
	 * saved as before, without asking Cloudflare.
	 */
	public function test_nothing_without_keys(): void {
		update_option( 'tala_guard', array( 'site_key' => self::SITE_KEY ) );
		$post = $this->publish_post( array( 'comment_status' => 'open' ) );
		$form = get_echo( 'comment_form', array( array(), $post ) );
		$this->assertStringContainsString( 'id="commentform"', $form );
		$this->assertStringNotContainsString( 'tala-guard', $form );
		$this->assertFalse( wp_script_is( 'tala-guard', 'enqueued' ) );

		$this->assertInstanceOf( WP_Comment::class, $this->send_comment( null ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * With both keys, the widget's place goes right before the submit button, with the public site
	 * key, the comment action, its height kept free, a status line and a note for browsers without
	 * JavaScript, and the script is loaded deferred in the footer. The secret is nowhere on the page.
	 */
	public function test_widget_in_comment_form(): void {
		$this->set_keys();
		$post = $this->publish_post( array( 'comment_status' => 'open' ) );
		$form = get_echo( 'comment_form', array( array(), $post ) );

		$this->assertMatchesRegularExpression(
			'#<div class="tala-guard-turnstile" data-sitekey="' . self::SITE_KEY . '" data-action="comment" style="min-height:65px"></div><div class="tala-guard-status" role="status" data-checking="[^"]+" data-failed="[^"]+"></div><noscript><p class="tala-guard-noscript">[^<]+</p></noscript><p class="form-submit[ "]#',
			$form
		);
		$this->assertStringNotContainsString( self::SECRET_KEY, $form );
		$this->assertTrue( wp_script_is( 'tala-guard', 'enqueued' ) );
		$script = wp_scripts()->registered['tala-guard'];
		$this->assertSame( 1, $script->extra['group'] ?? null, 'In the footer.' );
		$this->assertSame( 'defer', $script->extra['strategy'] ?? null );
		$this->assertStringEndsWith( 'tala-guard/assets/turnstile.js', $script->src );
	}

	/**
	 * A comment whose token Cloudflare confirms is saved. Siteverify gets the secret, the token, the
	 * visitor's IP and an idempotency key.
	 */
	public function test_confirmed_comment_is_saved(): void {
		$this->set_keys();
		$this->answers = array( $this->passed() );
		$comment       = $this->send_comment( self::TOKEN );

		$this->assertInstanceOf( WP_Comment::class, $comment );
		$this->assertCount( 1, $this->requests );
		$body = $this->requests[0]['body'];
		$this->assertSame( self::SECRET_KEY, $body['secret'] );
		$this->assertSame( self::TOKEN, $body['response'] );
		$this->assertSame( '203.0.113.7', $body['remoteip'] );
		$this->assertTrue( wp_is_uuid( $body['idempotency_key'], 4 ) );
	}

	/**
	 * A comment without a token, or with one longer than Turnstile's 2048 characters, is turned
	 * away (403) without asking Cloudflare.
	 */
	public function test_comment_without_token_is_turned_away(): void {
		$this->set_keys();
		foreach ( array( null, '', str_repeat( 'a', 2049 ) ) as $token ) {
			$result = $this->send_comment( $token );
			$this->assertWPError( $result );
			$this->assertSame( 'tala_guard_failed', $result->get_error_code() );
			$this->assertSame( 403, $result->get_error_data() );
			unset( $_POST['cf-turnstile-response'] );
		}
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 0, (int) get_comments( array( 'count' => true ) ) );
	}

	/**
	 * Cloudflare's answer must say success, for the comment action, on one of the site's hostnames;
	 * otherwise the comment is turned away (403) and the answer goes to tala_guard_turned_away.
	 */
	public function test_answer_must_match(): void {
		$this->set_keys();
		$heard = array();
		add_action(
			'tala_guard_turned_away',
			static function ( $answer ) use ( &$heard ): void {
				$heard[] = $answer;
			}
		);
		$wrong = array(
			'failed check'   => array(
				'success'     => false,
				'error-codes' => array( 'invalid-input-response' ),
			),
			'spent token'    => array(
				'success'     => false,
				'error-codes' => array( 'timeout-or-duplicate' ),
			),
			'another form'   => array( 'action' => 'contact' ),
			'another site'   => array( 'hostname' => 'spam.example.net' ),
			'success string' => array( 'success' => 'true' ),
		);
		foreach ( $wrong as $what => $changes ) {
			$this->answers = array( $this->passed( $changes ) );
			$result        = $this->send_comment( self::TOKEN );
			$this->assertWPError( $result, $what );
			$this->assertSame( 403, $result->get_error_data(), $what );
		}
		$this->assertCount( count( $wrong ), $heard );
		$this->assertSame( 0, (int) get_comments( array( 'count' => true ) ) );

		add_filter(
			'tala_guard_hostnames',
			static function ( array $hosts ): array {
				$hosts[] = 'spam.example.net';
				return $hosts;
			}
		);
		$this->answers = array( $this->passed( array( 'hostname' => 'spam.example.net' ) ) );
		$this->assertInstanceOf( WP_Comment::class, $this->send_comment( self::TOKEN ), 'A hostname added through the filter.' );
	}

	/**
	 * A network error or Cloudflare's internal error is retried once, with the same idempotency
	 * key; when Cloudflare still can't answer, the comment is turned away (503): it fails closed.
	 */
	public function test_retry_then_fail_closed(): void {
		$this->set_keys();

		$this->answers = array( new WP_Error( 'http_request_failed', 'cURL error 28' ), $this->passed() );
		$this->assertInstanceOf( WP_Comment::class, $this->send_comment( self::TOKEN ), 'Saved on the retry.' );
		$this->assertCount( 2, $this->requests );
		$this->assertSame( $this->requests[0]['body']['idempotency_key'], $this->requests[1]['body']['idempotency_key'] );

		$this->requests = array();
		$this->answers  = array(
			array(
				'success'     => false,
				'error-codes' => array( 'internal-error' ),
			),
			$this->passed(),
		);
		$this->assertInstanceOf( WP_Comment::class, $this->send_comment( self::TOKEN ), 'Cloudflare\'s internal error, then success.' );
		$this->assertCount( 2, $this->requests );

		$this->requests = array();
		$this->answers  = array( new WP_Error( 'http_request_failed', 'cURL error 6' ), new WP_Error( 'http_request_failed', 'cURL error 6' ) );
		$result         = $this->send_comment( self::TOKEN );
		$this->assertWPError( $result );
		$this->assertSame( 'tala_guard_unreachable', $result->get_error_code() );
		$this->assertSame( 503, $result->get_error_data() );
		$this->assertCount( 2, $this->requests, 'Two tries, no more.' );
	}

	/**
	 * Cloudflare's test keys answer with a fixed action and hostname ("test", "localhost"): with a
	 * test secret, success is enough.
	 */
	public function test_test_keys(): void {
		$this->set_keys( '1x0000000000000000000000000000000AA' );
		$this->assertTrue( tala_guard_test_keys() );
		$this->answers = array(
			$this->passed(
				array(
					'action'   => 'test',
					'hostname' => 'localhost',
				)
			),
		);
		$this->assertInstanceOf( WP_Comment::class, $this->send_comment( 'XXXX.DUMMY.TOKEN.XXXX' ) );
		$this->assertStringContainsString( 'test keys', get_echo( 'tala_guard_section' ) );

		$this->set_keys();
		$this->assertFalse( tala_guard_test_keys() );
	}

	/**
	 * A comment WordPress turns away first (here: no name and email) never spends its token.
	 */
	public function test_wordpress_checks_come_first(): void {
		$this->set_keys();
		update_option( 'require_name_email', 1 );
		$this->answers = array( $this->passed() );
		$result        = $this->send_comment(
			self::TOKEN,
			array(
				'author' => '',
				'email'  => '',
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( 'require_name_email', $result->get_error_code() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Comments that don't come through the public form (a reply written in wp-admin, a logged-in
	 * user's comment through the REST API) aren't asked for a token.
	 */
	public function test_only_the_public_form(): void {
		$this->set_keys();
		$post = $this->publish_post( array( 'comment_status' => 'open' ) );
		$id   = wp_new_comment(
			array(
				'comment_post_ID'      => $post,
				'comment_content'      => 'A reply from the editor.',
				'comment_author'       => 'Editor',
				'comment_author_email' => 'editor@example.com',
				'comment_author_url'   => '',
				'comment_type'         => 'comment',
			),
			true
		);
		$this->assertIsInt( $id );
		$this->assertSame( array(), $this->requests );
	}
}
