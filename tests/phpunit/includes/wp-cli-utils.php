<?php
/**
 * A stand-in for WP-CLI's table output (see class-wp-cli.php).
 *
 * @package TalaTests
 */

namespace WP_CLI\Utils;

/**
 * Records a table instead of printing it.
 *
 * @param string                           $format Output format.
 * @param array<int, array<string, mixed>> $items  Rows.
 * @param string[]|string                  $fields Columns.
 */
function format_items( $format, $items, $fields ): void {
	\WP_CLI::$tables[] = array(
		'format' => (string) $format,
		'fields' => $fields,
		'items'  => $items,
	);
}
