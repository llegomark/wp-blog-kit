<?php
/**
 * Tala Ads: the settings keep IDs only, each script is printed once and only where ads are allowed,
 * and the Innity box comes once after the article, with its height reserved.
 *
 * @package TalaTests
 */

/**
 * Ads, Tag Manager and the consent message.
 */
class Test_Tala_Ads extends Tala_SEO_Test_Case {

	/**
	 * Settings for a site that uses everything.
	 *
	 * @var array<string, string|bool>
	 */
	private const ALL = array(
		'adsense'        => 'ca-pub-1234567890123456',
		'consent'        => true,
		'gtm'            => 'GTM-ABC1234',
		'innity_key'     => '0123456789abcdef0123456789abcdef',
		'innity_zone'    => '103011',
		'innity_channel' => 'TECHNOLOGY,LIFESTYLE',
	);

	/**
	 * Each test starts with readable URLs and no scripts queued by an earlier one.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_pretty_permalinks();
		$GLOBALS['wp_scripts'] = new WP_Scripts();
	}

	/**
	 * Drops the scripts this test queued.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		parent::tear_down();
	}

	/**
	 * The whole <head> of the current page, with a fresh script queue.
	 *
	 * @return string
	 */
	private function page_head(): string {
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		return get_echo( 'wp_head' );
	}

	/**
	 * A published post (with what Tala SEO's publish check asks for).
	 *
	 * @param array<string, mixed> $args Arguments for wp_insert_post().
	 * @return int Post ID.
	 */
	private function post( array $args = array() ): int {
		return $this->publish_post(
			array_merge(
				array(
					'post_title'   => 'Planning a lesson',
					'post_content' => '<!-- wp:paragraph --><p>Start with what learners should be able to do.</p><!-- /wp:paragraph -->',
				),
				$args
			)
		);
	}

	/**
	 * The fields keep only the ID, from the ID or from the code each service gives.
	 */
	public function test_settings_keep_ids_only(): void {
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- AdSense's code, pasted into the field.
		$auto_ads = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1234567890123456" crossorigin="anonymous"></script>';
		$this->assertSame( 'ca-pub-1234567890123456', tala_ads_sanitize_adsense( $auto_ads ) );
		$this->assertSame( 'ca-pub-1234567890123456', tala_ads_sanitize_adsense( 'google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0' ) );
		$this->assertSame( 'ca-pub-1234567890123456', tala_ads_sanitize_adsense( ' pub-1234567890123456 ' ) );
		$this->assertSame( '', tala_ads_sanitize_adsense( 'ca-pub-12"><script>alert(1)</script>' ) );
		$this->assertSame( '', tala_ads_sanitize_adsense( array( 'ca-pub-1234567890123456' ) ) );

		$gtm = "<script>(function(w,d,s,l,i){…})(window,document,'script','dataLayer','GTM-abc1234');</script>";
		$this->assertSame( 'GTM-ABC1234', tala_ads_sanitize_gtm( $gtm ) );
		$this->assertSame( '', tala_ads_sanitize_gtm( 'GTM-' ) );

		$zone_tag = 'innity_adZoneAsync.q.push(function (){innity_adZoneAsync.display("0123456789ABCDEF0123456789abcdef", "103011", {"target": "div-ad-innity-103011/0","channel": "TECHNOLOGY,LIFESTYLE"});});';
		$this->assertSame( '0123456789abcdef0123456789abcdef', tala_ads_sanitize_innity_key( $zone_tag ) );
		$this->assertSame( '103011', tala_ads_sanitize_innity_zone( $zone_tag ) );
		$this->assertSame( '103011', tala_ads_sanitize_innity_zone( ' 103011 ' ) );
		$this->assertSame( '', tala_ads_sanitize_innity_zone( '103011"; alert(1); "' ) );
		$this->assertSame( 'TECHNOLOGY,LIFESTYLE', tala_ads_sanitize_innity_channel( 'technology, LIFESTYLE,<b>x</b>,lifestyle' ) );

		$clean = tala_ads_sanitize_settings(
			array(
				'adsense' => $auto_ads,
				'consent' => '1',
				'extra'   => '<script>',
			)
		);
		$this->assertSame(
			array(
				'adsense'        => 'ca-pub-1234567890123456',
				'consent'        => true,
				'gtm'            => '',
				'innity_key'     => '',
				'innity_zone'    => '',
				'innity_channel' => '',
			),
			$clean,
			'Unknown fields are dropped and the switch is a boolean.'
		);
		$this->assertFalse( tala_ads_sanitize_settings( 'not an array' )['consent'] );
	}

