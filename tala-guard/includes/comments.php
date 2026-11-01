<?php
/**
 * Turnstile in the comment form, and the check on the server before a comment is saved.
 *
 * Following Cloudflare's guidance (developers.cloudflare.com/turnstile/get-started/): the widget
 * gives the browser a token, and the server redeems it once at Siteverify before the comment goes
 * any further, accepting it only when Cloudflare says it succeeded, for this form's action, on this
 * site's own hostname. Anything else, including Cloudflare not answering, turns the comment away:
 * the check fails closed. The widget is plain markup with the public site key, so pages with
 * comment forms stay cacheable.
 *
 * Only comments sent through the public comment form (wp-comments-post.php) are checked. Replies
 * written in wp-admin and comments posted through the REST API by logged-in users don't go through
 * that form; WordPress lets only logged-in users comment through the REST API.
 *
 * @package TalaGuard
 */

defined( 'ABSPATH' ) || exit;

/** The action the widget declares and Siteverify must give back (letters, digits, "-", "_"; 32 at most). */
const TALA_GUARD_ACTION = 'comment';

/**
 * Cloudflare's Siteverify endpoint: an API the server calls, not content a page loads (Plugin Check's
 * offloading rule matches every cloudflare.com address).
 */
const TALA_GUARD_SITEVERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent

add_filter( 'comment_form_submit_field', 'tala_guard_comment_form_widget' );

/**
 * Puts the Turnstile widget right before the comment form's submit button, with a line for what the
 * check is doing, and loads the script that renders it.
 *
 * @param string $submit_field The submit button's markup.
 * @return string
 */
