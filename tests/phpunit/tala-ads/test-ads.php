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
				'adsense'          => 'ca-pub-1234567890123456',
				'consent'          => true,
				'error_protection' => '',
				'gtm'              => '',
				'innity_key'       => '',
				'innity_zone'      => '',
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
	 * Tag Manager, the consent message and AdSense each appear once in <head>, AdSense with the
	 * attributes its code asks for, and Tag Manager's fallback right after <body>.
	 */
	public function test_head_scripts_once(): void {
		update_option( 'tala_ads', self::ALL );
		$this->go_to( get_permalink( $this->post() ) );
		$head = $this->page_head();

		$this->assertSame( 1, substr_count( $head, 'adsbygoogle.js' ), 'AdSense is loaded once.' );
		$this->assertStringContainsString( 'ins.adsbygoogle[data-ad-status^="unfill"]{display:none!important}', $head, 'Slots Google leaves empty are hidden.' );
		$this->assertStringContainsString( 'ins.adsbygoogle iframe{color-scheme:light}', $head, 'Ad frames stay see-through in dark mode.' );
		$this->assertMatchesRegularExpression( '#<script[^>]+src="https://pagead2\.googlesyndication\.com/pagead/js/adsbygoogle\.js\?client=ca-pub-1234567890123456"[^>]*>#', $head );
		$this->assertMatchesRegularExpression( '#<script(?=[^>]*\basync\b)(?=[^>]*crossorigin="anonymous")[^>]+adsbygoogle\.js#', $head, 'Async, with crossorigin="anonymous".' );

		$this->assertSame( 1, substr_count( $head, "'dataLayer',\"GTM-ABC1234\"" ), 'Tag Manager is loaded once.' );
		$this->assertSame( 1, substr_count( $head, 'fundingchoicesmessages.google.com/i/pub-1234567890123456?ers=1' ), 'The consent message is loaded once.' );
		$this->assertStringContainsString( 'signalGooglefcPresent', $head );
		// WordPress also adds dns-prefetch hints for these hosts higher up: compare the scripts' own tags.
		$this->assertLessThan( strpos( $head, 'id="tala-ads-consent-js"' ), strpos( $head, 'id="tala-ads-gtm-js-after"' ), 'Tag Manager comes first.' );
		$this->assertLessThan( strpos( $head, 'id="tala-ads-adsense-js"' ), strpos( $head, 'id="tala-ads-consent-js"' ), 'The consent message comes before AdSense.' );

		$this->assertStringNotContainsString( 'tala-ads-error-protection', $head, 'No error protection without its code.' );

		$body = get_echo( 'wp_body_open' );
		$this->assertStringContainsString( '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-ABC1234"', $body );
		$this->assertStringContainsString( 'title="Google Tag Manager"', $body, 'The frame has a name for screen readers.' );
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
		foreach ( array( 'adsense', 'consent', 'error_protection', 'gtm', 'innity_key', 'innity_zone', 'innity_channel', 'cse', 'search_page' ) as $key ) {
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
