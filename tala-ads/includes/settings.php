<?php
/**
 * The settings (Settings → Ads). They hold IDs (and the results page for Google search), cut down to
 * the shape each service gives them, so
 * nothing typed or pasted into those fields can put a script on the site. The one piece of code is
 * Google's error protection tag for the ad blocking recovery message, which Google generates for
 * each site: it is kept only in the shape Google gives it, for the site's own publisher ID, naming
 * only Google's addresses, and only people WordPress lets add scripts can change it.
 *
 * @package TalaAds
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'tala_ads_register_settings' );

/**
 * Registers the option that holds every setting.
 */
function tala_ads_register_settings(): void {
	register_setting(
		'tala_ads',
		'tala_ads',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'tala_ads_save_settings',
			'default'           => array(),
		)
	);
}

/**
 * The settings, with defaults. Each value is checked again on the way out, so a damaged or
 * hand-edited option can never print anything the form wouldn't have kept.
 *
 * @return array{adsense:string,consent:bool,error_protection:string,gtm:string,innity_key:string,innity_zone:string,innity_channel:string,cse:string,search_page:int}
 */
function tala_ads_settings() {
	$saved = get_option( 'tala_ads', array() );
	return tala_ads_sanitize_settings( is_array( $saved ) ? $saved : array() );
}

/**
 * What is saved, from the form or update_option(): the cleaned settings, except that someone
 * WordPress doesn't let add scripts (the unfiltered_html capability) can't change the error
 * protection code; the stored one stays, if it still fits the publisher ID.
 *
 * @param mixed $input Submitted values.
 * @return array{adsense:string,consent:bool,error_protection:string,gtm:string,innity_key:string,innity_zone:string,innity_channel:string,cse:string,search_page:int}
 */
function tala_ads_save_settings( $input ) {
	$clean = tala_ads_sanitize_settings( $input );
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		$saved                     = get_option( 'tala_ads', array() );
		$clean['error_protection'] = tala_ads_sanitize_error_protection( is_array( $saved ) ? ( $saved['error_protection'] ?? '' ) : '', $clean['adsense'] );
	} elseif ( is_array( $input ) && is_string( $input['error_protection'] ?? null ) && '' !== trim( $input['error_protection'] )
		&& '' === $clean['error_protection'] && function_exists( 'add_settings_error' )
		&& ! in_array( 'tala_ads_error_protection', array_column( get_settings_errors( 'tala_ads' ), 'code' ), true ) ) {
		add_settings_error( 'tala_ads', 'tala_ads_error_protection', __( 'The error protection code wasn\'t saved: paste the whole code AdSense gives for this site\'s publisher ID, unchanged, with the publisher ID filled in above.', 'tala-ads' ) );
	}
	return $clean;
}

/**
 * Cleans the settings: each one to its own shape.
 *
 * @param mixed $input Submitted or stored values.
 * @return array{adsense:string,consent:bool,error_protection:string,gtm:string,innity_key:string,innity_zone:string,innity_channel:string,cse:string,search_page:int}
 */
function tala_ads_sanitize_settings( $input ) {
	$input   = is_array( $input ) ? $input : array();
	$adsense = tala_ads_sanitize_adsense( $input['adsense'] ?? '' );
	return array(
		'adsense'          => $adsense,
		'consent'          => ! empty( $input['consent'] ),
		'error_protection' => tala_ads_sanitize_error_protection( $input['error_protection'] ?? '', $adsense ),
		'gtm'              => tala_ads_sanitize_gtm( $input['gtm'] ?? '' ),
		'innity_key'       => tala_ads_sanitize_innity_key( $input['innity_key'] ?? '' ),
		'innity_zone'      => tala_ads_sanitize_innity_zone( $input['innity_zone'] ?? '' ),
		'innity_channel'   => tala_ads_sanitize_innity_channel( $input['innity_channel'] ?? '' ),
		'cse'              => tala_ads_sanitize_cse( $input['cse'] ?? '' ),
		'search_page'      => tala_ads_sanitize_search_page( $input['search_page'] ?? 0 ),
	);
}

/**
 * A Google Programmable Search Engine ID (the "cx"), entered as is or taken from Google's code
 * (cse.js?cx=…). '' when there is none.
 *
 * @param mixed $value Value entered.
 * @return string
 */
function tala_ads_sanitize_cse( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	if ( preg_match( '/^\s*([A-Za-z0-9:_-]{6,64})\s*$/', $value, $match ) ) {
		return $match[1];
	}
	return preg_match( '/[?&]cx=([A-Za-z0-9:_-]{6,64})/', $value, $match ) ? $match[1] : '';
}

/**
 * The results page for Google search: a page's ID, or 0.
 *
 * @param mixed $value Value entered.
 * @return int
 */
function tala_ads_sanitize_search_page( $value ) {
	$id = is_numeric( $value ) ? absint( $value ) : 0;
	return $id && 'page' === get_post_type( $id ) ? $id : 0;
}

