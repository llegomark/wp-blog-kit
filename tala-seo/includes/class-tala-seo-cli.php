<?php
/**
 * WP-CLI commands: find posts that aren't SEO-ready, and import data from another SEO plugin.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tala SEO tools.
 */
class Tala_SEO_CLI {

	/**
	 * Readable names of the copied fields.
	 */
	private const array FIELD_NAMES = array(
		TALA_SEO_TITLE            => 'SEO title',
		TALA_SEO_DESCRIPTION      => 'meta description',
		TALA_SEO_CANONICAL        => 'canonical',
		TALA_SEO_NOINDEX          => 'noindex',
		TALA_SEO_REDIRECT         => 'redirect',
		TALA_SEO_PRIMARY_CATEGORY => 'primary category',
		'tala_seo_fediverse'      => 'Fediverse handle',
		'tala_fediverse_url'      => 'Fediverse profile',
	);

	/**
	 * Whether this is a dry run.
	 *
	 * @var bool
	 */
	private bool $dry = false;

	/**
	 * Values copied so far, by "type:meta_key".
	 *
	 * @var array<string, int>
	 */
	private array $copied = array();

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
	public function audit( array $args, array $assoc_args ): void {
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
	 * Copies SEO data from Yoast SEO, Rank Math, The SEO Framework or SEOPress: titles, meta
	 * descriptions, canonicals, noindex, primary categories and permanent redirects of posts and
	 * pages; titles, descriptions and noindex of categories, tags and author pages; and authors'
	 * Fediverse handles. Existing Tala SEO values are never overwritten, and running it twice
	 * copies nothing new.
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
	public function import( array $args, array $assoc_args ): void {
		$this->dry    = ! empty( $assoc_args['dry-run'] );
		$this->copied = array();

		foreach ( $this->post_ids( 'any' ) as $id ) {
			foreach ( array(
				'title'       => TALA_SEO_TITLE,
				'description' => TALA_SEO_DESCRIPTION,
			) as $field => $key ) {
				$this->copy( 'post', $id, $key, tala_seo_legacy_value( $id, $field ) );
			}
			$this->copy( 'post', $id, TALA_SEO_CANONICAL, tala_seo_sanitize_url( tala_seo_legacy_value( $id, 'canonical' ) ) );
			$this->copy( 'post', $id, TALA_SEO_NOINDEX, tala_seo_legacy_value( $id, 'noindex' ) );
			$this->copy( 'post', $id, TALA_SEO_REDIRECT, $this->legacy_redirect( $id ) );
			if ( is_object_in_taxonomy( (string) get_post_type( $id ), 'category' ) && ! get_post_meta( $id, TALA_SEO_PRIMARY_CATEGORY, true ) ) {
				$primary = tala_seo_chosen_primary_category( $id );
				$this->copy( 'post', $id, TALA_SEO_PRIMARY_CATEGORY, $primary ? (string) $primary->term_id : '' );
			}
		}

		$terms = get_terms(
			array(
				'taxonomy'   => tala_seo_term_taxonomies(),
				'hide_empty' => false,
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			foreach ( array(
				'title'       => TALA_SEO_TITLE,
				'description' => TALA_SEO_DESCRIPTION,
				'noindex'     => TALA_SEO_NOINDEX,
			) as $field => $key ) {
				$this->copy( 'term', $term->term_id, $key, tala_seo_legacy_term_value( $term, $field ) );
			}
		}

		foreach ( get_users( array( 'fields' => 'ID' ) ) as $user_id ) {
			$fields = array(
				'title'         => TALA_SEO_TITLE,
				'description'   => TALA_SEO_DESCRIPTION,
				'noindex'       => TALA_SEO_NOINDEX,
				'fediverse'     => 'tala_seo_fediverse',
				'fediverse_url' => 'tala_fediverse_url',
			);
			foreach ( $fields as $field => $key ) {
				$this->copy( 'user', (int) $user_id, $key, tala_seo_legacy_user_value( (int) $user_id, $field ) );
			}
		}

		$rows = array();
		foreach ( $this->copied as $what => $count ) {
			list( $type, $key ) = explode( ':', $what, 2 );
			$rows[]             = array(
				'type'   => $type,
				'field'  => self::FIELD_NAMES[ $key ] ?? $key,
				'copied' => $count,
			);
		}
		if ( $rows ) {
			WP_CLI\Utils\format_items( 'table', $rows, array( 'type', 'field', 'copied' ) );
		}
		WP_CLI::success( sprintf( '%s %d value(s).', $this->dry ? 'Dry run, nothing changed. Would copy' : 'Copied', array_sum( $this->copied ) ) );
	}

	/**
	 * Copies a value into a Tala SEO field that is still empty.
	 *
	 * @param string $type  Meta type: post, term or user.
	 * @param int    $id    Object ID.
	 * @param string $key   Meta key.
	 * @param string $value Value from the other plugin ('' = nothing to copy).
	 */
	private function copy( string $type, int $id, string $key, string $value ): void {
		// The raw value: get_metadata() would return a registered default (0) for unset fields.
		if ( '' === $value || ! empty( get_metadata_raw( $type, $id, $key, true ) ) ) {
			return;
		}
		$what                  = $type . ':' . $key;
		$this->copied[ $what ] = ( $this->copied[ $what ] ?? 0 ) + 1;
		if ( ! $this->dry ) {
			update_metadata( $type, $id, $key, $value );
		}
	}

	/**
	 * A post's permanent redirect in The SEO Framework or SEOPress, or ''.
	 *
	 * Temporary SEOPress redirects (302, 307) and ones that apply to logged-in visitors only are
	 * left out: Tala SEO's redirect is permanent and applies to everyone.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function legacy_redirect( int $post_id ): string {
		// The SEO Framework keeps it under the plain "redirect" key, which other plugins may use too:
		// only trusted when The SEO Framework ran on this site (it keeps its settings when deleted).
		if ( false !== get_option( 'autodescription-site-settings' ) ) {
			$tsf = tala_seo_sanitize_redirect( get_post_meta( $post_id, 'redirect', true ) );
			if ( '' !== $tsf ) {
				return $tsf;
			}
		}
		$type = (string) get_post_meta( $post_id, '_seopress_redirections_type', true );
		if ( 'yes' !== get_post_meta( $post_id, '_seopress_redirections_enabled', true )
			|| 'only_logged_in' === get_post_meta( $post_id, '_seopress_redirections_logged_status', true )
			|| ! in_array( $type, array( '', '301' ), true ) ) {
			return '';
		}
		return tala_seo_sanitize_redirect( get_post_meta( $post_id, '_seopress_redirections_value', true ) );
	}

	/**
	 * IDs of all SEO post types with the given status.
	 *
	 * @param string $status Post status.
	 * @return int[]
	 */
	private function post_ids( string $status ): array {
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

WP_CLI::add_command( 'tala-seo', Tala_SEO_CLI::class );
