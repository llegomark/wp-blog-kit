<?php
/**
 * Reading the test settings from the environment. Used by the bootstrap and by
 * wp-tests-config.php, which WordPress's test installer also reads on its own.
 *
 * @package TalaTests
 */

/**
 * An environment variable, or $fallback when it is unset or empty.
 *
 * @param string $name     Variable name.
 * @param string $fallback Default value.
 * @return string
 */
function tala_tests_env( string $name, string $fallback ): string {
	$value = getenv( $name );
	return is_string( $value ) && '' !== $value ? $value : $fallback;
}
