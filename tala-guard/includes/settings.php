<?php
/**
 * The Turnstile keys (Settings → Discussion). The site key is public: it goes into every comment
 * form. The secret key never leaves the server: it is only sent to Cloudflare's Siteverify, it is
 * never printed back into the settings form, and wp-config.php can hold it instead of the database
 * (TALA_GUARD_SITE_KEY and TALA_GUARD_SECRET_KEY, which then win over the form).
 *
 * Both keys are kept only in the shape Cloudflare gives them, so nothing typed into those fields can
 * put anything else on the site.
 *
 * @package TalaGuard
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'tala_guard_register_settings' );

/**
 * Registers the option that holds the keys, on Settings → Discussion.
 */
function tala_guard_register_settings(): void {
	register_setting(
		'discussion',
		'tala_guard',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'tala_guard_save_settings',
			'default'           => array(),
			'show_in_rest'      => false,
		)
	);
}

/**
 * The keys in use: each from wp-config.php when it's defined there, otherwise from the settings.
 * Checked again on the way out, so a damaged or hand-edited option is treated as no key.
 *
 * @return array{site_key:string,secret_key:string}
 */
function tala_guard_keys() {
	$saved = get_option( 'tala_guard', array() );
	$saved = is_array( $saved ) ? $saved : array();
	return array(
		'site_key'   => tala_guard_sanitize_site_key( defined( 'TALA_GUARD_SITE_KEY' ) ? constant( 'TALA_GUARD_SITE_KEY' ) : ( $saved['site_key'] ?? '' ) ),
		'secret_key' => tala_guard_sanitize_secret_key( defined( 'TALA_GUARD_SECRET_KEY' ) ? constant( 'TALA_GUARD_SECRET_KEY' ) : ( $saved['secret_key'] ?? '' ) ),
	);
}

/**
 * Whether the check is on: both keys are there.
 *
 * @return bool
 */
function tala_guard_active() {
	$keys = tala_guard_keys();
	return '' !== $keys['site_key'] && '' !== $keys['secret_key'];
}

/**
 * Whether the keys are Cloudflare's test keys (developers.cloudflare.com/turnstile/troubleshooting/testing/),
 * which work on any address and answer for a fixed action and hostname: with them, every comment
 * passes or every comment fails, as the test key says.
 *
 * @return bool
 */
function tala_guard_test_keys() {
	$keys = tala_guard_keys();
	return 1 === preg_match( '/^[1-3]x0{16,}/', $keys['secret_key'] );
}

/**
 * What the settings form saves: the cleaned keys. A secret key field left empty, or filled with
 * something that isn't a key, keeps the saved secret (the form never shows it); an empty site key
 * turns the check off and forgets the secret too.
 *
 * @param mixed $input Submitted values.
 * @return array{site_key:string,secret_key:string}
 */
function tala_guard_save_settings( $input ) {
	$input  = is_array( $input ) ? $input : array();
	$saved  = get_option( 'tala_guard', array() );
	$saved  = is_array( $saved ) ? $saved : array();
	$site   = tala_guard_sanitize_site_key( $input['site_key'] ?? '' );
	$typed  = is_string( $input['secret_key'] ?? null ) ? trim( $input['secret_key'] ) : '';
	$secret = tala_guard_sanitize_secret_key( $typed );
	if ( '' === $secret ) {
		$secret = tala_guard_sanitize_secret_key( $saved['secret_key'] ?? '' );
	}

	if ( function_exists( 'add_settings_error' ) ) {
		if ( is_string( $input['site_key'] ?? null ) && '' !== trim( $input['site_key'] ) && '' === $site ) {
			tala_guard_settings_error( 'tala_guard_site_key', __( 'The Turnstile site key wasn\'t saved: copy it from the widget\'s page under Turnstile in Cloudflare\'s dashboard. It starts with 0x.', 'tala-guard' ) );
		}
		if ( '' !== $typed && tala_guard_sanitize_secret_key( $typed ) !== $typed ) {
			tala_guard_settings_error( 'tala_guard_secret_key', __( 'The Turnstile secret key wasn\'t changed: copy it from the widget\'s page under Turnstile in Cloudflare\'s dashboard. It starts with 0x.', 'tala-guard' ) );
		}
	}

	return array(
		'site_key'   => $site,
		'secret_key' => '' === $site ? '' : $secret,
	);
}

/**
 * Adds a settings error once (WordPress may run the sanitize callback twice on the first save).
 *
 * @param string $code    Error code.
 * @param string $message Message.
 */
function tala_guard_settings_error( $code, $message ): void {
	if ( ! in_array( $code, array_column( get_settings_errors( 'tala_guard' ), 'code' ), true ) ) {
		add_settings_error( 'tala_guard', $code, $message );
	}
}

/**
 * A Turnstile site key, entered as is or taken from Turnstile's widget code (data-sitekey="…").
 * '' when it isn't one.
 *
 * @param mixed $value Value entered.
 * @return string
 */
function tala_guard_sanitize_site_key( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	if ( preg_match( '/data-sitekey\s*=\s*["\']([^"\']*)["\']/', $value, $match ) ) {
		$value = $match[1];
	}
	return tala_guard_key_shape( $value );
}

/**
 * A Turnstile secret key, or ''.
 *
 * @param mixed $value Value entered.
 * @return string
 */
function tala_guard_sanitize_secret_key( $value ) {
	return is_string( $value ) ? tala_guard_key_shape( $value ) : '';
}

/**
 * The shape of Turnstile's keys: a digit and "x", then letters, digits, "-" and "_" (0x4AAAA… for a
 * real key, 1x0000… for a test key).
 *
 * @param string $value Value.
 * @return string The key, or ''.
 */
function tala_guard_key_shape( $value ) {
	$value = trim( $value );
	return preg_match( '/^[0-9]x[A-Za-z0-9_-]{16,80}$/D', $value ) ? $value : '';
}
