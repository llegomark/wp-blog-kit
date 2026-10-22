<?php
/**
 * WP-CLI commands: find posts that aren't SEO-ready, and import data from Yoast SEO or Rank Math.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tala SEO tools.
 */
class Tala_SEO_CLI {

	/**
	 * Lists published posts and pages that are missing what the publish check requires.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, csv, json or count.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp tala-seo audit
	 *     wp tala-seo audit --format=json
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function audit( $args, $assoc_args ): void {
		$rows = array();
		foreach ( $this->post_ids( 'publish' ) as $id ) {
			$problems = tala_seo_publish_problems(
				(string) get_post_type( $id ),
				tala_seo_post_description( $id ),
				(int) get_post_thumbnail_id( $id )
			);
			if ( $problems ) {
				$rows[] = array(
					'ID'      => $id,
					'type'    => get_post_type( $id ),
					'title'   => get_the_title( $id ),
					'missing' => implode( ', ', array_keys( $problems ) ),
				);
			}
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'ID', 'type', 'title', 'missing' ) );
	}

	/**
	 * Copies SEO titles, meta descriptions, canonicals and noindex from Yoast SEO or Rank Math.
	 * Existing Tala SEO values are never overwritten.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Count what would be copied without changing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp tala-seo import --dry-run
	 *     wp tala-seo import
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function import( $args, $assoc_args ): void {
		$dry    = ! empty( $assoc_args['dry-run'] );
		$fields = array(
			'title'       => TALA_SEO_TITLE,
			'description' => TALA_SEO_DESCRIPTION,
			'canonical'   => TALA_SEO_CANONICAL,
		);
		$copied = array_fill_keys( array_merge( array_keys( $fields ), array( 'noindex' ) ), 0 );

		foreach ( $this->post_ids( 'any' ) as $id ) {
			foreach ( $fields as $field => $key ) {
				$legacy = tala_seo_legacy_value( $id, $field );
				if ( '' !== $legacy && '' === (string) get_post_meta( $id, $key, true ) ) {
					++$copied[ $field ];
					if ( ! $dry ) {
						update_post_meta( $id, $key, 'canonical' === $field ? esc_url_raw( $legacy ) : $legacy );
					}
				}
			}
			if ( '1' === tala_seo_legacy_value( $id, 'noindex' ) && ! get_post_meta( $id, TALA_SEO_NOINDEX, true ) ) {
				++$copied['noindex'];
				if ( ! $dry ) {
					update_post_meta( $id, TALA_SEO_NOINDEX, true );
				}
			}
		}

		foreach ( $copied as $field => $count ) {
			WP_CLI::log( sprintf( '%-12s %d', $field, $count ) );
		}
		WP_CLI::success( $dry ? 'Dry run: nothing changed.' : 'Import finished.' );
	}

	/**
	 * IDs of all SEO post types with the given status.
	 *
	 * @param string $status Post status.
	 * @return int[]
	 */
	private function post_ids( $status ) {
		return get_posts(
			array(
				'post_type'      => tala_seo_post_types(),
				'post_status'    => $status,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
	}
}

WP_CLI::add_command( 'tala-seo', 'Tala_SEO_CLI' );
