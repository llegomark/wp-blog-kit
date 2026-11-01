<?php
/**
 * WP-CLI commands: find posts that aren't SEO-ready, import data from another SEO plugin, write
 * missing meta descriptions from the posts' opening text, and map PDFs to their articles.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/documents.php';

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
		'separator'               => 'title separator',
		'home_description'        => 'home page description',
		'publisher_type'          => 'site owner type',
		'publisher_name'          => 'site owner name',
		'same_as'                 => 'official profiles',
		'default_image'           => 'default share image',
		'verify'                  => 'verification codes',
		'attachment_pages'        => 'attachment pages off',
		'organization_type'       => 'kind of organization',
		'address'                 => 'postal address',
		'telephone'               => 'telephone',
		'email'                   => 'email',
	);

	/**
	 * Yoast SEO's title separators, by the name it stores, that Tala SEO offers too.
	 */
	private const array YOAST_SEPARATORS = array(
		'sc-pipe'   => '|',
		'sc-dash'   => '-',
		'sc-ndash'  => '–',
		'sc-mdash'  => '—',
		'sc-colon'  => ':',
		'sc-middot' => '·',
		'sc-bull'   => '•',
	);

	/**
	 * The plugin whose settings and values the import reads first: yoast, rank-math or ''.
	 *
	 * @var string
	 */
	private string $source = '';

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
		$format = $assoc_args['format'] ?? 'table';
		$rows   = array();
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
					'title'   => $this->cell( get_the_title( $id ), 'csv' === $format ),
					'missing' => implode( ', ', array_keys( $problems ) ),
				);
			}
		}
		WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'type', 'title', 'missing' ) );
	}

	/**
	 * Text written by a site's authors, safe to print: one line without control characters (which
	 * a terminal could act on), and in CSV no cell a spreadsheet would read as a formula.
	 *
	 * @param string $text Text.
	 * @param bool   $csv  Whether the output is CSV.
	 * @return string
	 */
	private function cell( string $text, bool $csv ): string {
		$text = trim( (string) preg_replace( '/[\p{Cc}\s]+/u', ' ', $text ) );
		return $csv && preg_match( '/^[=+\-@]/', $text ) ? "'" . $text : $text;
	}

	/**
	 * Copies SEO data from Yoast SEO, Rank Math, The SEO Framework or SEOPress: titles, meta
	 * descriptions, canonicals, noindex, primary categories and permanent redirects of posts and
	 * pages; titles, descriptions and noindex of categories, tags and author pages; and authors'
	 * Fediverse handles. Titles and descriptions made with the other plugin's variables
	 * (%%title%% %%sep%% %%sitename%%) are filled in, and the site name it added is taken off, as
	 * Tala SEO adds it.
	 *
	 * From Yoast SEO or Rank Math it also copies the site-wide settings into Settings → SEO (title
	 * separator, home page description, site owner, official profiles, default share image,
	 * verification codes), turns off attachment pages if the plugin redirected them, and hides from
	 * search engines each category, tag or author page that the plugin hid as a rule, so the same
	 * pages stay in and out of search results.
	 *
	 * Existing Tala SEO values are never overwritten, and running it twice copies nothing new.
	 *
	 * ## OPTIONS
	 *
	 * [--from=<plugin>]
	 * : Whose settings and values come first when both Yoast SEO and Rank Math left some: yoast or
	 * rank-math. By default, the one that is active, or the only one that left settings.
	 *
	 * [--dry-run]
	 * : Count what would be copied without changing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp tala-seo import --dry-run
	 *     wp tala-seo import --from=rank-math
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function import( array $args, array $assoc_args ): void {
		$this->dry    = ! empty( $assoc_args['dry-run'] );
		$this->copied = array();
		$this->source = $this->settings_source( (string) ( $assoc_args['from'] ?? '' ) );

		foreach ( $this->post_ids( 'any' ) as $id ) {
			foreach ( array(
				'title'       => TALA_SEO_TITLE,
				'description' => TALA_SEO_DESCRIPTION,
			) as $field => $key ) {
				$this->copy( 'post', $id, $key, $this->legacy_text( $id, $field ) );
			}
			$canonical = tala_seo_sanitize_url( tala_seo_legacy_value( $id, 'canonical' ) );
			if ( $this->copy( 'post', $id, TALA_SEO_CANONICAL, $canonical ) ) {
				WP_CLI::log( sprintf( 'Post %d canonical: %s', $id, $canonical ) );
			}
			$this->copy( 'post', $id, TALA_SEO_NOINDEX, tala_seo_legacy_value( $id, 'noindex' ) );
			$redirect = $this->legacy_redirect( $id );
			if ( $this->copy( 'post', $id, TALA_SEO_REDIRECT, $redirect ) ) {
				WP_CLI::log( sprintf( 'Post %d redirects to %s', $id, $redirect ) );
			}
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

		if ( '' !== $this->source ) {
			$this->import_settings();
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
	 * @return bool Whether it was copied (or would be, in a dry run).
	 * @phpstan-impure It counts what it copies and writes the value.
	 */
	private function copy( string $type, int $id, string $key, string $value ): bool {
		// The raw value: get_metadata() would return a registered default (0) for unset fields.
		if ( '' === $value || ! empty( get_metadata_raw( $type, $id, $key, true ) ) ) {
			return false;
		}
		$what                  = $type . ':' . $key;
		$this->copied[ $what ] = ( $this->copied[ $what ] ?? 0 ) + 1;
		if ( ! $this->dry ) {
			update_metadata( $type, $id, $key, $value );
		}
		return true;
	}

	/**
	 * Whose settings to import: the plugin named with --from, else the active one of Yoast SEO and
	 * Rank Math, else the only one that left settings; '' when there are none to import, or when
	 * both left settings and none is active (then --from decides).
	 *
	 * @param string $from yoast, rank-math or ''.
	 * @return string
	 */
	private function settings_source( string $from ): string {
		$has = array(
			'yoast'     => is_array( get_option( 'wpseo_titles' ) ),
			'rank-math' => is_array( get_option( 'rank-math-options-titles' ) ),
		);
		if ( '' !== $from ) {
			if ( ! isset( $has[ $from ] ) ) {
				WP_CLI::error( '--from takes yoast or rank-math.' );
			}
			if ( ! $has[ $from ] ) {
				WP_CLI::warning( sprintf( 'No %s settings on this site: only post, term and author values are imported.', $from ) );
				return '';
			}
			return $from;
		}
		$active = array_map( static fn( string $file ): string => dirname( $file ), (array) get_option( 'active_plugins', array() ) );
		if ( $has['rank-math'] && in_array( 'seo-by-rank-math', $active, true ) ) {
			return 'rank-math';
		}
		if ( $has['yoast'] && array_intersect( array( 'wordpress-seo', 'wordpress-seo-premium' ), $active ) ) {
			return 'yoast';
		}
		if ( $has['yoast'] && $has['rank-math'] ) {
			WP_CLI::warning( 'Both Yoast SEO and Rank Math left settings here, and neither is active: the site-wide settings were left alone. Run the import again with --from=yoast or --from=rank-math.' );
			return '';
		}
		return $has['yoast'] ? 'yoast' : ( $has['rank-math'] ? 'rank-math' : '' );
	}

	/**
	 * A post's title or description from another plugin, the import's source first, with that
	 * plugin's variables filled in; a title also loses the site name the plugin added. '' when
	 * there is none, when a variable can't be filled in, or when the title is just the post's own.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   title or description.
	 * @return string
	 */
	private function legacy_text( int $post_id, string $field ): string {
		$keys = 'title' === $field
			? array( '_yoast_wpseo_title', 'rank_math_title', '_genesis_title', '_seopress_titles_title' )
			: array( '_yoast_wpseo_metadesc', 'rank_math_description', '_genesis_description', '_seopress_titles_desc' );
		if ( 'rank-math' === $this->source ) {
			$keys = array( $keys[1], $keys[0], $keys[2], $keys[3] );
		}
		foreach ( $keys as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$text = $this->fill_variables( $raw, $post_id );
				if ( 'title' === $field ) {
					$text = tala_seo_strip_site_name( $text );
					if ( 0 === strcasecmp( $text, tala_seo_clean_text( get_the_title( $post_id ) ) ) ) {
						return '';
					}
				}
				return $text;
			}
		}
		return '';
	}

	/**
	 * Fills in the variables of Yoast SEO (%%title%%), Rank Math (%title%), The SEO Framework and
	 * SEOPress in a post's title or description. '' when one is left that has no value here.
	 *
	 * @param string $text    Text with variables.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	private function fill_variables( string $text, int $post_id ): string {
		$post     = get_post( $post_id );
		$title    = tala_seo_clean_text( get_the_title( $post_id ) );
		$category = tala_seo_chosen_primary_category( $post_id );
		if ( ! $category ) {
			$first    = get_the_category( $post_id );
			$category = $first ? $first[0] : null;
		}
		$excerpt = $post && has_excerpt( $post ) ? tala_seo_clean_text( $post->post_excerpt ) : '';
		$values  = array(
			'title'            => $title,
			'post_title'       => $title,
			'sitename'         => tala_seo_site_name(),
			'sitetitle'        => tala_seo_site_name(),
			'sitedesc'         => tala_seo_clean_text( get_bloginfo( 'description' ) ),
			'tagline'          => tala_seo_clean_text( get_bloginfo( 'description' ) ),
			'sep'              => tala_seo_settings()['separator'],
			'page'             => '',
			'pagenumber'       => '',
			'pagetotal'        => '',
			'category'         => $category ? $category->name : '',
			'primary_category' => $category ? $category->name : '',
			'excerpt'          => $excerpt,
			'excerpt_only'     => $excerpt,
			'post_excerpt'     => $excerpt,
			'currentyear'      => wp_date( 'Y' ),
			'date'             => $post ? (string) get_the_date( '', $post ) : '',
		);
		$unknown = false;
		$text    = (string) preg_replace_callback(
			'/%%([a-z0-9_]+)%%|%([a-z0-9_]+)%/i',
			static function ( array $variable ) use ( $values, &$unknown ): string {
				$key = strtolower( '' !== $variable[1] ? $variable[1] : $variable[2] );
				if ( array_key_exists( $key, $values ) ) {
					return (string) $values[ $key ];
				}
				$unknown = true;
				return $variable[0];
			},
			$text
		);
		return $unknown ? '' : tala_seo_clean_text( $text );
	}

	/**
	 * Copies the site-wide settings of the import's source into Settings → SEO (only the ones still
	 * at their defaults), turns off attachment pages if the plugin redirected them, and gives each
	 * category, tag or author page the plugin hid as a rule Tala SEO's own "hide" switch.
	 */
	private function import_settings(): void {
		$yoast    = 'yoast' === $this->source;
		$titles   = (array) get_option( $yoast ? 'wpseo_titles' : 'rank-math-options-titles', array() );
		$social   = (array) get_option( 'wpseo_social', array() );
		$general  = (array) get_option( $yoast ? 'wpseo' : 'rank-math-options-general', array() );
		$current  = tala_seo_settings();
		$stored   = get_option( 'tala_seo', array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$settings = array();
		WP_CLI::log( sprintf( 'Site-wide settings from %s.', $yoast ? 'Yoast SEO' : 'Rank Math' ) );

		$separator = $yoast ? ( self::YOAST_SEPARATORS[ (string) ( $titles['separator'] ?? '' ) ] ?? '' ) : html_entity_decode( (string) ( $titles['title_separator'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$separator = trim( $separator );
		if ( '|' === $current['separator'] && in_array( $separator, tala_seo_separators(), true ) && '|' !== $separator ) {
			$settings['separator'] = $separator;
		}

		$description = (string) ( $yoast ? ( $titles['metadesc-home-wpseo'] ?? '' ) : ( $titles['homepage_description'] ?? '' ) );
		if ( '' === $current['home_description'] && '' !== trim( $description ) && ! preg_match( '/%%?[a-z_]+%%?/i', $description ) ) {
			$settings['home_description'] = tala_seo_clean_text( $description );
		}

		$type = (string) ( $yoast ? ( $titles['company_or_person'] ?? '' ) : ( $titles['knowledgegraph_type'] ?? '' ) );
		if ( 'person' === $type && ! isset( $stored['publisher_type'] ) ) {
			$settings['publisher_type'] = 'person';
		}
		$name = tala_seo_clean_text( (string) ( $yoast ? ( $titles['company_name'] ?? '' ) : ( $titles['knowledgegraph_name'] ?? '' ) ) );
		if ( 'person' !== $type && '' === $current['publisher_name'] && '' !== $name && 0 !== strcasecmp( $name, tala_seo_site_name() ) ) {
			$settings['publisher_name'] = $name;
		}

		$profiles = $yoast
			? array_merge( array( $social['facebook_site'] ?? '', $this->x_profile( $social['twitter_site'] ?? '' ) ), (array) ( $social['other_social_urls'] ?? array() ) )
			: array_merge( array( $titles['social_url_facebook'] ?? '', $this->x_profile( $titles['twitter_author_names'] ?? '' ) ), (array) preg_split( '/\R/', (string) ( $titles['social_additional_profiles'] ?? '' ) ) );
		$profiles = array_values( array_unique( array_filter( array_map( 'tala_seo_sanitize_url', $profiles ) ) ) );
		if ( ! $current['same_as'] && $profiles ) {
			$settings['same_as'] = $profiles;
		}

		$image_id  = (int) ( $yoast ? ( $social['og_default_image_id'] ?? 0 ) : ( $titles['open_graph_image_id'] ?? 0 ) );
		$image_url = (string) ( $yoast ? ( $social['og_default_image'] ?? '' ) : ( $titles['open_graph_image'] ?? '' ) );
		if ( ! $image_id && '' !== $image_url ) {
			$image_id = attachment_url_to_postid( set_url_scheme( $image_url ) );
		}
		if ( ! $current['default_image'] && $image_id && wp_attachment_is_image( $image_id ) ) {
			$settings['default_image'] = $image_id;
		}

		$codes  = array(
			'google' => (string) ( $yoast ? ( $general['googleverify'] ?? '' ) : ( $general['google_verify'] ?? '' ) ),
			'bing'   => (string) ( $yoast ? ( $general['msverify'] ?? '' ) : ( $general['bing_verify'] ?? '' ) ),
		);
		$verify = $current['verify'];
		foreach ( $codes as $service => $code ) {
			$code = tala_seo_sanitize_verification( $code, $service );
			if ( '' === $verify[ $service ] && '' !== $code ) {
				$verify[ $service ] = $code;
				$settings['verify'] = $verify;
			}
		}

		$settings += $this->organization_details( $yoast, $titles, $current );

		foreach ( array_keys( $settings ) as $key ) {
			$this->copied[ 'site:' . ( in_array( $key, array( 'street_address', 'locality', 'region', 'postal_code', 'country' ), true ) ? 'address' : $key ) ] = 1;
		}
		if ( $settings && ! $this->dry ) {
			update_option( 'tala_seo', array_merge( $current, $settings ) );
		}

		$redirected = $yoast ? ! empty( $titles['disable-attachment'] ) : 'on' === ( $general['attachment_redirect_urls'] ?? '' );
		if ( $redirected && get_option( 'wp_attachment_pages_enabled' ) ) {
			$this->copied['site:attachment_pages'] = 1;
			if ( ! $this->dry ) {
				update_option( 'wp_attachment_pages_enabled', '0' );
			}
		}

		$this->hide_archives( $titles );
	}

	/**
	 * The organization's kind, postal address, telephone and email from Yoast Local SEO (wpseo_local)
	 * or Rank Math's local SEO settings, for the ones still empty in Settings → SEO. The address is
	 * copied whole, or not at all when part of it is already filled in.
	 *
	 * @param bool                 $yoast   Whether the source is Yoast SEO.
	 * @param array<string, mixed> $titles  Rank Math's title settings (rank-math-options-titles).
	 * @param array<string, mixed> $current Tala SEO's settings now.
	 * @return array<string, string>
	 */
	private function organization_details( bool $yoast, array $titles, array $current ): array {
		if ( $yoast ) {
			$local   = (array) get_option( 'wpseo_local', array() );
			$type    = (string) ( $local['business_type'] ?? '' );
			$address = array(
				'street_address' => implode( ', ', array_filter( array( trim( (string) ( $local['location_address'] ?? '' ) ), trim( (string) ( $local['location_address_2'] ?? '' ) ) ) ) ),
				'locality'       => (string) ( $local['location_city'] ?? '' ),
				'region'         => (string) ( $local['location_state'] ?? '' ),
				'postal_code'    => (string) ( $local['location_zipcode'] ?? '' ),
				'country'        => (string) ( $local['location_country'] ?? '' ),
			);
			$phone   = (string) ( $local['location_phone'] ?? '' );
			$email   = (string) ( $local['location_email'] ?? '' );
		} else {
			$parts   = (array) ( $titles['local_address'] ?? array() );
			$type    = (string) ( $titles['local_business_type'] ?? '' );
			$address = array(
				'street_address' => (string) ( $parts['streetAddress'] ?? '' ),
				'locality'       => (string) ( $parts['addressLocality'] ?? '' ),
				'region'         => (string) ( $parts['addressRegion'] ?? '' ),
				'postal_code'    => (string) ( $parts['postalCode'] ?? '' ),
				'country'        => (string) ( $parts['addressCountry'] ?? '' ),
			);
			$numbers = (array) ( $titles['phone_numbers'] ?? array() );
			$phone   = (string) ( is_array( $numbers[0] ?? null ) ? ( $numbers[0]['number'] ?? '' ) : '' );
			$email   = (string) ( $titles['email'] ?? '' );
		}
		$found   = tala_seo_sanitize_organization(
			$address + array(
				'organization_type' => $type,
				'telephone'         => $phone,
				'email'             => $email,
			)
		);
		$details = array();
		if ( 'Organization' === $current['organization_type'] && 'Organization' !== $found['organization_type'] ) {
			$details['organization_type'] = $found['organization_type'];
		}
		$keys = array_keys( $address );
		if ( ! array_filter( array_intersect_key( $current, array_flip( $keys ) ) ) ) {
			$details += array_filter( array_intersect_key( $found, array_flip( $keys ) ), static fn( string $value ): bool => '' !== $value );
		}
		foreach ( array( 'telephone', 'email' ) as $key ) {
			if ( '' === $current[ $key ] && '' !== $found[ $key ] ) {
				$details[ $key ] = $found[ $key ];
			}
		}
		return $details;
	}

	/**
	 * Category, tag and author pages the import's source kept out of search engines as a rule get
	 * Tala SEO's own "hide" switch, one by one, unless the plugin let that page in on purpose.
	 *
	 * @param array<string, mixed> $titles The plugin's title settings (wpseo_titles or rank-math-options-titles).
	 */
	private function hide_archives( array $titles ): void {
		$yoast       = 'yoast' === $this->source;
		$rule        = static function ( string $key ) use ( $titles ): bool {
			return 'on' === ( $titles[ $key . '_custom_robots' ] ?? '' ) && in_array( 'noindex', (array) ( $titles[ $key . '_robots' ] ?? array() ), true );
		};
		$hidden      = array(
			'category' => $yoast ? ! empty( $titles['noindex-tax-category'] ) : $rule( 'tax_category' ),
			'post_tag' => $yoast ? ! empty( $titles['noindex-tax-post_tag'] ) : $rule( 'tax_post_tag' ),
		);
		$yoast_terms = get_option( 'wpseo_taxonomy_meta' );
		foreach ( array_keys( array_filter( $hidden ) ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$own = $yoast
					? ( is_array( $yoast_terms ) ? ( $yoast_terms[ $taxonomy ][ $term->term_id ]['wpseo_noindex'] ?? '' ) : '' )
					: ( in_array( 'index', (array) get_term_meta( $term->term_id, 'rank_math_robots', true ), true ) ? 'index' : '' );
				if ( 'index' !== $own ) {
					$this->copy( 'term', $term->term_id, TALA_SEO_NOINDEX, '1' );
				}
			}
		}
		$authors = $yoast
			? ! empty( $titles['noindex-author-wpseo'] ) || ! empty( $titles['disable-author'] )
			: $rule( 'author' ) || 'on' === ( $titles['disable_author_archives'] ?? '' );
		if ( $authors ) {
			foreach ( get_users(
				array(
					'has_published_posts' => true,
					'fields'              => 'ID',
				)
			) as $user_id ) {
				$this->copy( 'user', (int) $user_id, TALA_SEO_NOINDEX, '1' );
			}
		}
	}

	/**
	 * An X (Twitter) profile address from a username or an address, or ''.
	 *
	 * @param mixed $value Username (with or without @) or address.
	 * @return string
	 */
	private function x_profile( $value ): string {
		$value = trim( is_string( $value ) ? $value : '' );
		if ( '' === $value || str_contains( $value, '/' ) ) {
			return $value;
		}
		$handle = ltrim( $value, '@' );
		return preg_match( '/^[A-Za-z0-9_]{1,15}$/', $handle ) ? 'https://x.com/' . $handle : '';
	}

	/**
	 * Writes a meta description for each published post that has none, from the opening of its own
	 * text: its first paragraphs, cut after the last whole sentence that fits (else after a whole
	 * word, with an ellipsis), as Rank Math's automatic descriptions are, but written once, so they
	 * can be edited. A paragraph three or more of these posts start with (a template's introduction)
	 * is passed over, so their descriptions start with what is their own. Posts with a description
	 * (Tala SEO's or another plugin's) and password-protected posts are left alone.
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<types>]
	 * : Post types, separated by commas.
	 * ---
	 * default: post
	 * ---
	 *
	 * [--length=<characters>]
	 * : The most characters a description may have.
	 * ---
	 * default: 155
	 * ---
	 *
	 * [--dry-run]
	 * : Show some of the descriptions and count them, without saving any.
	 *
	 * ## EXAMPLES
	 *
	 *     wp tala-seo describe --dry-run
	 *     wp tala-seo describe --post_type=post,page
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function describe( array $args, array $assoc_args ): void {
		$dry    = ! empty( $assoc_args['dry-run'] );
		$types  = array_values( array_intersect( array_map( 'trim', explode( ',', (string) ( $assoc_args['post_type'] ?? 'post' ) ) ), tala_seo_post_types() ) );
		$length = max( 50, min( 300, (int) ( $assoc_args['length'] ?? 155 ) ) );
		if ( ! $types ) {
			WP_CLI::error( 'No post type with SEO fields given.' );
		}
		$ids = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		// Which paragraphs these posts share: a template's introduction describes none of them.
		$posts  = array();
		$shared = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post && '' === tala_seo_post_description( $id ) ) {
				$posts[] = $post;
				foreach ( array_unique( tala_seo_opening_paragraphs( $post ) ) as $paragraph ) {
					$shared[ $paragraph ] = ( $shared[ $paragraph ] ?? 0 ) + 1;
				}
			}
		}
		$skip    = array_map( 'strval', array_keys( array_filter( $shared, static fn( int $count ): bool => $count >= 3 ) ) );
		$written = 0;
		$empty   = 0;
		$samples = array();
		$same    = array();
		foreach ( $posts as $post ) {
			$id   = $post->ID;
			$text = tala_seo_opening_text( $post, $length, $skip );
			if ( '' === $text ) {
				++$empty;
				continue;
			}
			$same[ $text ] = ( $same[ $text ] ?? 0 ) + 1;
			if ( count( $samples ) < 5 ) {
				$samples[] = array(
					'ID'          => $id,
					'description' => $text,
				);
			}
			if ( ! $dry ) {
				update_post_meta( $id, TALA_SEO_DESCRIPTION, $text );
			}
			++$written;
		}
		if ( $samples ) {
			WP_CLI\Utils\format_items( 'table', $samples, array( 'ID', 'description' ) );
		}
		if ( $skip ) {
			WP_CLI::log( sprintf( '%d paragraph(s) that three or more posts start with were passed over.', count( $skip ) ) );
		}
		$shared = array_sum( array_filter( $same, static fn( int $count ): bool => $count > 1 ) );
		if ( $shared ) {
			WP_CLI::warning( sprintf( '%d post(s) have only text other posts share, so their descriptions repeat: rewrite those first.', $shared ) );
		}
		if ( $empty ) {
			WP_CLI::warning( sprintf( '%d post(s) have no paragraph to start from: write their descriptions by hand (wp tala-seo audit lists them).', $empty ) );
		}
		WP_CLI::success( sprintf( '%s %d description(s).', $dry ? 'Dry run, nothing changed. Would write' : 'Wrote', $written ) );
	}

	/**
	 * A post's permanent redirect in The SEO Framework or SEOPress, or ''.
	 *
	 * Temporary SEOPress redirects (302, 307) and ones that apply to logged-in visitors only are
	 * left out: Tala SEO's redirect is permanent and applies to everyone.
	 *
	 * Either plugin's redirect field is set by whoever edits the post, a contributor's draft
	 * included, and reviewers may never have seen it (The SEO Framework's is the plain "redirect"
	 * key, which can also be added as a custom field): a redirect goes live only if the post's
	 * author could set one on the published post themselves.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function legacy_redirect( int $post_id ): string {
		$target = '';
		// The plain "redirect" key, which other plugins may use too, is read only when The SEO
		// Framework ran on this site (it keeps its settings when deleted).
		if ( false !== get_option( 'autodescription-site-settings' ) ) {
			$target = tala_seo_sanitize_redirect( get_post_meta( $post_id, 'redirect', true ) );
		}
		$type = (string) get_post_meta( $post_id, '_seopress_redirections_type', true );
		if ( '' === $target
			&& 'yes' === get_post_meta( $post_id, '_seopress_redirections_enabled', true )
			&& 'only_logged_in' !== get_post_meta( $post_id, '_seopress_redirections_logged_status', true )
			&& in_array( $type, array( '', '301' ), true ) ) {
			$target = tala_seo_sanitize_redirect( get_post_meta( $post_id, '_seopress_redirections_value', true ) );
		}
		if ( '' === $target ) {
			return '';
		}
		$post_type = get_post_type_object( (string) get_post_type( $post_id ) );
		$author    = (int) get_post_field( 'post_author', $post_id );
		if ( $post_type && user_can( $author, $post_type->cap->edit_published_posts ) ) {
			return $target;
		}
		WP_CLI::warning( sprintf( 'Post %d: left out the redirect to %s (its author can\'t edit published posts, so nobody may have reviewed it).', $post_id, $target ) );
		return '';
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

	/**
	 * Lists each PDF with the article that presents it, for the web server's rel="canonical"
	 * header on the file.
	 *
	 * A PDF that search engines index on its own sends readers straight to the file, past the
	 * article. Redirecting the file loses it from search; Google's answer for documents that aren't
	 * HTML is a Link: <article>; rel="canonical" header on the file, which keeps the file available
	 * to everyone and lets search engines show the article instead. Run this whenever PDFs are
	 * added (a daily job is enough), save the output where nginx reads its maps, and reload nginx.
	 *
	 * The article of a PDF is a published post or page, not hidden from search engines, that links
	 * to it or embeds it: the one it was uploaded to when that one presents it, else the one that
	 * presents the fewest PDFs, posts before pages, the earliest first.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : nginx prints a map block for the server's configuration.
	 * ---
	 * default: nginx
	 * options:
	 *   - nginx
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * [--variable=<name>]
	 * : The nginx variable the map sets, without "$". Give each site its own: nginx maps are shared
	 * by every site on the server.
	 * ---
	 * default: tala_seo_pdf_canonical
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp tala-seo pdf-canonicals --variable=example_pdf_canonical > /etc/nginx/maps/example-pdf-canonicals.conf
	 *     wp tala-seo pdf-canonicals --format=table
	 *
	 * @subcommand pdf-canonicals
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function pdf_canonicals( array $args, array $assoc_args ): void {
		$format   = (string) ( $assoc_args['format'] ?? 'nginx' );
		$variable = (string) ( $assoc_args['variable'] ?? 'tala_seo_pdf_canonical' );
		if ( ! preg_match( '/^[a-z_][a-z0-9_]*$/i', $variable ) ) {
			WP_CLI::error( 'The variable name may have only letters, digits and underscores, and may not start with a digit.' );
		}
		$canonicals = tala_seo_pdf_canonicals();
		if ( 'nginx' === $format ) {
			WP_CLI::line( rtrim( tala_seo_pdf_canonicals_nginx( $canonicals, $variable ) ) );
			return;
		}
		$rows = array();
		foreach ( $canonicals as $path => $url ) {
			$rows[] = array(
				'pdf'     => $path,
				'article' => $url,
			);
		}
		WP_CLI\Utils\format_items( $format, $rows, array( 'pdf', 'article' ) );
	}
}

WP_CLI::add_command( 'tala-seo', Tala_SEO_CLI::class );
