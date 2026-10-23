<?php
/**
 * WordPress test configuration (read by wp-phpunit's bootstrap and its installer).
 *
 * Every value can come from the environment; the defaults fit the local setup in the README: the
 * WordPress copy that tests/bin/install-wp-tests.sh downloads, and MariaDB through its Unix socket as
 * your own user, with no password. The test database is emptied and filled again on every run, so
 * it must never be a site's real database.
 *
 * @package TalaTests
 */

define( 'WP_DEBUG', true );

require_once __DIR__ . '/includes/env.php';

define( 'ABSPATH', rtrim( tala_tests_env( 'WP_CORE_DIR', dirname( __DIR__, 2 ) . '/.cache/wordpress' ), '/' ) . '/' );

define( 'DB_NAME', tala_tests_env( 'WP_TESTS_DB_NAME', 'wp_tests_blogkit' ) );
define( 'DB_USER', tala_tests_env( 'WP_TESTS_DB_USER', tala_tests_env( 'USER', 'root' ) ) );
define( 'DB_PASSWORD', tala_tests_env( 'WP_TESTS_DB_PASSWORD', '' ) );
define( 'DB_HOST', tala_tests_env( 'WP_TESTS_DB_HOST', 'localhost' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the test install's own prefix.

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Tala Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
