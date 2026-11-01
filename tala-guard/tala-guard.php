<?php
/**
 * Plugin Name:       Tala Guard
 * Description:       Keeps spam bots out of the comments with Cloudflare Turnstile: a check in the comment form that people rarely notice, confirmed with Cloudflare on the server before WordPress saves a comment. Works with any theme.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.5
 * Author:            Edventure
 * Author URI:        https://edventure.com.ph
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tala-guard
 * Update URI:        false
 *
 * @package TalaGuard
 */

defined( 'ABSPATH' ) || exit;

const TALA_GUARD_VERSION = '1.0.0';
const TALA_GUARD_FILE    = __FILE__;

require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/comments.php';

if ( is_admin() ) {
	require __DIR__ . '/includes/admin.php';
}
