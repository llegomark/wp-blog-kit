<?php
/**
 * The Turnstile keys on Settings → Discussion, and the text Tala Guard suggests for the privacy
 * policy.
 *
 * @package TalaGuard
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', 'tala_guard_settings_fields' );

/**
 * The section and its fields on Settings → Discussion (the option is registered on init).
 */
function tala_guard_settings_fields(): void {
	add_settings_section(
		'tala_guard',
		__( 'Spam check for comments', 'tala-guard' ),
		'tala_guard_section',
		'discussion',
		array(
			'before_section' => '<div id="tala-guard">',
			'after_section'  => '</div>',
		)
	);
	add_settings_field( 'tala_guard_site_key', __( 'Turnstile site key', 'tala-guard' ), 'tala_guard_field_site_key', 'discussion', 'tala_guard', array( 'label_for' => 'tala-guard-site-key' ) );
	add_settings_field( 'tala_guard_secret_key', __( 'Turnstile secret key', 'tala-guard' ), 'tala_guard_field_secret_key', 'discussion', 'tala_guard', array( 'label_for' => 'tala-guard-secret-key' ) );
}

/**
 * What the check does, and whether it's on.
 */
function tala_guard_section(): void {
	echo '<p>' . esc_html__( 'Cloudflare Turnstile checks that a person, not a bot, is sending each comment from the comment form, usually without asking them to do anything. Cloudflare confirms each check before WordPress saves the comment, so bots\' comments never reach the moderation queue.', 'tala-guard' ) . '</p>';
	echo '<p>' . wp_kses(
		__( 'In Cloudflare\'s dashboard, under <strong>Turnstile</strong>, add a widget for this site\'s domain (the <strong>Managed</strong> type is recommended), then enter its two keys here. Leave the site key empty to turn the check off.', 'tala-guard' ),
		array( 'strong' => array() )
	) . '</p>';
	if ( ! tala_guard_active() ) {
		echo '<p><strong>' . esc_html__( 'The check is off.', 'tala-guard' ) . '</strong></p>';
	} elseif ( tala_guard_test_keys() ) {
		echo '<p><strong>' . esc_html__( 'These are Cloudflare\'s test keys: they don\'t tell people from bots. Use them only on a test site.', 'tala-guard' ) . '</strong></p>';
	} else {
		echo '<p><strong>' . esc_html__( 'The check is on for the comment form.', 'tala-guard' ) . '</strong></p>';
	}
}

/**
 * The site key field.
 */
function tala_guard_field_site_key(): void {
	if ( defined( 'TALA_GUARD_SITE_KEY' ) ) {
		printf( '<input type="text" id="tala-guard-site-key" class="regular-text code" value="%s" disabled>', esc_attr( tala_guard_keys()['site_key'] ) );
		echo '<p class="description">' . esc_html__( 'Set in wp-config.php (TALA_GUARD_SITE_KEY).', 'tala-guard' ) . '</p>';
		return;
	}
	printf(
		'<input type="text" id="tala-guard-site-key" name="tala_guard[site_key]" class="regular-text code" value="%s" placeholder="0x4AAAAAAA…" autocomplete="off" spellcheck="false">',
		esc_attr( tala_guard_keys()['site_key'] )
	);
	echo '<p class="description">' . esc_html__( 'Public: it goes into the comment form.', 'tala-guard' ) . '</p>';
}

/**
 * The secret key field. It is never filled in: the saved secret stays on the server.
 */
function tala_guard_field_secret_key(): void {
	if ( defined( 'TALA_GUARD_SECRET_KEY' ) ) {
		echo '<input type="password" id="tala-guard-secret-key" class="regular-text code" value="" disabled>';
		echo '<p class="description">' . esc_html__( 'Set in wp-config.php (TALA_GUARD_SECRET_KEY).', 'tala-guard' ) . '</p>';
		return;
	}
	$saved = '' !== tala_guard_keys()['secret_key'];
	printf(
		'<input type="password" id="tala-guard-secret-key" name="tala_guard[secret_key]" class="regular-text code" value="" placeholder="%s" autocomplete="new-password" spellcheck="false">',
		esc_attr( $saved ? __( 'Saved (not shown)', 'tala-guard' ) : '' )
	);
	echo '<p class="description">' . esc_html(
		$saved
			? __( 'A secret key is saved. It isn\'t shown; enter a new one only to replace it.', 'tala-guard' )
			: __( 'Private: it stays on the server and is only sent to Cloudflare. It can also be set in wp-config.php as TALA_GUARD_SECRET_KEY.', 'tala-guard' )
	) . '</p>';
}

add_filter( 'plugin_action_links_' . plugin_basename( TALA_GUARD_FILE ), 'tala_guard_action_links' );

/**
 * A "Settings" link under the plugin's name in Plugins, to its section on Settings → Discussion.
 *
 * @param string[] $links Action links.
 * @return string[]
 */
function tala_guard_action_links( $links ) {
	array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-discussion.php#tala-guard' ) ), esc_html__( 'Settings', 'tala-guard' ) ) );
	return $links;
}

add_action( 'admin_init', 'tala_guard_privacy_policy' );

/**
 * Suggests a paragraph for the privacy policy (Settings → Privacy → Policy Guide).
 */
function tala_guard_privacy_policy(): void {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$text = '<p class="privacy-policy-tutorial">' . esc_html__( 'Tala Guard uses Cloudflare Turnstile on the comment form. Add this to the part of your policy about comments.', 'tala-guard' ) . '</p>'
		. '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'tala-guard' ) . ' </strong>'
		. sprintf(
			/* translators: %s: address of Cloudflare's Turnstile privacy addendum. */
			esc_html__( 'When you write a comment, Cloudflare Turnstile checks that a person, not an automated program, is sending it. To do so, Cloudflare receives technical information about your browser and device, and your IP address, and uses it only for this check; Turnstile doesn\'t read what you type. Cloudflare\'s Turnstile privacy addendum explains more: %s', 'tala-guard' ),
			// A link in the policy's text, not content a page loads (Plugin Check's offloading rule
			// matches every cloudflare.com address).
			'https://www.cloudflare.com/turnstile-privacy-policy/' // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent
		)
		. '</p>';
	wp_add_privacy_policy_content( __( 'Tala Guard', 'tala-guard' ), wp_kses_post( $text ) );
}
