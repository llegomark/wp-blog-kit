<?php
/**
 * Google Programmable Search in place of WordPress's search: every WordPress search goes to the
 * results page, where Google's search box and results take the place of the page's Search block
 * (or follow its content when it has none).
 *
 * @package TalaAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * The results page's ID when Google search is set up (an engine ID and a published page), else 0.
 *
 * @return int
 */
function tala_ads_search_page() {
	$settings = tala_ads_settings();
	if ( '' === $settings['cse'] || ! $settings['search_page'] || 'publish' !== get_post_status( $settings['search_page'] ) ) {
		return 0;
	}
	return $settings['search_page'];
}

/**
 * The search terms of the results page's address (?q=…), as plain text.
 *
 * @return string
 */
function tala_ads_search_terms() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a search, only shown back to the reader.
	return isset( $_GET['q'] ) && is_string( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) : '';
}

add_action( 'template_redirect', 'tala_ads_search_redirect', 1 );

/**
 * Sends WordPress searches (?s=…, from any search form) to the results page, where Google answers
 * them. Feeds of searches are left alone.
 */
function tala_ads_search_redirect(): void {
	$page = tala_ads_search_page();
	if ( ! $page || ! is_search() || is_feed() ) {
		return;
	}
	$url   = (string) get_permalink( $page );
	$terms = trim( get_search_query( false ) );
	if ( '' !== $terms ) {
		$url = add_query_arg( 'q', rawurlencode( $terms ), $url );
	}
	if ( wp_safe_redirect( $url, 302, 'Tala Ads' ) ) {
		exit;
	}
}

add_action( 'wp_enqueue_scripts', 'tala_ads_search_script' );

/**
 * Google's search script, on the results page only, with the styles that give Google's box and
 * results the theme's look. WordPress prints the styles inline (from their file, under its size
 * limit for inline styles), so they cost no request.
 */
function tala_ads_search_script(): void {
	$page = tala_ads_search_page();
	if ( ! $page || ! is_page( $page ) ) {
		return;
	}
	wp_enqueue_style( 'tala-ads-google-search', plugins_url( 'assets/google-search.css', TALA_ADS_FILE ), array(), TALA_ADS_VERSION );
	wp_style_add_data( 'tala-ads-google-search', 'path', dirname( __DIR__ ) . '/assets/google-search.css' );
	wp_enqueue_script(
		'tala-ads-search-hint',
		plugins_url( 'assets/google-search.js', TALA_ADS_FILE ),
		array(),
		TALA_ADS_VERSION,
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
	wp_enqueue_script(
		'tala-ads-google-search',
		'https://cse.google.com/cse.js?cx=' . rawurlencode( tala_ads_settings()['cse'] ),
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the service's own address, exactly as it gives it.
		array(
			'strategy'  => 'async',
			'in_footer' => false,
		)
	);
}

/**
 * Google's search box and results, which read the search from ?q=. Google's box has no hint of its
 * own; assets/google-search.js gives it this one once Google has drawn it.
 *
 * @param string $hint What the empty box says: the Search block's placeholder, or a default.
 * @return string
 */
function tala_ads_search_markup( $hint = '' ) {
	do_action( 'tala_ads_search_placed' );
	if ( '' === trim( $hint ) ) {
		$hint = __( 'Type here to search', 'tala-ads' );
	}
	return '<div class="tala-ads-google-search" data-hint="' . esc_attr( $hint ) . '"><div class="gcse-search" data-queryParameterName="q"></div></div>';
}

/**
 * Whether the content being shown is the results page's own, in the main loop, with Google's
 * results not placed yet.
 *
 * @return bool
 */
function tala_ads_search_place_here() {
	$page = tala_ads_search_page();
	return $page && is_page( $page ) && in_the_loop() && is_main_query() && get_the_ID() === $page
		&& doing_filter( 'the_content' ) && ! did_action( 'tala_ads_search_placed' );
}

add_filter( 'render_block_core/search', 'tala_ads_search_block', 10, 2 );

/**
 * The results page's own Search block becomes Google's search box and results, with the block's
 * placeholder as the box's hint. Other Search blocks on the results page (the theme's, at the top
 * or in the footer) are left out, so the page has one search box, Google's. Search blocks on other
 * pages stay WordPress's: their searches come here anyway.
 *
 * @param string               $html  The Search block's HTML.
 * @param array<string, mixed> $block The parsed block.
 * @return string
 */
function tala_ads_search_block( $html, $block = array() ) {
	if ( tala_ads_search_place_here() ) {
		$hint = $block['attrs']['placeholder'] ?? '';
		return tala_ads_search_markup( is_string( $hint ) ? $hint : '' );
	}
	$page = tala_ads_search_page();
	return $page && is_page( $page ) ? '' : $html;
}

add_filter( 'the_content', 'tala_ads_search_content', 20 );

/**
 * A results page without a Search block gets Google's search box and results after its content.
 *
 * @param string $content Page content.
 * @return string
 */
function tala_ads_search_content( $content ) {
	return tala_ads_search_place_here() ? $content . tala_ads_search_markup() : $content;
}

add_filter( 'wp_robots', 'tala_ads_search_robots' );

/**
 * Results for a search stay out of search engines, as Google asks for internal search results
 * (links on them still count); the page itself, without a search, may be indexed.
 *
 * @param array<string, bool|string> $robots Robots directives.
 * @return array<string, bool|string>
 */
function tala_ads_search_robots( $robots ) {
	$page = tala_ads_search_page();
	if ( $page && is_page( $page ) && '' !== tala_ads_search_terms() ) {
		$robots['noindex'] = true;
		if ( empty( $robots['nofollow'] ) ) {
			$robots['follow'] = true;
		}
	}
	return $robots;
}
