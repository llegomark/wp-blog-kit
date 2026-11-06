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
		$GLOBALS['wp_styles']  = new WP_Styles();
	}

	/**
	 * Drops the scripts and styles this test queued.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
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
		$this->assertSame( 'TECHNOLOGY,LIFESTYLE', tala_ads_sanitize_innity_channel( $zone_tag ), 'The channels from a pasted zone tag.' );
		$this->assertSame( '', tala_ads_sanitize_innity_channel( 'new innity_adZone("0123456789abcdef0123456789abcdef", "120075", {"width": "300", "height": "250"});' ), 'A tag without channels has none.' );
		// Innity's Standard tab gives the standard form of the tag.
		$this->assertSame( '120075', tala_ads_sanitize_innity_zone( '<script type="text/javascript">new innity_adZone("773fc3013fec43c958724a25b7dcb360", "120075", {"width": "300", "height": "250"});</script>' ) ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript

		$clean = tala_ads_sanitize_settings(
			array(
				'adsense' => $auto_ads,
				'consent' => '1',
				'extra'   => '<script>',
			)
		);
		$this->assertSame(
			array(
				'adsense'          => 'ca-pub-1234567890123456',
				'in_article'       => '',
				'multiplex'        => '',
				'consent'          => true,
				'error_protection' => '',
				'gtm'              => '',
				'innity_key'       => '',
				'innity_zone'      => '',
				'innity_desktop'   => '',
				'innity_mobile'    => '',
				'innity_channel'   => '',
				'cse'              => '',
				'search_page'      => 0,
			),
			$clean,
			'Unknown fields are dropped and the switch is a boolean.'
		);
		$this->assertFalse( tala_ads_sanitize_settings( 'not an array' )['consent'] );
	}

	/**
	 * Google's error protection tag as AdSense gives it (shortened: the real function is about 10 KB):
	 * a self-contained function, then one call with the site's configuration in base64.
	 *
	 * @param string $publisher The publisher ID the configuration names.
	 * @param string $address   An address in the configuration.
	 * @return string
	 */
	private function error_protection( string $publisher = 'pub-1234567890123456', string $address = 'https://fundingchoicesmessages.google.com/b/pub-1234567890123456' ): string {
		$config = (string) wp_json_encode(
			array(
				$publisher,
				array( null, null, null, $address ),
				null,
				null,
				'https://fundingchoicesmessages.google.com/el/AGSKWx?te=TOKEN_EXPOSED',
				'div-gpt-ad',
				20,
				100,
				'cHViLTEyMzQ1Njc4OTAxMjM0NTY=',
				array( null, null, null, 'https://www.gstatic.com/0emn/f/p/' . $publisher . '.js?usqp=CA0' ),
			)
		);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Google's tag carries its configuration this way.
		return "(function(){'use strict';var t=this||self;(function(a,b){t[a]=function(c){t[a]=function(){};b(c)}})(\"__h82AlnkH6D91__\",function(a){window.talaTest=a});}).call(this);\n\nwindow.__h82AlnkH6D91__(\"" . base64_encode( $config ) . '");';
	}

	/**
	 * The error protection code is kept only in Google's shape, for the site's own publisher ID,
	 * naming only Google's addresses, with nothing that could close the script or add another.
	 */
	public function test_error_protection_shape(): void {
		$id   = 'ca-pub-1234567890123456';
		$code = $this->error_protection();
		$this->assertSame( $code, tala_ads_sanitize_error_protection( $code, $id ) );
		$this->assertSame( $code, tala_ads_sanitize_error_protection( "<script>{$code}</script>\n", $id ), 'Pasted with its <script> element.' );
		$this->assertSame( $code, tala_ads_sanitize_error_protection( str_replace( "\n", "\r\n", $code ), $id ), 'Line breaks as a form sends them.' );

		$this->assertSame( '', tala_ads_sanitize_error_protection( $code, '' ), 'Not without a publisher ID.' );
		$this->assertSame( '', tala_ads_sanitize_error_protection( $this->error_protection( 'pub-9999999999999999' ), $id ), 'Another site\'s code.' );
		$this->assertSame( '', tala_ads_sanitize_error_protection( $this->error_protection( 'pub-1234567890123456', 'https://evil.example/x' ), $id ), 'An address off Google.' );
		$this->assertSame( '', tala_ads_sanitize_error_protection( $this->error_protection( 'pub-1234567890123456', 'javascript:alert(1)' ), $id ) );
		$this->assertSame( '', tala_ads_sanitize_error_protection( $code . 'alert(1);', $id ), 'Code after the call.' );
		$this->assertSame( '', tala_ads_sanitize_error_protection( str_replace( 'var t=', '</script><script>alert(1)</script>var t=', $code ), $id ) );
		$this->assertSame( '', tala_ads_sanitize_error_protection( str_replace( 'window.__h82AlnkH6D91__(', 'window.__other123__(', $code ), $id ), 'A call to a function the code doesn\'t define.' );
		$this->assertSame( '', tala_ads_sanitize_error_protection( '<script>alert(1)</script>', $id ) );
		$this->assertSame( '', tala_ads_sanitize_error_protection( array( $code ), $id ) );
	}

	/**
	 * Only people WordPress lets add scripts (unfiltered_html) can change the error protection
	 * code; anyone else saving the form keeps the stored one.
	 */
	public function test_error_protection_needs_unfiltered_html(): void {
		$code = $this->error_protection();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option(
			'tala_ads',
			array(
				'adsense'          => 'ca-pub-1234567890123456',
				'error_protection' => $code,
			)
		);
		$this->assertSame( $code, tala_ads_settings()['error_protection'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertFalse( current_user_can( 'unfiltered_html' ) );
		update_option(
			'tala_ads',
			array(
				'adsense'          => 'ca-pub-1234567890123456',
				'gtm'              => 'GTM-ABC1234',
				'error_protection' => '',
			)
		);
		$this->assertSame( $code, tala_ads_settings()['error_protection'], 'The stored code stays.' );
		$this->assertSame( 'GTM-ABC1234', tala_ads_settings()['gtm'], 'The other fields are saved.' );
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
		$this->assertStringNotContainsString( 'data-ad-status', $head, 'No ad styles without ads.' );
		$this->assertStringNotContainsString( 'googletagmanager', $head );
		$this->assertStringNotContainsString( 'fundingchoices', $head );
		$this->assertSame( '', get_echo( 'wp_body_open' ) );
	}

	/**
	 * Tag Manager, the consent message and AdSense each appear once in <head>, AdSense first, with the
	 * attributes its code asks for, and Tag Manager's fallback right after <body>.
	 */
	public function test_head_scripts_once(): void {
		update_option( 'tala_ads', self::ALL );
		$this->go_to( get_permalink( $this->post() ) );
		$head = $this->page_head();

		$this->assertSame( 1, substr_count( $head, 'adsbygoogle.js' ), 'AdSense is loaded once.' );
		$this->assertStringNotContainsString( 'data-ad-status', $head, 'No ad slot is ever hidden.' );
		$this->assertMatchesRegularExpression( '#<style id=["\']tala-ads-inline-css["\']>\s*ins\.adsbygoogle iframe\{color-scheme:light\}\s*(/\*[^*]*\*/\s*)?</style>#', $head, 'Tala Ads\' only style hides nothing.' );
		$this->assertStringContainsString( 'ins.adsbygoogle iframe{color-scheme:light}', $head, 'Ad frames stay see-through in dark mode.' );
		$this->assertMatchesRegularExpression( '#<script[^>]+src="https://pagead2\.googlesyndication\.com/pagead/js/adsbygoogle\.js\?client=ca-pub-1234567890123456"[^>]*>#', $head );
		$this->assertMatchesRegularExpression( '#<script(?=[^>]*\basync\b)(?=[^>]*crossorigin="anonymous")[^>]+adsbygoogle\.js#', $head, 'Async, with crossorigin="anonymous".' );
		$this->assertSame( 1, preg_match( '#<script[^>]+adsbygoogle\.js[^>]*>#', $head, $tag ) );
		$this->assertDoesNotMatchRegularExpression( '#\sdata-#', $tag[0], 'AdSense reads every data- attribute on its tag and warns in the console about those it doesn\'t know, such as WordPress\'s data-wp-strategy.' );

		$this->assertSame( 1, substr_count( $head, "'dataLayer',\"GTM-ABC1234\"" ), 'Tag Manager is loaded once.' );
		$this->assertSame( 1, substr_count( $head, 'fundingchoicesmessages.google.com/i/pub-1234567890123456?ers=1' ), 'The consent message is loaded once.' );
		$this->assertStringContainsString( 'signalGooglefcPresent', $head );
		// WordPress also adds a dns-prefetch hint for AdSense's host higher up: compare the scripts' own tags.
		$this->assertLessThan( strpos( $head, 'id="tala-ads-gtm-js-after"' ), strpos( $head, 'id="tala-ads-adsense-js"' ), 'AdSense comes first.' );
		$this->assertLessThan( strpos( $head, 'id="tala-ads-consent-js"' ), strpos( $head, 'id="tala-ads-gtm-js-after"' ), 'Then Tag Manager, then the consent message.' );
		$this->assertSame( 1, substr_count( $head, 'id="tala-ads-adsense-js"' ), 'Printed once, not again with the other head scripts.' );
		$this->assertSame( 1, substr_count( $head, 'id="tala-ads-gtm-js-after"' ) );

		$this->assertStringNotContainsString( 'tala-ads-error-protection', $head, 'No error protection without its code.' );

		$body = get_echo( 'wp_body_open' );
		$this->assertStringContainsString( '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-ABC1234"', $body );
		$this->assertStringContainsString( 'title="Google Tag Manager"', $body, 'The frame has a name for screen readers.' );
	}

	/**
	 * Only AdSense goes ahead of the page's styles. Tag Manager, Google's messages and error
	 * protection print with the page's other head scripts, after its styles, and Innity at the end of
	 * the page. WordPress's early DNS lookups stay for every server, Innity's included.
	 */
	public function test_only_adsense_first(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option(
			'tala_ads',
			array_merge(
				self::ALL,
				array(
					'innity_desktop'   => '103008',
					'error_protection' => $this->error_protection(),
				)
			)
		);
		$this->go_to( get_permalink( $this->post() ) );
		$head   = $this->page_head();
		$styles = strpos( $head, '<style' );

		$this->assertIsInt( $styles );
		$this->assertLessThan( $styles, strpos( $head, 'id="tala-ads-adsense-js"' ), 'AdSense comes before the page\'s styles, so the browser starts loading it first.' );
		foreach ( array( 'tala-ads-gtm-js-after', 'tala-ads-consent-js', 'tala-ads-consent-signal-js-after', 'tala-ads-error-protection-js-after' ) as $id ) {
			$this->assertGreaterThan( $styles, strpos( $head, 'id="' . $id . '"' ), $id . ' prints with the other head scripts, after the page\'s styles.' );
		}
		$this->assertStringNotContainsString( 'admanager-async.js', $head, 'Innity\'s loader is not in <head>.' );
		$this->assertStringContainsString( 'cdn.innity.net/admanager-async.js', get_echo( 'wp_print_footer_scripts' ), 'Innity loads at the end of the page.' );
		foreach ( array( 'pagead2.googlesyndication.com', 'fundingchoicesmessages.google.com', 'cdn.innity.net' ) as $host ) {
			$this->assertStringContainsString( "<link rel='dns-prefetch' href='//" . $host . "' />", $head, $host . ' keeps its early DNS lookup.' );
		}
	}

	/**
	 * On pages with ads, the browser connects early to AdSense's servers, from the hints at the top of
	 * <head>: the AdSense tag's server in CORS mode, as the tag loads, the ad frames' server in normal
	 * mode, and a DNS lookup for the ads' files. Each once, before the AdSense tag; none without ads.
	 */
	public function test_ad_server_hints(): void {
		update_option( 'tala_ads', self::ALL );
		$this->go_to( get_permalink( $this->post() ) );
		$head = $this->page_head();

		$this->assertSame( 1, preg_match_all( "#<link(?=[^>]*rel='preconnect')(?=[^>]*href='https://pagead2\.googlesyndication\.com')(?=[^>]*crossorigin='anonymous')[^>]*/>#", $head ), 'AdSense\'s server, in CORS mode like its tag.' );
		$this->assertSame( 1, preg_match_all( "#<link[^>]*href='https://googleads\.g\.doubleclick\.net'[^>]*/>#", $head, $frames ) );
		$this->assertStringContainsString( "rel='preconnect'", $frames[0][0] );
		$this->assertStringNotContainsString( 'crossorigin', $frames[0][0], 'The ad frames load in normal mode.' );
		$this->assertSame( 1, substr_count( $head, "<link rel='dns-prefetch' href='//tpc.googlesyndication.com' />" ), 'The ads\' files: a DNS lookup.' );
		foreach ( array( 'googleads.g.doubleclick.net', 'tpc.googlesyndication.com' ) as $host ) {
			$this->assertLessThan( strpos( $head, 'id="tala-ads-adsense-js"' ), strpos( $head, $host ), $host . ' comes before the AdSense tag.' );
		}

		$this->go_to( home_url( '/no-such-page/' ) );
		$head = $this->page_head();
		$this->assertStringNotContainsString( 'googleads.g.doubleclick.net', $head, 'No ads, no early connections.' );
		$this->assertStringNotContainsString( 'tpc.googlesyndication.com', $head );
		$this->assertDoesNotMatchRegularExpression( "#rel='preconnect'[^>]*pagead2|pagead2[^>]*rel='preconnect'#", $head );
	}

	/**
	 * The error protection code goes in <head> after Google's messages tag, on pages with ads, and
	 * only while Google's messages are on.
	 */
	public function test_error_protection_output(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'tala_ads', array_merge( self::ALL, array( 'error_protection' => $this->error_protection() ) ) );
		$this->go_to( get_permalink( $this->post() ) );
		$head = $this->page_head();
		$this->assertSame( 1, substr_count( $head, 'window.__h82AlnkH6D91__(' ) );
		$this->assertMatchesRegularExpression( '#<script id="tala-ads-error-protection-js-after">\s*\(function\(\)\{#', $head );
		$this->assertLessThan( strpos( $head, 'id="tala-ads-error-protection-js-after"' ), strpos( $head, 'id="tala-ads-consent-js"' ), 'After Google\'s messages tag.' );

		$this->go_to( home_url( '/no-such-page/' ) );
		$this->assertStringNotContainsString( '__h82AlnkH6D91__', $this->page_head(), 'Not on pages without ads.' );

		update_option(
			'tala_ads',
			array_merge(
				self::ALL,
				array(
					'consent'          => false,
					'error_protection' => $this->error_protection(),
				)
			)
		);
		$this->go_to( home_url( '/' ) );
		$this->assertStringNotContainsString( '__h82AlnkH6D91__', $this->page_head(), 'Not while Google\'s messages are off.' );
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
	 * Post content with the given paragraphs, as the block editor saves it.
	 *
	 * @param string ...$texts Each paragraph's text.
	 * @return string
	 */
	private function paragraphs( string ...$texts ): string {
		return implode( '', array_map( static fn( string $text ): string => '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->', $texts ) );
	}

	/**
	 * The Innity box: once, after the post's text, in the main loop of the post the page shows,
	 * centred, with 250 px reserved; its zone in the footer as Innity's direct tag gives it (no Ad
	 * Manager click macro), and the loader. The box is never hidden.
	 */
	public function test_innity_box(): void {
		update_option( 'tala_ads', self::ALL );
		$id = $this->post( array( 'post_content' => $this->paragraphs( 'One.', 'Two.', 'Three.', 'Four.', 'Five.', 'Six.', 'Seven.' ) ) );
		$this->go_to( get_permalink( $id ) );
		$this->assertStringNotContainsString( 'tala-ads-innity', apply_filters( 'the_content', 'Text' ), 'Nothing outside the loop.' );
		the_post();

		$content = apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
		$this->assertMatchesRegularExpression( '#<p[^>]*>Seven\.</p>\s*<div class="tala-ads-innity" style="min-height:250px;[^"]*text-align:center"><div id="div-ad-innity-103011/0" style="display:inline-block"></div></div>$#', $content, 'After the text, not in it.' );
		$this->assertSame( 1, substr_count( $content, 'tala-ads-innity' ) );
		$this->assertStringNotContainsString( 'tala-ads-innity', apply_filters( 'the_content', 'Again' ), 'One box per page.' );

		$footer = get_echo( 'wp_print_footer_scripts' );
		$this->assertSame( 1, substr_count( $footer, 'cdn.innity.net/admanager-async.js' ) );
		$this->assertStringContainsString( 'innity_adZoneAsync.display("0123456789abcdef0123456789abcdef","103011",{"target":"div-ad-innity-103011\/0","width":"300","height":"250","channel":"TECHNOLOGY,LIFESTYLE"})', $footer );
		$this->assertStringNotContainsString( 'CLICK_URL', $footer, 'No click macro: only Google Ad Manager fills it in.' );
		$this->assertStringNotContainsString( 'display="none"', $footer, 'The box is never hidden.' );
		$this->assertLessThan( strpos( $footer, 'admanager-async.js' ), strpos( $footer, 'innity_adZoneAsync.q.push' ), 'The zone is queued before the loader.' );
		$this->assertStringNotContainsString( 'data-innity-slot', $content, 'No in-read place without a Universal tag.' );

		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$short                 = $this->post( array( 'post_content' => $this->paragraphs( 'Only.' ) ) );
		$this->go_to( get_permalink( $short ) );
		the_post();
		$this->assertMatchesRegularExpression( '#<p[^>]*>Only\.</p>\s*<div class="tala-ads-innity"[^>]*><div id="div-ad-innity-103011/0"[^>]*></div></div>$#', apply_filters( 'the_content', get_post_field( 'post_content', $short ) ), 'After the text of a short post too.' );
	}

	/**
	 * Innity's Universal tags: on every page with ads, the zone for the screen (wide or phone) chosen
	 * in the browser, as the cached page is the same for every device; one loader with the box.
	 */
	public function test_innity_universal(): void {
		update_option( 'tala_ads', array_merge( self::ALL, array( 'innity_desktop' => '103008' ) ) );
		$id = $this->post( array( 'post_content' => $this->paragraphs( 'One.', 'Two.', 'Three.', 'Four.', 'Five.', 'Six.', 'Seven.' ) ) );
		$this->go_to( get_permalink( $id ) );
		$this->page_head();
		the_post();
		apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
		$footer = get_echo( 'wp_print_footer_scripts' );
		$this->assertStringContainsString( 'innity_adZoneAsync.display("0123456789abcdef0123456789abcdef",zone,{"channel":"TECHNOLOGY,LIFESTYLE"})', $footer );
		$this->assertStringContainsString( 'window.matchMedia("(min-width: 768px)").matches?"103008":""', $footer, 'Wide screens get the desktop zone; phones, without a zone, none.' );
		$this->assertSame( 1, substr_count( $footer, 'cdn.innity.net/admanager-async.js' ), 'One loader for the box and the Universal tag.' );
		$this->assertStringContainsString( 'display("0123456789abcdef0123456789abcdef","103011"', $footer, 'The box stays.' );

		update_option(
			'tala_ads',
			array(
				'innity_key'    => '0123456789abcdef0123456789abcdef',
				'innity_mobile' => '103009',
			)
		);
		$this->go_to( home_url( '/' ) );
		$this->page_head();
		$footer = get_echo( 'wp_print_footer_scripts' );
		$this->assertStringContainsString( 'display("0123456789abcdef0123456789abcdef",zone,{})', $footer, 'On every page, without AdSense or channels.' );
		$this->assertStringContainsString( '.matches?"":"103009"', $footer );

		foreach (
			array(
				'Not without a zone'         => array( 'innity_key' => '0123456789abcdef0123456789abcdef' ),
				'Not without a key'          => array( 'innity_desktop' => '103008' ),
				'Not where ads are kept off' => array(
					'innity_key'     => '0123456789abcdef0123456789abcdef',
					'innity_desktop' => '103008',
				),
			) as $message => $settings
		) {
			update_option( 'tala_ads', $settings );
			if ( 'Not where ads are kept off' === $message ) {
				add_filter( 'tala_ads_show', '__return_false' );
			}
			$this->go_to( home_url( '/' ) );
			$this->page_head();
			$this->assertStringNotContainsString( 'innity', get_echo( 'wp_print_footer_scripts' ), $message );
		}
	}

	/**
	 * Innity's zones go through one gate: with Google's messages on, they wait for Google's consent
	 * data and don't load where the GDPR applies; the Universal tag waits for the page to load;
	 * errors in Innity's code are caught; the box is never hidden.
	 */
	public function test_innity_gate(): void {
		update_option( 'tala_ads', array_merge( self::ALL, array( 'innity_desktop' => '103008' ) ) );
		$id = $this->post( array( 'post_content' => $this->paragraphs( 'One.', 'Two.', 'Three.', 'Four.', 'Five.', 'Six.', 'Seven.' ) ) );
		$this->go_to( get_permalink( $id ) );
		$this->page_head();
		the_post();
		apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
		$footer = get_echo( 'wp_print_footer_scripts' );
		$this->assertSame( 1, substr_count( $footer, 'window.talaAdsInnity=function(show,later)' ), 'One gate for both zones.' );
		$this->assertStringContainsString( 'googlefc.callbackQueue.push({CONSENT_DATA_READY:', $footer );
		$this->assertStringContainsString( 'if(!(ok&&d&&true===d.gdprApplies))run()', $footer, 'No Innity where the GDPR applies.' );
		$this->assertStringContainsString( 'window.addEventListener("load",idle)', $footer );
		$this->assertStringContainsString( '}catch(e){}})},true)}})(window.matchMedia("(min-width: 768px)")', $footer, 'The Universal tag after the page has loaded.' );
		$this->assertStringContainsString( 'try{innity_adZoneAsync.display("0123456789abcdef0123456789abcdef",zone,', $footer, 'Errors in Innity\'s code are caught.' );
		$this->assertStringContainsString( '}catch(e){}})},false);', $footer, 'The box at once.' );
		$this->assertStringNotContainsString( 'display="none"', $footer, 'The box is never hidden.' );
		$this->assertLessThan( strpos( $footer, 'tala-ads-innity-universal-js-after' ), strpos( $footer, 'tala-ads-innity-gate-js-after' ), 'The gate comes first.' );

		update_option( 'tala_ads', array_merge( self::ALL, array( 'consent' => false ) ) );
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$other                 = $this->post( array( 'post_content' => $this->paragraphs( 'One.', 'Two.' ) ) );
		$this->go_to( get_permalink( $other ) );
		the_post();
		apply_filters( 'the_content', get_post_field( 'post_content', $other ) );
		$footer = get_echo( 'wp_print_footer_scripts' );
		$this->assertStringContainsString( 'window.addEventListener("load",idle)}}run();};', $footer, 'Without Google\'s messages, no wait for consent.' );
		$this->assertStringNotContainsString( 'googlefc', $footer );
	}

	/**
	 * With a Universal tag, the site's images carry data-innity-allow-in-image="false", which keeps
	 * Innity's in-image ads off them: images in the content and those printed from the media library,
	 * unless the tala_ads_innity_in_image filter allows them; nothing without a Universal tag.
	 */
	public function test_innity_in_image_off(): void {
		$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$html  = '<p><img src="https://example.org/memo.jpg" width="800" height="1100" alt="A memo"></p>';

		update_option( 'tala_ads', array_merge( self::ALL, array( 'innity_desktop' => '103008' ) ) );
		$this->assertStringContainsString( 'data-innity-allow-in-image="false"', wp_filter_content_tags( $html ), 'An image in the content.' );
		$this->assertStringContainsString( 'data-innity-allow-in-image="false"', wp_get_attachment_image( $image, 'full' ), 'A featured image or logo.' );

		add_filter( 'tala_ads_innity_in_image', '__return_true' );
		$this->assertStringNotContainsString( 'data-innity-allow-in-image', wp_filter_content_tags( $html ), 'Allowed by the filter.' );
		remove_filter( 'tala_ads_innity_in_image', '__return_true' );

		update_option( 'tala_ads', self::ALL );
		$this->assertStringNotContainsString( 'data-innity-allow-in-image', wp_filter_content_tags( $html ), 'The box alone serves no in-image ads.' );
		$this->assertStringNotContainsString( 'data-innity-allow-in-image', wp_get_attachment_image( $image, 'full' ) );
	}

	/**
	 * No Innity on searches or on Google's results page: their addresses carry what readers typed,
	 * and Innity's tags send the full address to its servers.
	 */
	public function test_no_innity_on_searches(): void {
		$results = $this->publish_post(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Search',
				'post_content' => $this->paragraphs( 'One.', 'Two.', 'Three.', 'Four.', 'Five.', 'Six.', 'Seven.' ),
			)
		);
		update_option(
			'tala_ads',
			array_merge(
				self::ALL,
				array(
					'innity_desktop' => '103008',
					'cse'            => '0123456789abcdef0',
					'search_page'    => $results,
				)
			)
		);
		$this->go_to( get_permalink( $results ) );
		$this->page_head();
		the_post();
		$content = apply_filters( 'the_content', get_post_field( 'post_content', $results ) );
		$this->assertStringNotContainsString( 'innity', $content, 'No box or in-read place on the results page.' );
		$this->assertStringNotContainsString( 'innity', get_echo( 'wp_print_footer_scripts' ), 'No Universal tag there.' );

		update_option( 'tala_ads', array_merge( self::ALL, array( 'innity_desktop' => '103008' ) ) );
		$this->post();
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$this->go_to( home_url( '/?s=learners' ) );
		$this->assertTrue( is_search() );
		$this->page_head();
		$this->assertStringNotContainsString( 'innity', get_echo( 'wp_print_footer_scripts' ), 'No Universal tag on WordPress searches.' );
	}

	/**
	 * The In-article and Multiplex units' IDs are kept from the ID or from the unit's code AdSense
	 * gives; anything else is dropped.
	 */
	public function test_ad_unit_ids(): void {
		// AdSense's ad unit codes, exactly as AdSense gives them: input for the ID, never printed.
		$in_article = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1234567890123456" crossorigin="anonymous"></script><ins class="adsbygoogle" style="display:block; text-align:center;" data-ad-layout="in-article" data-ad-format="fluid" data-ad-client="ca-pub-1234567890123456" data-ad-slot="3203298627"></ins><script>(adsbygoogle = window.adsbygoogle || []).push({});</script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$multiplex  = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1234567890123456" crossorigin="anonymous"></script><ins class="adsbygoogle" style="display:block" data-ad-format="autorelaxed" data-ad-client="ca-pub-1234567890123456" data-ad-slot="2403531897"></ins><script>(adsbygoogle = window.adsbygoogle || []).push({});</script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$this->assertSame( '3203298627', tala_ads_sanitize_ad_unit( ' 3203298627 ' ) );
		$this->assertSame( '3203298627', tala_ads_sanitize_ad_unit( $in_article ) );
		$this->assertSame( '2403531897', tala_ads_sanitize_ad_unit( $multiplex ) );
		foreach ( array( '', '123', '32032986270', 'data-ad-slot="320329862"', '<script>alert(1)</script>', array( '3203298627' ) ) as $bad ) {
			$this->assertSame( '', tala_ads_sanitize_ad_unit( $bad ) );
		}
		update_option(
			'tala_ads',
			array(
				'in_article' => $in_article,
				'multiplex'  => $multiplex,
			)
		);
		$this->assertSame( '3203298627', tala_ads_settings()['in_article'] );
		$this->assertSame( '2403531897', tala_ads_settings()['multiplex'] );
	}

	/**
	 * On a post: the In-article ad after the second paragraph (paragraphs in quotations and lists
	 * don't count), with its space reserved; the Multiplex ad right after the text; then Innity's
	 * box and in-read place, below the AdSense ads. One request each in the footer. Nothing hides
	 * any of them.
	 */
	public function test_adsense_units_then_innity(): void {
		update_option(
			'tala_ads',
			array_merge(
				self::ALL,
				array(
					'in_article'     => '3203298627',
					'multiplex'      => '2403531897',
					'innity_desktop' => '103008',
				)
			)
		);
		$id = $this->post(
			array(
				'post_content' => '<!-- wp:paragraph --><p>First.</p><!-- /wp:paragraph -->'
					. '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Quoted.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->'
					. '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li><p>Listed.</p></li><!-- /wp:list-item --></ul><!-- /wp:list -->'
					. '<!-- wp:paragraph --><p>Second.</p><!-- /wp:paragraph -->'
					. '<!-- wp:heading --><h2 class="wp-block-heading">Next</h2><!-- /wp:heading -->'
					. '<!-- wp:paragraph --><p>Third.</p><!-- /wp:paragraph -->',
			)
		);
		$this->go_to( get_permalink( $id ) );
		$head = $this->page_head();
		$this->assertStringNotContainsString( 'adsbygoogle', apply_filters( 'the_content', 'Text' ), 'Nothing outside the loop.' );
		the_post();
		$content = apply_filters( 'the_content', get_post_field( 'post_content', $id ) );

		$this->assertMatchesRegularExpression( '#<p class="wp-block-paragraph">Second\.</p>\s*<div class="tala-ads-in-article" style="min-height:280px;[^"]*"><ins class="adsbygoogle" style="display:block;text-align:center" data-ad-layout="in-article" data-ad-format="fluid" data-ad-client="ca-pub-1234567890123456" data-ad-slot="3203298627"></ins></div><h2#', $content );
		$this->assertMatchesRegularExpression(
			'#<p class="wp-block-paragraph">Third\.</p>\s*'
			. '<div class="tala-ads-multiplex" style="margin:2em 0"><ins class="adsbygoogle" style="display:block" data-ad-format="autorelaxed" data-ad-client="ca-pub-1234567890123456" data-ad-slot="2403531897"></ins></div>'
			. '<div class="tala-ads-innity"[^>]*><div id="div-ad-innity-103011/0"[^>]*></div></div>'
			. '<span id="innity-in-post"[^>]*></span>$#',
			$content,
			'The Multiplex ad right after the text, then Innity.'
		);
		$this->assertSame( 1, substr_count( $content, 'tala-ads-in-article' ) );
		$this->assertSame( 1, substr_count( $content, 'tala-ads-multiplex' ) );
		$this->assertStringNotContainsString( 'data-tala-ads-here', $content );
		$this->assertStringNotContainsString( 'adsbygoogle', apply_filters( 'the_content', get_post_field( 'post_content', $id ) ), 'One of each per page.' );

		$footer = get_echo( 'wp_print_footer_scripts' );
		$this->assertSame( 2, substr_count( $footer, '(adsbygoogle=window.adsbygoogle||[]).push({});' ), 'One request for each unit.' );
		$this->assertSame( 1, substr_count( $head, 'adsbygoogle.js?client=ca-pub-1234567890123456' ), 'Auto ads\' tag loads AdSense once, for the units too.' );
		$this->assertStringNotContainsString( 'data-ad-status', $head . $footer, 'No ad is ever hidden.' );
	}

	/**
	 * No In-article or Multiplex ad without its ID or the publisher ID, on pages, on lists of posts,
	 * on a password-protected post, or on a page without ads; no In-article ad on a post with
	 * nothing after its second paragraph.
	 */
	public function test_adsense_units_only_where_they_belong(): void {
		$long  = $this->paragraphs( 'One.', 'Two.', 'Three.' );
		$check = function ( int $id, string $why ): void {
			$GLOBALS['wp_scripts'] = new WP_Scripts();
			$this->go_to( get_permalink( $id ) );
			the_post();
			$this->assertStringNotContainsString( 'adsbygoogle', apply_filters( 'the_content', get_post_field( 'post_content', $id ) ), $why );
		};
		$units = array(
			'in_article' => '3203298627',
			'multiplex'  => '2403531897',
		);

		update_option( 'tala_ads', self::ALL );
		$check( $this->post( array( 'post_content' => $long ) ), 'Not without the units\' IDs.' );

		update_option( 'tala_ads', $units );
		$check( $this->post( array( 'post_content' => $long ) ), 'Not without the publisher ID.' );

		update_option( 'tala_ads', array_merge( self::ALL, $units ) );
		$check(
			$this->post(
				array(
					'post_type'    => 'page',
					'post_content' => $long,
				)
			),
			'Not on pages.'
		);
		$check(
			$this->post(
				array(
					'post_password' => 'secret',
					'post_content'  => $long,
				)
			),
			'Not before the password is entered.'
		);
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$this->go_to( home_url( '/' ) );
		the_post();
		$this->assertStringNotContainsString( 'adsbygoogle', apply_filters( 'the_content', 'Text' ), 'Not on the home page list.' );

		update_option( 'tala_ads', array_merge( self::ALL, array( 'in_article' => '3203298627' ) ) );
		$check( $this->post( array( 'post_content' => $this->paragraphs( 'One.', 'Two.' ) ) ), 'No In-article ad when nothing follows the second paragraph.' );
	}

	/**
	 * With a Universal tag, the place for Innity's in-read ad: once, after the post's text and the
	 * box.
	 */
	public function test_innity_in_read(): void {
		update_option( 'tala_ads', array_merge( self::ALL, array( 'innity_desktop' => '103008' ) ) );
		$id = $this->post( array( 'post_content' => $this->paragraphs( 'One.', 'Two.', 'Three.', 'Four.', 'Five.', 'Six.', 'Seven.' ) ) );
		$this->go_to( get_permalink( $id ) );
		$this->assertStringNotContainsString( 'data-innity-slot', apply_filters( 'the_content', 'Text' ), 'Nothing outside the loop.' );
		the_post();

		$content = apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
		$this->assertMatchesRegularExpression( '#<p[^>]*>Seven\.</p>\s*<div class="tala-ads-innity"[^>]*><div[^>]*></div></div><span id="innity-in-post" class="innity-in-post" data-innity-slot="in-read" style="display:block"></span>$#', $content, 'A span with the id and class Innity\'s in-read ads look for, after the text and the box.' );
		$this->assertSame( 1, substr_count( $content, 'data-innity-slot' ) );
		$this->assertStringNotContainsString( 'data-innity-slot', apply_filters( 'the_content', 'Again' ), 'Once per page.' );

		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$short                 = $this->post( array( 'post_content' => $this->paragraphs( 'One.', 'Two.' ) ) );
		$this->go_to( get_permalink( $short ) );
		the_post();
		$this->assertMatchesRegularExpression( '#<span id="innity-in-post"[^>]*></span>$#', apply_filters( 'the_content', get_post_field( 'post_content', $short ) ), 'After the text of a shorter post.' );

		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$locked                = $this->post( array( 'post_password' => 'secret' ) );
		$this->go_to( get_permalink( $locked ) );
		the_post();
		$this->assertStringNotContainsString( 'data-innity-slot', apply_filters( 'the_content', 'Text' ), 'Not before the password is entered.' );
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
	 * A results page set up for Google search.
	 *
	 * @param string $content The page's content.
	 * @return int Page ID.
	 */
	private function search_page( string $content = '<!-- wp:paragraph --><p>Did you find what you were looking for?</p><!-- /wp:paragraph -->' . "\n\n" . '<!-- wp:search {"label":"Search","placeholder":"Type here to search TeacherPH"} /-->' ): int {
		$page = $this->publish_post(
			array(
				'post_type'    => 'page',
				'post_name'    => 'search',
				'post_title'   => 'Search Results',
				'post_content' => $content,
			)
		);
		update_option(
			'tala_ads',
			array(
				'cse'         => '0123456789abcdef0',
				'search_page' => $page,
			)
		);
		return $page;
	}

	/**
	 * The engine ID is kept from the ID or Google's code; the results page must be a page.
	 */
	public function test_search_settings(): void {
		$this->assertSame( '43bcda175ae4cfb1a', tala_ads_sanitize_cse( '<script async src="https://cse.google.com/cse.js?cx=43bcda175ae4cfb1a"></script>' ) ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Google's code, pasted into the field.
		$this->assertSame( '012345678901234567890:abcdefghijk', tala_ads_sanitize_cse( ' 012345678901234567890:abcdefghijk ' ), 'The older form of ID.' );
		$this->assertSame( '', tala_ads_sanitize_cse( '43bcda"><script>' ) );
		$this->assertSame( 0, tala_ads_sanitize_search_page( $this->post() ), 'A post is not a page.' );
		$page = $this->publish_post( array( 'post_type' => 'page' ) );
		$this->assertSame( $page, tala_ads_sanitize_search_page( (string) $page ) );
		$this->assertSame( 0, tala_ads_sanitize_search_page( 'x' ) );
	}

	/**
	 * Every WordPress search goes to the results page, with its words in ?q=; feeds of searches
	 * stay; without the setup, WordPress's search works as before.
	 */
	public function test_searches_go_to_google(): void {
		$this->post();
		$this->go_to( home_url( '/?s=lesson' ) );
		$this->assertNull( $this->redirect_of( 'tala_ads_search_redirect' ), 'No redirect until Google search is set up.' );

		$page = $this->search_page();
		$this->go_to( home_url( '/?s=lesson+plan%20&cat=1' ) );
		$redirect = $this->redirect_of( 'tala_ads_search_redirect' );
		$this->assertNotNull( $redirect );
		$this->assertSame( get_permalink( $page ) . '?q=lesson%20plan', $redirect->location );
		$this->assertSame( 302, $redirect->status );

		$this->go_to( home_url( '/?s=' ) );
		$this->assertSame( get_permalink( $page ), $this->redirect_of( 'tala_ads_search_redirect' )?->location, 'An empty search opens the page.' );

		$this->go_to( home_url( '/?s=lesson&feed=rss2' ) );
		$this->assertNull( $this->redirect_of( 'tala_ads_search_redirect' ), 'Feeds of searches stay.' );

		wp_update_post(
			array(
				'ID'          => $page,
				'post_status' => 'draft',
			)
		);
		$this->go_to( home_url( '/?s=lesson' ) );
		$this->assertNull( $this->redirect_of( 'tala_ads_search_redirect' ), 'Not to a page that isn\'t published.' );
	}

	/**
	 * On the results page, its Search block becomes Google's search box and results (once), with the
	 * block's placeholder as the box's hint; Search blocks outside its content are left out there and
	 * stay on other pages; Google's script loads in <head>, and results for a search get noindex.
	 */
	public function test_results_page(): void {
		$page = $this->search_page();
		$this->go_to( get_permalink( $page ) );
		$head = $this->page_head();
		$this->assertSame( 1, substr_count( $head, 'https://cse.google.com/cse.js?cx=0123456789abcdef0' ) );
		$this->assertMatchesRegularExpression( '#<script[^>]+\basync\b[^>]+cse\.google\.com#', $head );
		$this->assertMatchesRegularExpression( '#<style id=["\']tala-ads-google-search-inline-css["\']>#', $head, 'The theme-matching styles, inline (no request).' );
		$this->assertStringContainsString( 'var(--wp--preset--color--contrast, currentcolor)', $head );
		$this->assertMatchesRegularExpression( '#\.tala-ads-google-search \.gsc-table-cell-thumbnail\s*\{\s*display:\s*none#', $head, 'Results without Google\'s thumbnails.' );
		$this->assertStringNotContainsString( 'noindex', $head, 'The page itself may be indexed.' );

		$this->assertMatchesRegularExpression( '#<script(?=[^>]*\sdefer\b)[^>]*/assets/google-search\.js\?ver=#', get_echo( 'wp_print_footer_scripts' ), 'The hint for Google\'s box.' );
		$this->assertSame( '', do_blocks( '<!-- wp:search {"label":"Search"} /-->' ), 'Outside the page\'s content, a Search block is left out: Google\'s is the one.' );
		the_post();
		$content = apply_filters( 'the_content', get_post_field( 'post_content', $page ) );
		$this->assertStringContainsString( 'Did you find what you were looking for?', $content );
		$this->assertSame( 1, substr_count( $content, '<div class="gcse-search" data-queryParameterName="q"></div>' ) );
		$this->assertStringContainsString( '<div class="tala-ads-google-search" data-hint="Type here to search TeacherPH">', $content, 'The block\'s placeholder is the hint.' );
		$this->assertStringNotContainsString( 'wp-block-search', $content, 'Google\'s box replaces the Search block.' );

		$_GET['q'] = 'lesson plan';
		$this->assertMatchesRegularExpression( "#<meta name='robots' content='[^']*\\bnoindex, follow\\b#", $this->page_head(), 'Results for a search stay out of search engines.' );
		unset( $_GET['q'] );

		$this->go_to( get_permalink( $this->post() ) );
		$this->assertStringContainsString( 'wp-block-search', do_blocks( '<!-- wp:search {"label":"Search"} /-->' ), 'On other pages a Search block stays.' );
		$head = $this->page_head();
		$this->assertStringNotContainsString( 'cse.google.com', $head, 'Google\'s script only on the results page.' );
		$this->assertStringNotContainsString( 'tala-ads-google-search', $head, 'And its styles.' );
	}

	/**
	 * A results page without a Search block gets Google's results after its content.
	 */
	public function test_results_page_without_search_block(): void {
		$page = $this->search_page( '<!-- wp:paragraph --><p>Search the site.</p><!-- /wp:paragraph -->' );
		$this->go_to( get_permalink( $page ) );
		the_post();
		$this->assertMatchesRegularExpression( '#Search the site\.</p>\s*<div class="tala-ads-google-search" data-hint="Type here to search"><div class="gcse-search" data-queryParameterName="q"></div></div>#', apply_filters( 'the_content', get_post_field( 'post_content', $page ) ) );
	}

	/**
	 * Settings → Ads shows every field to administrators, and whether ads.txt lists the ID.
	 */
	public function test_settings_page(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		tala_ads_settings_fields();
		$this->assertStringContainsString( 'Publish a page first', get_echo( 'tala_ads_field_search_page' ), 'A site without pages is told what to do.' );
		$this->publish_post( array( 'post_type' => 'page' ) );
		update_option( 'tala_ads', self::ALL );

		$page = get_echo( 'tala_ads_settings_page' );
		foreach ( array( 'adsense', 'consent', 'error_protection', 'gtm', 'innity_key', 'innity_zone', 'innity_desktop', 'innity_mobile', 'innity_channel', 'cse', 'search_page' ) as $key ) {
			$this->assertMatchesRegularExpression( '#name=["\']tala_ads\[' . $key . '\]["\']#', $page, $key );
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
