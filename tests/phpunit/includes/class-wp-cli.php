<?php
/**
 * A stand-in for the part of WP-CLI's API that Tala SEO's command uses. It records what the
 * command registers and prints, so tests can read it.
 *
 * @package TalaTests
 */

/**
 * WP-CLI's static API.
 */
class WP_CLI {

	/**
	 * Registered commands: name => [ callable, arguments ].
	 *
	 * @var array<string, array{0: callable|string, 1: array<string, mixed>}>
	 */
	public static array $commands = array();

	/**
	 * Messages, as [ type, text ].
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	public static array $messages = array();

	/**
	 * Tables passed to WP_CLI\Utils\format_items().
	 *
	 * @var array<int, array{format: string, fields: string[]|string, items: array<int, array<string, mixed>>}>
	 */
	public static array $tables = array();

	/**
	 * Forgets what was printed.
	 */
	public static function reset(): void {
		self::$messages = array();
		self::$tables   = array();
	}

	/**
	 * Records a command.
	 *
	 * @param string               $name     Command name.
	 * @param callable|string      $callback Class or callable.
	 * @param array<string, mixed> $args     Arguments.
	 * @return bool
	 */
	public static function add_command( $name, $callback, $args = array() ): bool {
		self::$commands[ $name ] = array( $callback, $args );
		return true;
	}

	/**
	 * Records a success message.
	 *
	 * @param string $message Message.
	 */
	public static function success( $message ): void {
		self::$messages[] = array( 'success', (string) $message );
	}

	/**
	 * Records a warning.
	 *
	 * @param string $message Message.
	 */
	public static function warning( $message ): void {
		self::$messages[] = array( 'warning', (string) $message );
	}

	/**
	 * Records a line of output.
	 *
	 * @param string $message Message.
	 */
	public static function log( $message ): void {
		self::$messages[] = array( 'log', (string) $message );
	}

	/**
	 * Records a line printed as it is (WP-CLI prints these even with --quiet).
	 *
	 * @param string $message Message.
	 */
	public static function line( $message = '' ): void {
		self::$messages[] = array( 'line', (string) $message );
	}

	/**
	 * An error ends the command, as in WP-CLI.
	 *
	 * @param string $message Message.
	 * @throws RuntimeException Always.
	 */
	public static function error( $message ): never {
		throw new RuntimeException( (string) $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- read by tests, never printed in a page.
	}
}
