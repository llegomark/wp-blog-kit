<?php
/**
 * Runs when Tala SEO is deleted from Plugins (not on deactivation).
 *
 * Removes the plugin's settings, the one-save "minor edit" flags and the remembered old addresses
 * of pages and terms (they only serve the plugin's redirects). Keeps what editors wrote (SEO
 * titles, meta descriptions, canonical, noindex, redirect and primary-category choices of posts,
 * terms and authors, author photos, profile links and Fediverse handles) so nothing is lost if the
 * plugin is installed again or another SEO plugin imports it.
 *
 * @package TalaSEO
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'tala_seo' );
delete_post_meta_by_key( '_tala_seo_minor_edit' );
delete_post_meta_by_key( '_tala_seo_old_path' );
delete_metadata( 'term', 0, '_tala_seo_old_path', '', true );
