<?php
/**
 * Runs when Tala Ads is deleted from Plugins (not on deactivation): removes its settings, on every site of a network.
 *
 * @package TalaAds
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// On a network WordPress runs this once, on the site the plugin is deleted from, and each site has
// settings of its own.
$tala_ads_sites = is_multisite()
	? get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	)
	: array( get_current_blog_id() );

foreach ( $tala_ads_sites as $tala_ads_site ) {
	if ( is_multisite() ) {
		switch_to_blog( (int) $tala_ads_site );
	}
	delete_option( 'tala_ads' );
	if ( is_multisite() ) {
		restore_current_blog();
	}
}
