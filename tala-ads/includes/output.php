<?php
/**
 * What visitors get: Google Tag Manager, Google's consent message, the AdSense Auto ads code, the
 * In-article ad unit and the Innity box. Every script goes through WordPress's script loader, once per page, so it works
 * with any theme and nothing loads twice.
 *
 * @package TalaAds
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'tala_ads_enqueue' );

/**
 * The scripts in <head>, in the order Google asks for: Tag Manager as high as it can be, then the
 * consent message, then AdSense, which places the Auto ads itself.
 */
function tala_ads_enqueue(): void {
	$settings = tala_ads_settings();

	if ( '' !== $settings['gtm'] ) {
		wp_register_script( 'tala-ads-gtm', false, array(), TALA_ADS_VERSION, array( 'in_footer' => false ) );
		wp_enqueue_script( 'tala-ads-gtm' );
		wp_add_inline_script(
			'tala-ads-gtm',
			sprintf(
				"(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',%s);",
				wp_json_encode( $settings['gtm'] )
			)
		);
	}

	if ( '' === $settings['adsense'] ) {
		return;
	}

	// Google's messages (AdSense → Privacy & messaging): the consent message and the ad blocking
	// recovery message come from the same tag. The second script tells them the first one ran.
	if ( $settings['consent'] ) {
		wp_enqueue_script(
			'tala-ads-consent',
			'https://fundingchoicesmessages.google.com/i/' . substr( $settings['adsense'], 3 ) . '?ers=1',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the service's own address, exactly as it gives it.
			array(
				'strategy'  => 'async',
				'in_footer' => false,
			)
		);
		wp_register_script( 'tala-ads-consent-signal', false, array(), TALA_ADS_VERSION, array( 'in_footer' => false ) );
		wp_enqueue_script( 'tala-ads-consent-signal' );
		wp_add_inline_script(
			'tala-ads-consent-signal',
			"(function(){function signalGooglefcPresent(){if(!window.frames['googlefcPresent']){if(document.body){var iframe=document.createElement('iframe');iframe.style='width: 0; height: 0; border: none; z-index: -1000; left: -1000px; top: -1000px;';iframe.style.display='none';iframe.name='googlefcPresent';document.body.appendChild(iframe);}else{setTimeout(signalGooglefcPresent,0);}}}signalGooglefcPresent();})();"
		);
		// Google's error protection tag: when an ad blocker stops the recovery message itself, it asks
		// the reader to allow ads. Only on pages that show ads; checked against its shape on save and
		// again here (tala_ads_sanitize_error_protection()).
		if ( '' !== $settings['error_protection'] && tala_ads_show_ads() ) {
			wp_register_script( 'tala-ads-error-protection', false, array(), TALA_ADS_VERSION, array( 'in_footer' => false ) );
			wp_enqueue_script( 'tala-ads-error-protection' );
			wp_add_inline_script( 'tala-ads-error-protection', $settings['error_protection'] );
		}
	}

	if ( tala_ads_show_ads() ) {
		// A slot Auto ads place but Google doesn't fill is marked "unfilled" (collapsed, but its ad
		// frame stays visible: a white box over the text) or "unfill-optimized" (a blank gap). Google's
		// recommendation is to hide unfilled slots; both are hidden here.
		// Ad frames are light pages. On a page in dark mode the browser paints a frame of a light page
		// white until its ad arrives (and through an unfilled slot's last moment); declared light, the
		// frame stays see-through, as on a light page, and an ad paints its own background.
		wp_register_style( 'tala-ads', false, array(), TALA_ADS_VERSION );
		wp_enqueue_style( 'tala-ads' );
		// The in-article unit's reserved space closes with its slot when Google leaves it unfilled.
		wp_add_inline_style( 'tala-ads', 'ins.adsbygoogle[data-ad-status^="unfill"]{display:none!important}ins.adsbygoogle iframe{color-scheme:light}.tala-ads-in-article:has(>ins[data-ad-status^="unfill"]){display:none}' );
		wp_enqueue_script(
			'tala-ads-adsense',
			'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . $settings['adsense'],
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the service's own address, exactly as it gives it.
			array(
				'strategy'  => 'async',
				'in_footer' => false,
			)
		);
	}
}

