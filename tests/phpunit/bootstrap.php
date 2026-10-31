<?php
/**
 * PHPUnit bootstrap: WordPress's test framework (wp-phpunit) with Tala SEO and Tala Ads active and
 * Tala Blog as the theme, loaded from this repository.
 *
 * @package TalaTests
 */

$tala_root = dirname( __DIR__, 2 );
require $tala_root . '/vendor/autoload.php'; // PHPUnit and the PHPUnit Polyfills the framework needs.
require_once __DIR__ . '/includes/env.php';

// wp-phpunit reads the path of the test configuration from the environment.
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
$tala_tests_dir = tala_tests_env( 'WP_TESTS_DIR', $tala_root . '/vendor/wp-phpunit/wp-phpunit' );
require_once $tala_tests_dir . '/includes/functions.php';

// WordPress finds themes in theme directories; this one holds only a link to the theme in the repo.
$tala_themes = $tala_root . '/.cache/themes';
if ( ! is_link( $tala_themes . '/tala-blog' ) ) {
	// WordPress (and its filesystem functions) isn't loaded yet.
	if ( ! is_dir( $tala_themes ) ) {
		mkdir( $tala_themes, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
	}
	symlink( $tala_root . '/tala-blog', $tala_themes . '/tala-blog' );
}

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $tala_root ): void {
		require $tala_root . '/tala-seo/tala-seo.php';
		require $tala_root . '/tala-ads/tala-ads.php';
	}
);
tests_add_filter(
	'setup_theme',
	static function () use ( $tala_themes ): void {
		register_theme_directory( $tala_themes );
		search_theme_directories( true );
		switch_theme( 'tala-blog' );
	}
);

require $tala_tests_dir . '/includes/bootstrap.php';

// On a site, Tala SEO loads its admin screens' code only in wp-admin, and its command only under
// WP-CLI. The tests load both for every test, so no test depends on which ran before it. WP-CLI
// itself is a stand-in that records what the command prints (includes/class-wp-cli.php).
require_once ABSPATH . 'wp-admin/includes/admin.php';
foreach ( array( 'admin', 'admin-fields', 'admin-columns' ) as $tala_file ) {
	require_once $tala_root . "/tala-seo/includes/{$tala_file}.php";
}
require_once __DIR__ . '/includes/class-wp-cli.php';
require_once __DIR__ . '/includes/wp-cli-utils.php';
require_once $tala_root . '/tala-seo/includes/class-tala-seo-cli.php';
require_once $tala_root . '/tala-ads/includes/admin.php';

require __DIR__ . '/includes/class-tala-seo-redirect-caught.php';
require __DIR__ . '/includes/class-tala-seo-test-case.php';
