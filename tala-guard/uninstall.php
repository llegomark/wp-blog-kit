<?php
/**
 * Runs when Tala Guard is deleted from Plugins (not on deactivation): removes its settings, the
 * Turnstile keys.
 *
 * @package TalaGuard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'tala_guard' );
