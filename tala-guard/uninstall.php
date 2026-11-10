<?php
/**
 * Runs when Tala Guard is deleted from Plugins (not on deactivation): removes its settings, the
 * Turnstile keys, on every site of a network.
 *
 * @package TalaGuard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// On a network WordPress runs this once, on the site the plugin is deleted from, and each site has
// settings of its own.
$tala_guard_sites = is_multisite()
	? get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	)
	: array( get_current_blog_id() );

foreach ( $tala_guard_sites as $tala_guard_site ) {
	if ( is_multisite() ) {
		switch_to_blog( (int) $tala_guard_site );
	}
	delete_option( 'tala_guard' );
	if ( is_multisite() ) {
		restore_current_blog();
	}
}
