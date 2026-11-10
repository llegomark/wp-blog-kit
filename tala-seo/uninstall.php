<?php
/**
 * Runs when Tala SEO is deleted from Plugins (not on deactivation), for every site of a network.
 *
 * Removes the plugin's settings, the note of its version, the one-save "minor edit" flags, the remembered old addresses
 * of pages, terms and author pages (they only serve the plugin's redirects), the "share crop tried" markers and
 * the video titles fetched from YouTube.
 * The image crops stay: they are ordinary files of the media library. Keeps what editors wrote (SEO
 * titles, meta descriptions, canonical, noindex, redirect and primary-category choices of posts,
 * video publish dates,
 * terms and authors, author photos, profile links and Fediverse handles) so nothing is lost if the
 * plugin is installed again or another SEO plugin imports it.
 *
 * No functions here, only steps: a file that declares one can run once per request.
 *
 * @package TalaSEO
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// On a network WordPress runs this once, on the site the plugin is deleted from; each site keeps its
// own settings, posts and terms, so each is cleaned in turn.
$tala_seo_sites = is_multisite()
	? get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	)
	: array( get_current_blog_id() );

foreach ( $tala_seo_sites as $tala_seo_site ) {
	if ( is_multisite() ) {
		switch_to_blog( (int) $tala_seo_site );
	}
	delete_option( 'tala_seo' );
	delete_option( 'tala_seo_version' );
	delete_post_meta_by_key( '_tala_seo_minor_edit' );
	delete_post_meta_by_key( '_tala_seo_old_path' );
	delete_post_meta_by_key( '_tala_seo_share_crop_tried' );
	delete_post_meta_by_key( '_tala_seo_videos' );
	delete_post_meta_by_key( '_tala_seo_video_failures' );
	delete_metadata( 'term', 0, '_tala_seo_old_path', '', true );
	if ( is_multisite() ) {
		restore_current_blog();
	}
}

// Users belong to the whole network: once.
delete_metadata( 'user', 0, '_tala_seo_old_path', '', true );
