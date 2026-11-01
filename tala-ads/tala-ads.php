<?php
/**
 * Plugin Name:       Tala Ads
 * Description:       Google AdSense Auto ads, Google Tag Manager and Google's consent and ad blocking recovery messages on every page, each loaded once and set up with IDs, plus Google search in place of WordPress's search and an optional Innity ad box after articles. Works with any theme.
 * Version:           1.3.0
 * Requires at least: 7.0
 * Requires PHP:      8.5
 * Author:            Edventure
 * Author URI:        https://edventure.com.ph
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tala-ads
 * Update URI:        false
 *
 * @package TalaAds
 */

defined( 'ABSPATH' ) || exit;

const TALA_ADS_VERSION = '1.3.0';
const TALA_ADS_FILE    = __FILE__;

require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/output.php';
require __DIR__ . '/includes/search.php';

if ( is_admin() ) {
	require __DIR__ . '/includes/admin.php';
}
