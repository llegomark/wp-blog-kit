<?php
/**
 * What visitors get: Google Tag Manager, Google's consent message, the AdSense Auto ads code, and
 * Innity's box and Universal tags. Every script goes through WordPress's script loader, once per
 * page, so it works with any theme and nothing loads twice.
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
		// Google places the Auto ads and decides what each place shows; nothing here hides any of them.
		// Ad frames are light pages. On a page in dark mode the browser paints a frame of a light page
		// white until its ad arrives; declared light, the frame stays see-through, as on a light page,
		// and an ad paints its own background.
		wp_register_style( 'tala-ads', false, array(), TALA_ADS_VERSION );
		wp_enqueue_style( 'tala-ads' );
		wp_add_inline_style( 'tala-ads', 'ins.adsbygoogle iframe{color-scheme:light}' );
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

// Right after wp_enqueue_scripts and the title (both at 1), before the page's styles (8) and the
// other head scripts (9).
add_action( 'wp_head', 'tala_ads_print_head_scripts', 2 );

/**
 * Google's tags at the top of <head>, before the page's styles: a theme that inlines its CSS would
 * otherwise put many kilobytes of it before them, and the browser would start loading AdSense later.
 * They are printed in the order they were queued and marked done, so WordPress doesn't print them
 * again with the other head scripts.
 */
