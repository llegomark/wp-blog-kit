<?php
/**
 * Script dependencies and version for the Breadcrumbs block's editor script (index.js).
 * WordPress reads this file because of its exact name, so it can't follow the usual file naming.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array( 'wp-block-editor', 'wp-blocks', 'wp-element', 'wp-server-side-render' ),
	'version'      => '1.0.0',
);
