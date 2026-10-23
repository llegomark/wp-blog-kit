<?php
/**
 * Thrown instead of redirecting, so a test can read where WordPress would have sent the visitor.
 *
 * @package TalaTests
 */

/**
 * A redirect a test caught.
 */
class Tala_SEO_Redirect_Caught extends RuntimeException {

	/**
	 * Redirect target.
	 *
	 * @var string
	 */
	public string $location;

	/**
	 * HTTP status.
	 *
	 * @var int
	 */
	public int $status;

	/**
	 * The X-Redirect-By value.
	 *
	 * @var string
	 */
	public string $by;

	/**
	 * Records a redirect.
	 *
	 * @param string $location Target.
	 * @param int    $status   HTTP status.
	 * @param string $by       X-Redirect-By value.
	 */
	public function __construct( string $location, int $status, string $by ) {
		parent::__construct( "Redirect ($status) to $location" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- read by tests, never printed in a page.
		$this->location = $location;
		$this->status   = $status;
		$this->by       = $by;
	}
}
