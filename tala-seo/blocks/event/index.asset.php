<?php
/**
 * Script dependencies and version for the Event block's editor script (index.js).
 * The version is the plugin's, so each release reaches browsers and caches.
 * WordPress reads this file because of its exact name, so it can't follow the usual file naming.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
	'version'      => TALA_SEO_VERSION,
);