	/**
	 * A damaged or hand-edited option still prints nothing but IDs.
	 */
	public function test_stored_values_are_checked_again(): void {
		update_option(
			'tala_ads',
			array(
				'adsense' => 'ca-pub-1"><script>alert(1)</script>',
				'gtm'     => 'GTM-ABC1234',
			)
		);
		$this->assertSame( '', tala_ads_settings()['adsense'] );
		$this->assertSame( 'GTM-ABC1234', tala_ads_settings()['gtm'] );
		update_option( 'tala_ads', 'broken' );
		$this->assertSame( '', tala_ads_settings()['gtm'] );
	}

	/**
	 * Nothing is printed until an ID is set.
	 */
	public function test_nothing_without_settings(): void {
		$this->go_to( get_permalink( $this->post() ) );
		$head = $this->page_head();
		$this->assertStringNotContainsString( 'googlesyndication', $head );
		$this->assertStringNotContainsString( 'googletagmanager', $head );
		$this->assertStringNotContainsString( 'fundingchoices', $head );
		$this->assertSame( '', get_echo( 'wp_body_open' ) );
	}

	/**
	 * Tag Manager, the consent message and AdSense each appear once in <head>, AdSense with the
	 * attributes its code asks for, and Tag Manager's fallback right after <body>.
	 */
	public function test_head_scripts_once(): void {
		update_option( 'tala_ads', self::ALL );
		$this->go_to( get_permalink( $this->post() ) );
		$head = $this->page_head();

		$this->assertSame( 1, substr_count( $head, 'adsbygoogle.js' ), 'AdSense is loaded once.' );
		$this->assertMatchesRegularExpression( '#<script[^>]+src="https://pagead2\.googlesyndication\.com/pagead/js/adsbygoogle\.js\?client=ca-pub-1234567890123456"[^>]*>#', $head );
		$this->assertMatchesRegularExpression( '#<script(?=[^>]*\basync\b)(?=[^>]*crossorigin="anonymous")[^>]+adsbygoogle\.js#', $head, 'Async, with crossorigin="anonymous".' );

		$this->assertSame( 1, substr_count( $head, "'dataLayer',\"GTM-ABC1234\"" ), 'Tag Manager is loaded once.' );
		$this->assertSame( 1, substr_count( $head, 'fundingchoicesmessages.google.com/i/pub-1234567890123456?ers=1' ), 'The consent message is loaded once.' );
		$this->assertStringContainsString( 'signalGooglefcPresent', $head );
		// WordPress also adds dns-prefetch hints for these hosts higher up: compare the scripts' own tags.
		$this->assertLessThan( strpos( $head, 'id="tala-ads-consent-js"' ), strpos( $head, 'id="tala-ads-gtm-js-after"' ), 'Tag Manager comes first.' );
		$this->assertLessThan( strpos( $head, 'id="tala-ads-adsense-js"' ), strpos( $head, 'id="tala-ads-consent-js"' ), 'The consent message comes before AdSense.' );

		$body = get_echo( 'wp_body_open' );
		$this->assertStringContainsString( '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-ABC1234"', $body );
		$this->assertStringContainsString( 'title="Google Tag Manager"', $body, 'The frame has a name for screen readers.' );
	}

	/**
	 * The consent message needs the AdSense ID and its switch; Tag Manager works on its own.
	 */
	public function test_each_part_on_its_own(): void {
		update_option(
			'tala_ads',
			array(
				'gtm'     => 'GTM-ABC1234',
				'consent' => true,
			)
		);
		$this->go_to( get_permalink( $this->post() ) );
		$head = $this->page_head();
		$this->assertStringContainsString( 'GTM-ABC1234', $head );
		$this->assertStringNotContainsString( 'fundingchoices', $head, 'No consent message without a publisher ID.' );

		update_option( 'tala_ads', array( 'adsense' => 'ca-pub-1234567890123456' ) );
		$head = $this->page_head();
		$this->assertStringContainsString( 'adsbygoogle.js', $head );
		$this->assertStringNotContainsString( 'fundingchoices', $head, 'The consent message only when switched on.' );
		$this->assertStringNotContainsString( 'googletagmanager', $head );
	}