function tala_ads_print_head_scripts(): void {
	$handles = array_values(
		array_filter(
			array( 'tala-ads-gtm', 'tala-ads-consent', 'tala-ads-consent-signal', 'tala-ads-error-protection', 'tala-ads-adsense' ),
			static fn( string $handle ): bool => wp_script_is( $handle )
		)
	);
	if ( array() !== $handles ) {
		wp_scripts()->do_items( $handles );
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
 * Post types whose pages get the Innity box and the place for Innity's in-read ad.
 *
 * @return string[]
 */
function tala_ads_post_types() {
	return (array) apply_filters( 'tala_ads_post_types', array( 'post', 'page' ) );
}

/**
 * Innity's loader, once per page. The zones go into its queue (window.innity_adZoneAsync.q), which
 * it works through whether it arrives before or after them.
 */
function tala_ads_innity_loader(): void {
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
}

/**
 * The settings Innity's zone tags pass along: the site's channels, when there are any. Innity's own
 * tags also pass a click-tracking prefix, "%%CLICK_URL_UNESC%%", but only in their Google Ad Manager
 * version, where Ad Manager fills it in; a tag placed on the page directly has none.
 *
 * @param array<string, string> $args Settings of this zone.
 * @return array<string, string>|stdClass
 */
function tala_ads_innity_args( array $args ) {
	$channel = tala_ads_settings()['innity_channel'];
	if ( '' !== $channel ) {
		$args['channel'] = $channel;
	}
	return array() === $args ? new stdClass() : $args;
}

/**
 * Where Innity may load: pages with ads, but not searches or Google's results page, whose addresses
 * carry what readers typed (Innity's tags send the page's full address to its servers).
 *
 * @return bool
 */
function tala_ads_innity_allowed() {
	$results = tala_ads_search_page();
	return tala_ads_show_ads() && ! is_search() && ! ( $results && is_page( $results ) );
}

/**
 * The gate Innity's zones go through, window.talaAdsInnity( show, later ): show() runs, any
 * error it throws caught; with later, only once the page has loaded and the browser is idle (or after 2 seconds).
 * With Google's messages on, it first waits for Google's consent data, and where the GDPR applies
 * doesn't run: Innity's tags read no consent signal of their own, and set an identifier cookie
 * (iUUID) and share it with partners.
 */
function tala_ads_innity_gate(): void {
	if ( wp_script_is( 'tala-ads-innity-gate', 'registered' ) ) {
		return;
	}
	$consent = tala_ads_settings()['consent']
		? 'window.googlefc=window.googlefc||{};googlefc.callbackQueue=googlefc.callbackQueue||[];googlefc.callbackQueue.push({CONSENT_DATA_READY:function(){if("function"!==typeof window.__tcfapi){run();return}__tcfapi("getTCData",2,function(d,ok){if(!(ok&&d&&true===d.gdprApplies))run()})}});'
		: 'run();';
	wp_register_script( 'tala-ads-innity-gate', false, array(), TALA_ADS_VERSION, array( 'in_footer' => true ) );
	wp_add_inline_script(
		'tala-ads-innity-gate',
		'window.talaAdsInnity=function(show,later){function go(){try{show()}catch(e){}}function run(){if(!later){go();return}function idle(){(window.requestIdleCallback||function(f){setTimeout(f,1)})(go,{timeout:2000})}if("complete"===document.readyState){idle()}else{window.addEventListener("load",idle)}}' . $consent . '};'
	);
}

add_action( 'wp_enqueue_scripts', 'tala_ads_innity_universal' );

/**
 * Innity's Universal tags on every page with ads: one zone for screens 768 px wide or more and one
 * for narrower ones, each optional. Innity's server picks the format (an in-read ad in the article,
 * an in-image ad, or, on phones, full-screen ones). The page cache gives every device the same
 * HTML, so the browser picks the zone for its screen; a screen without a zone loads none. It loads
 * once the page has (its in-read ad only draws when scrolled to), so it doesn't compete with the page.
 */
function tala_ads_innity_universal(): void {
	$settings = tala_ads_settings();
	if ( '' === $settings['innity_key'] || ( '' === $settings['innity_desktop'] && '' === $settings['innity_mobile'] )
		|| ! tala_ads_innity_allowed() ) {
		return;
	}
	tala_ads_innity_gate();
	wp_register_script( 'tala-ads-innity-universal', false, array( 'tala-ads-innity-gate' ), TALA_ADS_VERSION, array( 'in_footer' => true ) );
	wp_enqueue_script( 'tala-ads-innity-universal' );
	wp_add_inline_script(
		'tala-ads-innity-universal',
		sprintf(
			'(function(zone){if(zone){talaAdsInnity(function(){window.innity_adZoneAsync=window.innity_adZoneAsync||{};innity_adZoneAsync.q=innity_adZoneAsync.q||[];innity_adZoneAsync.q.push(function(){try{innity_adZoneAsync.display(%s,zone,%s)}catch(e){}})},true)}})(window.matchMedia("(min-width: 768px)").matches?%s:%s);',
			wp_json_encode( $settings['innity_key'] ),
			wp_json_encode( tala_ads_innity_args( array() ) ),
			wp_json_encode( $settings['innity_desktop'] ),
			wp_json_encode( $settings['innity_mobile'] )
		)
	);
	tala_ads_innity_loader();
}

/**
 * Whether the content being filtered is the post or page the request shows, in the main loop, with
 * Innity set up and ads allowed.
 *
 * @return bool
 */
function tala_ads_innity_here() {
	return '' !== tala_ads_settings()['innity_key'] && is_singular( tala_ads_post_types() ) && in_the_loop()
		&& is_main_query() && get_the_ID() === get_queried_object_id() && ! post_password_required() && tala_ads_innity_allowed();
}

/**
 * Whether Innity's in-image ads are kept off the page's images: when a Universal tag is set (the
 * only zone that serves them), unless the "tala_ads_innity_in_image" filter returns true. Innity
 * lays them over the lower part of a large image in the article, which on a scanned memo or an
 * infographic covers its text.
 *
 * @return bool
 */
function tala_ads_innity_no_in_image() {
	$settings = tala_ads_settings();
	return ! is_admin() && '' !== $settings['innity_key'] && ( '' !== $settings['innity_desktop'] || '' !== $settings['innity_mobile'] )
		&& ! apply_filters( 'tala_ads_innity_in_image', false );
}

add_filter( 'wp_content_img_tag', 'tala_ads_innity_content_image' );

/**
 * An image in a post or page's content, marked data-innity-allow-in-image="false", which both
 * Innity's image finder and its in-image ads skip.
 *
 * @param mixed $image The image's tag (a string, unless another filter broke it).
 * @return mixed
 */
function tala_ads_innity_content_image( $image ) {
	if ( ! is_string( $image ) || ! tala_ads_innity_no_in_image() ) {
		return $image;
	}
	$tag = new WP_HTML_Tag_Processor( $image );
	if ( ! $tag->next_tag( array( 'tag_name' => 'img' ) ) ) {
		return $image;
	}
	$tag->set_attribute( 'data-innity-allow-in-image', 'false' );
	return $tag->get_updated_html();
}

add_filter( 'wp_get_attachment_image_attributes', 'tala_ads_innity_attachment_image' );

/**
 * The same mark on images WordPress prints from the media library outside the content: featured
 * images, the site logo.
 *
 * @param mixed $attributes The image's attributes (an array, unless another filter broke it).
 * @return mixed
 */
function tala_ads_innity_attachment_image( $attributes ) {
	if ( is_array( $attributes ) && tala_ads_innity_no_in_image() ) {
		$attributes['data-innity-allow-in-image'] = 'false';
	}
	return $attributes;
}

add_filter( 'the_content', 'tala_ads_innity_box', 20 );

/**
 * The Innity box (300 × 250) in the post or page the request shows, once: after its sixth
 * paragraph, where readers still are, or after its text when it has fewer. Its 250 px are kept free
 * while the ad loads, so the page doesn't jump. The box is never hidden.
 *
 * @param string $content Post content.
 * @return string
 */
function tala_ads_innity_box( $content ) {
	$zone = tala_ads_settings()['innity_zone'];
	if ( '' === $zone || wp_script_is( 'tala-ads-innity-zone' ) || ! tala_ads_innity_here() ) {
		return $content;
	}
	$target = 'div-ad-innity-' . $zone . '/0';
	$args   = array(
		'target' => $target,
		'width'  => '300',
		'height' => '250',
	);
	tala_ads_innity_gate();
	wp_register_script( 'tala-ads-innity-zone', false, array( 'tala-ads-innity-gate' ), TALA_ADS_VERSION, array( 'in_footer' => true ) );
	wp_enqueue_script( 'tala-ads-innity-zone' );
	wp_add_inline_script(
		'tala-ads-innity-zone',
		sprintf(
			'talaAdsInnity(function(){window.innity_adZoneAsync=window.innity_adZoneAsync||{};innity_adZoneAsync.q=innity_adZoneAsync.q||[];innity_adZoneAsync.q.push(function(){try{innity_adZoneAsync.display(%1$s,%2$s,%3$s)}catch(e){}})},false);',
			wp_json_encode( tala_ads_settings()['innity_key'] ),
			wp_json_encode( $zone ),
			wp_json_encode( tala_ads_innity_args( $args ) )
		)
	);
	tala_ads_innity_loader();
	$box = sprintf(
		'<div class="tala-ads-innity" style="min-height:250px;margin:2em auto;text-align:center"><div id="%s" style="display:inline-block"></div></div>',
		esc_attr( $target )
	);
	return tala_ads_after_paragraphs( $content, (int) apply_filters( 'tala_ads_innity_paragraphs', 6 ), $box ) ?? $content . $box;
}

add_filter( 'the_content', 'tala_ads_innity_in_read', 21 );

/**
 * With a Universal tag set, the place for Innity's in-read ad in the post or page the request shows,
 * once: after its fourth paragraph, or after its text when it has fewer. Without it, Innity looks
 * for the article itself and puts its ad after the second paragraph. Innity's loader looks for the
 * place by its data-innity-slot attribute or its id; its in-read ads look only for a span with the
 * id or class innity-in-post.
 *
 * @param string $content Post content.
 * @return string
 */
function tala_ads_innity_in_read( $content ) {
	$settings = tala_ads_settings();
	if ( ( '' === $settings['innity_desktop'] && '' === $settings['innity_mobile'] )
		|| wp_script_is( 'tala-ads-innity-in-read' ) || ! tala_ads_innity_here() ) {
		return $content;
	}
	// A script with no code, which prints nothing: it only marks the place as taken for this page.
	wp_register_script( 'tala-ads-innity-in-read', false, array(), TALA_ADS_VERSION, array( 'in_footer' => true ) );
	wp_enqueue_script( 'tala-ads-innity-in-read' );
	$slot = '<span id="innity-in-post" class="innity-in-post" data-innity-slot="in-read" style="display:block"></span>';
	return tala_ads_after_paragraphs( $content, (int) apply_filters( 'tala_ads_innity_in_read_paragraphs', 4 ), $slot ) ?? $content . $slot;
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
