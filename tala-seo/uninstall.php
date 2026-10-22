<?php
/**
 * Runs when Tala SEO is deleted from Plugins (not on deactivation).
 *
 * Removes the plugin's settings and the one-save "minor edit" flags. Keeps what editors wrote
 * (SEO titles, meta descriptions, canonical and noindex choices, author photos and profile links)
 * so nothing is lost if the plugin is installed again or another SEO plugin imports it.
 *
 * @package TalaSEO
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'tala_seo' );
delete_post_meta_by_key( '_tala_seo_minor_edit' );
