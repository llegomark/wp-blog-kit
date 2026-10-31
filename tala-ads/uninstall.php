<?php
/**
 * Runs when Tala Ads is deleted from Plugins (not on deactivation): removes its settings.
 *
 * @package TalaAds
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'tala_ads' );