	/**
	 * No ads on pages without the site's own content (error pages, searches that found nothing),
	 * while Tag Manager still counts the visit. The filter can keep ads off any page.
	 */
	public function test_no_ads_on_empty_pages(): void {
		update_option( 'tala_ads', self::ALL );
		$this->post();

		$this->go_to( home_url( '/no-such-page/' ) );
		$this->assertTrue( is_404() );
		$head = $this->page_head();
		$this->assertStringNotContainsString( 'adsbygoogle.js', $head );
		$this->assertStringContainsString( 'GTM-ABC1234', $head );

		$this->go_to( home_url( '/?s=zzqxnothing' ) );
		$this->assertStringNotContainsString( 'adsbygoogle.js', $this->page_head(), 'A search that found nothing.' );

		$this->go_to( home_url( '/?s=lesson' ) );
		$this->assertStringContainsString( 'adsbygoogle.js', $this->page_head(), 'A search that found something.' );

		add_filter( 'tala_ads_show', '__return_false' );
		$this->go_to( home_url( '/' ) );
		$this->assertStringNotContainsString( 'adsbygoogle.js', $this->page_head() );
	}

	/**
	 * The Innity box: once, after the article's text, in the main loop of the post the page shows,
	 * with 250 px reserved; its zone and loader in the footer.
	 */
	public function test_innity_box(): void {
		update_option( 'tala_ads', self::ALL );
		$id = $this->post();
		$this->go_to( get_permalink( $id ) );
		$this->assertStringNotContainsString( 'tala-ads-innity', apply_filters( 'the_content', 'Text' ), 'Nothing outside the loop.' );
		the_post();

		$content = apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
		$this->assertMatchesRegularExpression( '#</p>\s*<div class="tala-ads-innity" style="min-height:250px;[^"]*"><div id="div-ad-innity-103011/0"></div></div>$#', $content );
		$this->assertStringNotContainsString( 'tala-ads-innity', apply_filters( 'the_content', 'Again' ), 'One box per page.' );

		$footer = get_echo( 'wp_print_footer_scripts' );
		$this->assertSame( 1, substr_count( $footer, 'cdn.innity.net/admanager-async.js' ) );
		$this->assertStringContainsString( 'innity_adZoneAsync.display("0123456789abcdef0123456789abcdef","103011",{"target":"div-ad-innity-103011\/0","width":"300","height":"250","innity_pcu":"%%CLICK_URL_UNESC%%","channel":"TECHNOLOGY,LIFESTYLE"})', $footer );
		$this->assertLessThan( strpos( $footer, 'admanager-async.js' ), strpos( $footer, 'innity_adZoneAsync.q.push' ), 'The zone is queued before the loader.' );
	}

	/**
	 * No Innity box without its settings, on lists of posts, or on a password-protected post.
	 */
	public function test_innity_box_only_where_it_belongs(): void {
		$id = $this->post();
		update_option( 'tala_ads', array( 'adsense' => 'ca-pub-1234567890123456' ) );
		$this->go_to( get_permalink( $id ) );
		the_post();
		$this->assertStringNotContainsString( 'tala-ads-innity', apply_filters( 'the_content', 'Text' ), 'Not without a key and zone.' );

		update_option( 'tala_ads', self::ALL );
		$this->go_to( home_url( '/' ) );
		the_post();
		$this->assertStringNotContainsString( 'tala-ads-innity', apply_filters( 'the_content', 'Text' ), 'Not on the home page list.' );

		$locked = $this->post( array( 'post_password' => 'secret' ) );
		$this->go_to( get_permalink( $locked ) );
		the_post();
		$this->assertStringNotContainsString( 'tala-ads-innity', apply_filters( 'the_content', 'Text' ), 'Not before the password is entered.' );
	}

	/**
	 * Settings → Ads shows every field to administrators, and whether ads.txt lists the ID.
	 */
	public function test_settings_page(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		tala_ads_settings_fields();
		update_option( 'tala_ads', self::ALL );

		$page = get_echo( 'tala_ads_settings_page' );
		foreach ( array( 'adsense', 'consent', 'gtm', 'innity_key', 'innity_zone', 'innity_channel' ) as $key ) {
			$this->assertStringContainsString( 'name="tala_ads[' . $key . ']"', $page );
		}
		$this->assertStringContainsString( 'value="ca-pub-1234567890123456"', $page );
		$this->assertStringContainsString( 'There is no ads.txt', $page );

		$file = ABSPATH . 'ads.txt';
		file_put_contents( $file, "google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		try {
			$this->assertTrue( tala_ads_ads_txt_status( 'ca-pub-1234567890123456' )['ok'] ?? false );
			$this->assertFalse( tala_ads_ads_txt_status( 'ca-pub-9999999999999999' )['ok'] ?? true );
		} finally {
			wp_delete_file( $file );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( '', get_echo( 'tala_ads_settings_page' ), 'Only for people who manage options.' );
	}
}