add_filter( 'wp_script_attributes', 'tala_ads_script_attributes' );

/**
 * AdSense's code asks for crossorigin="anonymous" on its script. AdSense also reads every data-
 * attribute on that tag as a setting and warns in the console about any it doesn't know, so the
 * data-wp-strategy note WordPress adds to scripts with a loading strategy comes off (the tag keeps
 * its async attribute).
 *
 * @param array<string, string|bool> $attributes Script tag attributes.
 * @return array<string, string|bool>
 */
function tala_ads_script_attributes( $attributes ) {
	if ( 'tala-ads-adsense-js' === ( $attributes['id'] ?? '' ) ) {
		$attributes['crossorigin'] = 'anonymous';
		unset( $attributes['data-wp-strategy'] );
	}
	return $attributes;
}

add_action( 'wp_body_open', 'tala_ads_body_open' );

/**
 * Tag Manager's fallback for browsers without JavaScript, right after <body> as Tag Manager asks.
 */
function tala_ads_body_open(): void {
	$gtm = tala_ads_settings()['gtm'];
	if ( '' === $gtm ) {
		return;
	}
	printf(
		'<noscript><iframe src="%s" height="0" width="0" style="display:none;visibility:hidden" title="%s"></iframe></noscript>' . "\n",
		esc_url( 'https://www.googletagmanager.com/ns.html?id=' . $gtm ),
		esc_attr__( 'Google Tag Manager', 'tala-ads' )
	);
}

/**
 * Whether the current page may show ads. AdSense doesn't allow ads on pages without the site's own
 * content, such as error pages and searches that found nothing; feeds and embeds aren't pages. The
 * "tala_ads_show" filter has the last word (return false to keep ads off a page).
 *
 * @return bool
 */
function tala_ads_show_ads() {
	global $wp_query;
	$empty_search = is_search() && $wp_query instanceof WP_Query && 0 === (int) $wp_query->found_posts;
	$show         = ! is_404() && ! $empty_search && ! is_feed() && ! is_embed();
	return (bool) apply_filters( 'tala_ads_show', $show );
}

/**
 * Post types whose pages get the Innity box after their content.
 *
 * @return string[]
 */
function tala_ads_post_types() {
	return (array) apply_filters( 'tala_ads_post_types', array( 'post', 'page' ) );
}

add_filter( 'the_content', 'tala_ads_innity_box', 20 );

/**
 * The Innity box after the article's own text, on the post or page the request shows (in the main
 * loop, once). Its 250 px are reserved before the ad arrives, so the page doesn't jump.
 *
 * @param string $content Post content.
 * @return string
 */
function tala_ads_innity_box( $content ) {
	$settings = tala_ads_settings();
	if ( '' === $settings['innity_key'] || '' === $settings['innity_zone']
		|| ! is_singular( tala_ads_post_types() ) || ! in_the_loop() || ! is_main_query()
		|| get_the_ID() !== get_queried_object_id() || post_password_required()
		|| wp_script_is( 'tala-ads-innity-zone' ) || ! tala_ads_show_ads() ) {
		return $content;
	}
	$target = 'div-ad-innity-' . $settings['innity_zone'] . '/0';
	$args   = array(
		'target'     => $target,
		'width'      => '300',
		'height'     => '250',
		'innity_pcu' => '%%CLICK_URL_UNESC%%',
	);
	if ( '' !== $settings['innity_channel'] ) {
		$args['channel'] = $settings['innity_channel'];
	}
	// The zone goes into Innity's queue first; its loader, which may arrive before or after, works
	// through the queue.
	wp_register_script( 'tala-ads-innity-zone', false, array(), TALA_ADS_VERSION, array( 'in_footer' => true ) );
	wp_enqueue_script( 'tala-ads-innity-zone' );
	wp_add_inline_script(
		'tala-ads-innity-zone',
		sprintf(
			'window.innity_adZoneAsync=window.innity_adZoneAsync||{};innity_adZoneAsync.q=innity_adZoneAsync.q||[];innity_adZoneAsync.q.push(function(){innity_adZoneAsync.display(%s,%s,%s);});',
			wp_json_encode( $settings['innity_key'] ),
			wp_json_encode( $settings['innity_zone'] ),
			wp_json_encode( $args )
		)
	);
	wp_enqueue_script(
		'tala-ads-innity',
		'https://cdn.innity.net/admanager-async.js',
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the service's own address, exactly as it gives it.
		array(
			'strategy'  => 'async',
			'in_footer' => true,
		)
	);
	return $content . sprintf(
		'<div class="tala-ads-innity" style="min-height:250px;margin:2em auto;text-align:center"><div id="%s"></div></div>',
		esc_attr( $target )
	);
}