function tala_guard_comment_form_widget( $submit_field ) {
	if ( ! tala_guard_active() ) {
		return $submit_field;
	}
	wp_enqueue_script(
		'tala-guard',
		plugins_url( 'assets/turnstile.js', TALA_GUARD_FILE ),
		array(),
		TALA_GUARD_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	$keys = tala_guard_keys();
	// The widget's height (65px, Cloudflare's size for "flexible") is kept free, so the form doesn't
	// move when it appears.
	$widget = sprintf(
		'<div class="tala-guard-turnstile" data-sitekey="%1$s" data-action="%2$s" style="min-height:65px"></div>'
		. '<div class="tala-guard-status" role="status" data-checking="%3$s" data-failed="%4$s"></div>'
		. '<noscript><p class="tala-guard-noscript">%5$s</p></noscript>',
		esc_attr( $keys['site_key'] ),
		esc_attr( TALA_GUARD_ACTION ),
		esc_attr__( 'Checking that a person is writing. Your comment will be sent in a moment.', 'tala-guard' ),
		esc_attr__( 'The check that keeps out spam couldn\'t finish. Reload the page and send your comment again.', 'tala-guard' ),
		esc_html__( 'Turn on JavaScript to post a comment: a quick check from Cloudflare Turnstile confirms that a person is writing.', 'tala-guard' )
	);
	return $widget . $submit_field;
}

add_action( 'pre_comment_on_post', 'tala_guard_mark_form_comment' );

/**
 * Notes that the comment being handled came through the public comment form: WordPress fires
 * pre_comment_on_post only in wp_handle_comment_submission(), which wp-comments-post.php calls.
 */
function tala_guard_mark_form_comment(): void {
	tala_guard_form_comment( true );
}

/**
 * Whether the comment being saved came through the public comment form.
 *
 * @param bool|null $set True or false to set it; null to read it.
 * @return bool
 */
function tala_guard_form_comment( $set = null ) {
	static $form = false;
	if ( null !== $set ) {
		$form = $set;
	}
	return $form;
}

add_filter( 'pre_comment_approved', 'tala_guard_check_comment', 99 );

/**
 * Checks a comment from the public form with Cloudflare before WordPress saves it. WordPress runs
 * this after its own checks (required fields, duplicates, flooding), so a comment those turn away
 * never spends its token. On failure WordPress shows the message with a link back to the form, with
 * the status code the error carries.
 *
 * @param int|string|WP_Error $approved What WordPress would do with the comment.
 * @return int|string|WP_Error
 */
function tala_guard_check_comment( $approved ) {
	if ( is_wp_error( $approved ) || ! tala_guard_form_comment() ) {
		return $approved;
	}
	tala_guard_form_comment( false );
	if ( ! tala_guard_active() ) {
		return $approved;
	}
	// Visitors' comment forms carry no nonce (WordPress's own form has none, and pages with comment
	// forms are cached for everyone); the Turnstile token is what this check verifies. It goes to
	// Cloudflare as it came, and only when it's a string of at most 2048 characters.
	$token  = isset( $_POST['cf-turnstile-response'] ) ? wp_unslash( $_POST['cf-turnstile-response'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$result = tala_guard_verify( is_string( $token ) ? $token : '', tala_guard_visitor_ip() );
	return true === $result ? $approved : $result;
}

/**
 * Redeems a Turnstile token at Siteverify.
 *
 * Cloudflare's checks: success, the expected action and one of the site's hostnames (with
 * Cloudflare's test keys, whose answers name a fixed action and hostname, only success). A
 * network error or Cloudflare's own "internal-error" is retried once with the same idempotency
 * key, so the retry can't count as a second use of the token.
 *
 * @param string $token Token from the form.
 * @param string $ip    Visitor's IP address, or ''.
 * @return true|WP_Error
 */
function tala_guard_verify( $token, $ip ) {
	if ( '' === $token || strlen( $token ) > 2048 ) {
		return tala_guard_error( 'failed' );
	}
	$keys = tala_guard_keys();
	$body = array(
		'secret'          => $keys['secret_key'],
		'response'        => $token,
		'idempotency_key' => wp_generate_uuid4(),
	);
	if ( '' !== $ip ) {
		$body['remoteip'] = $ip;
	}

	$answer = null;
	for ( $attempt = 0; $attempt < 2 && null === $answer; $attempt++ ) {
		$response = wp_safe_remote_post(
			TALA_GUARD_SITEVERIFY,
			array(
				'timeout' => 8,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			continue;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $data ) && ! in_array( 'internal-error', (array) ( $data['error-codes'] ?? array() ), true ) ) {
			$answer = $data;
		}
	}
	if ( null === $answer ) {
		return tala_guard_error( 'unreachable' );
	}

	$passed = true === ( $answer['success'] ?? null );
	if ( $passed && ! tala_guard_test_keys() ) {
		$passed = TALA_GUARD_ACTION === ( $answer['action'] ?? null )
			&& in_array( $answer['hostname'] ?? null, tala_guard_hostnames(), true );
	}
	if ( ! $passed ) {
		/**
		 * Fires when Siteverify turns a comment away, with its answer (no secret in it), for logging.
		 *
		 * @param array<string, mixed> $answer Siteverify's answer.
		 */
		do_action( 'tala_guard_turned_away', $answer );
		return tala_guard_error( 'failed' );
	}
	return true;
}

/**
 * The hostnames a token may come from: the site's own (home and WordPress addresses).
 *
 * @return string[]
 */
function tala_guard_hostnames() {
	$hosts = array_values( array_unique( array_filter( array( wp_parse_url( home_url(), PHP_URL_HOST ), wp_parse_url( site_url(), PHP_URL_HOST ) ) ) ) );
	/**
	 * Filters the hostnames a Turnstile token may come from.
	 *
	 * @param string[] $hosts Hostnames.
	 */
	return array_values( array_filter( apply_filters( 'tala_guard_hostnames', $hosts ), 'is_string' ) );
}

/**
 * The visitor's IP address as WordPress records it for comments, or '' when it isn't one.
 *
 * @return string
 */
function tala_guard_visitor_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return false === filter_var( $ip, FILTER_VALIDATE_IP ) ? '' : $ip;
}

/**
 * The error a turned-away comment gets. It names no cause beyond what the visitor can act on, as
 * Cloudflare advises.
 *
 * @param string $reason "failed" or "unreachable".
 * @return WP_Error
 */
function tala_guard_error( $reason ) {
	if ( 'unreachable' === $reason ) {
		return new WP_Error(
			'tala_guard_unreachable',
			__( 'Your comment wasn\'t sent: the check that keeps out spam couldn\'t be done just now. Go back and send it again in a minute.', 'tala-guard' ),
			503
		);
	}
	return new WP_Error(
		'tala_guard_failed',
		__( 'Your comment wasn\'t sent: it didn\'t pass the check that keeps out spam. Go back, wait for the check above the button to finish, and send it again.', 'tala-guard' ),
		403
	);
}