/**
 * Google's error protection tag (AdSense → Privacy & messaging → Ad blocking recovery → Tagging per
 * site), as the JavaScript inside its <script> element, or '' when the value isn't that tag.
 *
 * It is kept only in the shape Google gives it: one self-contained function, then a single call
 * that hands it the site's configuration, whose first entry is the publisher ID and whose addresses
 * are all on Google's message and static servers. Nothing can close the script element or start
 * another one.
 *
 * @param mixed  $value   Value entered (with or without the <script> element around it).
 * @param string $adsense The site's publisher ID ("ca-pub-…"); without one, nothing is kept.
 * @return string
 */
function tala_ads_sanitize_error_protection( $value, $adsense ) {
	if ( ! is_string( $value ) || '' === $adsense || strlen( $value ) > 65536 ) {
		return '';
	}
	// Browsers send a textarea's line breaks as CR LF: keep them as Google writes them.
	$code = trim( str_replace( array( "\r\n", "\r" ), "\n", $value ) );
	if ( preg_match( '#^<script>(.*)</script>$#s', $code, $match ) ) {
		$code = trim( $match[1] );
	}
	if ( preg_match( '#</?script|<!--#i', $code )
		|| ! preg_match( '/^\(function\(\)\{.*\}\)\.call\(this\);\s*window\.(__[A-Za-z0-9]{4,40}__)\("([A-Za-z0-9+\/]+={0,2})"\);?$/s', $code, $match )
		|| ! str_contains( $code, '("' . $match[1] . '",' ) ) {
		return '';
	}
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- reads Google's configuration to check what it names.
	$config = json_decode( (string) base64_decode( $match[2], true ), true );
	if ( ! is_array( $config ) || substr( $adsense, 3 ) !== ( $config[0] ?? null ) ) {
		return '';
	}
	$urls = array();
	array_walk_recursive(
		$config,
		static function ( $item ) use ( &$urls ): void {
			if ( is_string( $item ) && preg_match( '#^[a-z][a-z0-9+.-]*:#i', $item ) ) {
				$urls[] = $item;
			}
		}
	);
	foreach ( $urls as $url ) {
		// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- the servers Google's code may name, checked here; nothing is loaded from them by this plugin.
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! in_array( wp_parse_url( $url, PHP_URL_HOST ), array( 'fundingchoicesmessages.google.com', 'www.gstatic.com' ), true ) ) {
			return '';
		}
	}
	return $code;
}

/**
 * An AdSense publisher ID as "ca-pub-" and its digits, taken from the ID or from any code AdSense
 * gives (the Auto ads code, an ad unit, the ads.txt line). '' when there is none.
 *
 * @param mixed $value Value entered.
 * @return string
 */
function tala_ads_sanitize_adsense( $value ) {
	return is_string( $value ) && preg_match( '/\bpub-(\d{10,20})\b/', $value, $match ) ? 'ca-pub-' . $match[1] : '';
}

/**
 * A Google Tag Manager container ID ("GTM-" and letters or digits), taken from the ID or from the
 * code Tag Manager gives. '' when there is none.
 *
 * @param mixed $value Value entered.
 * @return string
 */
function tala_ads_sanitize_gtm( $value ) {
	return is_string( $value ) && preg_match( '/\bGTM-([A-Z0-9]{4,12})\b/i', $value, $match ) ? 'GTM-' . strtoupper( $match[1] ) : '';
}

/**
 * Innity's publisher key: the 32 hexadecimal characters that come first in its zone tag's
 * innity_adZoneAsync.display( "key", "zone", … ). '' when there is none.
 *
 * @param mixed $value Value entered.
 * @return string
 */
function tala_ads_sanitize_innity_key( $value ) {
	return is_string( $value ) && preg_match( '/\b([a-f0-9]{32})\b/i', $value, $match ) ? strtolower( $match[1] ) : '';
}

/**
 * An Innity zone ID (digits), entered as is or taken from the zone tag. '' when there is none.
 *
 * @param mixed $value Value entered.
 * @return string
 */
function tala_ads_sanitize_innity_zone( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	if ( preg_match( '/^\s*(\d{1,12})\s*$/', $value, $match ) ) {
		return $match[1];
	}
	return preg_match( '/display\(\s*["\'][a-f0-9]{32}["\']\s*,\s*["\'](\d{1,12})["\']/i', $value, $match ) ? $match[1] : '';
}

/**
 * Innity's channels, the topics its zone tag lists ("TECHNOLOGY,LIFESTYLE"): capital letters, digits
 * and underscores, separated by commas. Anything else is dropped.
 *
 * @param mixed $value Value entered.
 * @return string
 */
function tala_ads_sanitize_innity_channel( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$channels = array_filter(
		array_map( static fn( string $channel ): string => strtoupper( trim( $channel ) ), explode( ',', $value ) ),
		static fn( string $channel ): bool => (bool) preg_match( '/^[A-Z0-9_]{1,40}$/', $channel )
	);
	return implode( ',', array_slice( array_values( array_unique( $channels ) ), 0, 10 ) );
}
