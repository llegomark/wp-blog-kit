<?php
/**
 * The settings (Settings → Ads). They hold IDs only, never code: each value is cut down to the
 * shape the service gives it, so nothing typed or pasted into the form can put a script on the site.
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
			'sanitize_callback' => 'tala_ads_sanitize_settings',
			'default'           => array(),
		)
	);
}

/**
 * The settings, with defaults. Each value is checked again on the way out, so a damaged or
 * hand-edited option can never print anything but an ID.
 *
 * @return array{adsense:string,consent:bool,gtm:string,innity_key:string,innity_zone:string,innity_channel:string}
 */
function tala_ads_settings() {
	$saved = get_option( 'tala_ads', array() );
	return tala_ads_sanitize_settings( is_array( $saved ) ? $saved : array() );
}

/**
 * Cleans the settings form (and the stored option).
 *
 * @param mixed $input Submitted values.
 * @return array{adsense:string,consent:bool,gtm:string,innity_key:string,innity_zone:string,innity_channel:string}
 */
function tala_ads_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	return array(
		'adsense'        => tala_ads_sanitize_adsense( $input['adsense'] ?? '' ),
		'consent'        => ! empty( $input['consent'] ),
		'gtm'            => tala_ads_sanitize_gtm( $input['gtm'] ?? '' ),
		'innity_key'     => tala_ads_sanitize_innity_key( $input['innity_key'] ?? '' ),
		'innity_zone'    => tala_ads_sanitize_innity_zone( $input['innity_zone'] ?? '' ),
		'innity_channel' => tala_ads_sanitize_innity_channel( $input['innity_channel'] ?? '' ),
	);
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
