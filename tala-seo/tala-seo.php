<?php
/**
 * Plugin Name:       Tala SEO
 * Description:       Lean SEO for blogs: titles, meta descriptions, canonicals, Open Graph, JSON-LD structured data, visible breadcrumbs, sitemap and robots.txt tuning, and a publish check that blocks posts without a meta description or featured image.
 * Version:           1.5.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Mark Anthony Llego
 * Author URI:        https://edventure.com.ph
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tala-seo
 * Update URI:        false
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

const TALA_SEO_VERSION = '1.5.0';
const TALA_SEO_FILE    = __FILE__;

// Post meta keys. The leading underscore hides them from the Custom Fields box.
const TALA_SEO_TITLE       = '_tala_seo_title';
const TALA_SEO_DESCRIPTION = '_tala_seo_description';
const TALA_SEO_CANONICAL   = '_tala_seo_canonical';
const TALA_SEO_NOINDEX     = '_tala_seo_noindex';

// Post meta: the category that leads the breadcrumbs, and an address the post sends visitors to.
const TALA_SEO_PRIMARY_CATEGORY = '_tala_seo_primary_category';
const TALA_SEO_REDIRECT         = '_tala_seo_redirect';

// Post and term meta: addresses a page or term archive had before it moved (see redirects.php).
const TALA_SEO_OLD_PATH = '_tala_seo_old_path';

require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/terms.php';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/schema.php';
require __DIR__ . '/includes/breadcrumbs.php';
require __DIR__ . '/includes/sitemap.php';
require __DIR__ . '/includes/publish-guard.php';
require __DIR__ . '/includes/users.php';
require __DIR__ . '/includes/redirects.php';

if ( is_admin() ) {
	require __DIR__ . '/includes/admin.php';
	require __DIR__ . '/includes/admin-fields.php';
	require __DIR__ . '/includes/admin-columns.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require __DIR__ . '/includes/class-tala-seo-cli.php';
}

/**
 * Post types that get SEO fields.
 *
 * @return string[]
 */
function tala_seo_post_types() {
	return apply_filters( 'tala_seo_post_types', array( 'post', 'page' ) );
}

add_action( 'init', 'tala_seo_register_settings' );

/**
 * Registers the plugin settings on init (not admin_init), so values saved through WP-CLI or
 * the REST API go through the same sanitizing as the settings screen.
 */
function tala_seo_register_settings(): void {
	register_setting(
		'tala_seo',
		'tala_seo',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'tala_seo_sanitize_settings',
			'default'           => array(),
		)
	);
}

add_action( 'init', 'tala_seo_register_meta' );

/**
 * Registers the per-post SEO fields so the block editor can read and save them over REST.
 */
function tala_seo_register_meta(): void {
	$can_edit = static function ( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	};

	foreach ( tala_seo_post_types() as $type ) {
		register_post_meta(
			$type,
			TALA_SEO_TITLE,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'tala_seo_sanitize_line',
				'auth_callback'     => $can_edit,
			)
		);
		register_post_meta(
			$type,
			TALA_SEO_DESCRIPTION,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'tala_seo_sanitize_line',
				'auth_callback'     => $can_edit,
			)
		);
		register_post_meta(
			$type,
			TALA_SEO_CANONICAL,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $can_edit,
			)
		);
		register_post_meta(
			$type,
			TALA_SEO_NOINDEX,
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => $can_edit,
			)
		);
		register_post_meta(
			$type,
			TALA_SEO_REDIRECT,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'tala_seo_sanitize_redirect',
				'auth_callback'     => $can_edit,
			)
		);
		if ( is_object_in_taxonomy( $type, 'category' ) ) {
			register_post_meta(
				$type,
				TALA_SEO_PRIMARY_CATEGORY,
				array(
					'type'              => 'integer',
					'single'            => true,
					'default'           => 0,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => $can_edit,
				)
			);
		}
	}

	// Crops in the three aspect ratios Google recommends for article images. Sized so that any
	// featured image of at least 1200 × 675 gets all three, each well above 300,000 pixels.
	add_image_size( 'tala-seo-16x9', 1200, 675, true );
	add_image_size( 'tala-seo-4x3', 900, 675, true );
	add_image_size( 'tala-seo-1x1', 675, 675, true );
}

register_activation_hook( __FILE__, 'tala_seo_activate' );

/**
 * Turns off attachment pages: thin pages that only show one image, which Google shouldn't index.
 * (The plugin adds no rewrite rules, so there is nothing to flush.)
 */
function tala_seo_activate(): void {
	update_option( 'wp_attachment_pages_enabled', 0 );
}