// Before the Innity box (20), which then follows the article's text, not the in-article ad.
add_filter( 'the_content', 'tala_ads_in_article', 19 );

/**
 * The In-article ad unit after the post's second paragraph, where Google suggests placing it, on the
 * post the request shows (in the main loop, once). Its space is kept free while the ad loads, about
 * an In-article ad's height on a phone, so the text below doesn't jump. Only the article's own
 * paragraphs count, not those in lists, quotations, tables or FAQ answers; a post with fewer than
 * two, or nothing after its second, gets no in-article ad. Auto ads then keep their distance from it.
 *
 * @param string $content Post content.
 * @return string
 */
function tala_ads_in_article( $content ) {
	$settings = tala_ads_settings();
	if ( '' === $settings['adsense'] || '' === $settings['in_article']
		|| ! is_singular( (array) apply_filters( 'tala_ads_in_article_post_types', array( 'post' ) ) )
		|| ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id()
		|| post_password_required() || wp_script_is( 'tala-ads-in-article' ) || ! tala_ads_show_ads() ) {
		return $content;
	}
	$unit   = sprintf(
		'<div class="tala-ads-in-article" style="min-height:280px;margin:2em 0"><ins class="adsbygoogle" style="display:block;text-align:center" data-ad-layout="in-article" data-ad-format="fluid" data-ad-client="%s" data-ad-slot="%s"></ins></div>',
		esc_attr( $settings['adsense'] ),
		esc_attr( $settings['in_article'] )
	);
	$placed = tala_ads_after_paragraphs( $content, 2, $unit );
	if ( null === $placed ) {
		return $content;
	}
	// AdSense fills the slot when asked; the request waits in its queue until its script has loaded.
	wp_register_script( 'tala-ads-in-article', false, array(), TALA_ADS_VERSION, array( 'in_footer' => true ) );
	wp_enqueue_script( 'tala-ads-in-article' );
	wp_add_inline_script( 'tala-ads-in-article', '(adsbygoogle=window.adsbygoogle||[]).push({});' );
	return $placed;
}

/**
 * The HTML with $insert after its $count-th top-level paragraph, before the element that follows it;
 * null when there is no such place, or WordPress's HTML API can't read the HTML.
 *
 * @param string $html   Post content.
 * @param int    $count  Paragraphs to pass.
 * @param string $insert Markup to add.
 * @return string|null
 */
function tala_ads_after_paragraphs( $html, $count, $insert ) {
	$processor = WP_HTML_Processor::create_fragment( $html );
	if ( null === $processor ) {
		return null;
	}
	$base   = null; // The depth of the content's top-level elements.
	$seen   = 0;
	$marked = false;
	while ( $processor->next_tag() ) {
		$depth = $processor->get_current_depth();
		$base  = $base ?? $depth;
		if ( $depth !== $base ) {
			continue;
		}
		if ( $seen >= $count ) {
			$processor->set_attribute( 'data-tala-ads-here', true );
			$marked = true;
			break;
		}
		if ( 'P' === $processor->get_tag() ) {
			++$seen;
		}
	}
	if ( ! $marked || null !== $processor->get_last_error() ) {
		return null;
	}
	$updated = $processor->get_updated_html();
	$marker  = ' data-tala-ads-here';
	$at      = strpos( $updated, $marker );
	// A new attribute follows the tag's name, so the tag starts at the last "<" before it.
	$start = false === $at ? false : strrpos( substr( $updated, 0, $at ), '<' );
	if ( false === $at || false === $start ) {
		return null;
	}
	$updated = substr_replace( $updated, '', $at, strlen( $marker ) );
	return substr_replace( $updated, $insert, $start, 0 );
}
