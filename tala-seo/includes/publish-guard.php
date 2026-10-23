<?php
/**
 * Publish guard: a post can't go live (publish or schedule) without the SEO essentials.
 * Enforced on the server, so it holds for the block editor, Quick Edit, the REST API and WP-CLI.
 *
 * Also handles "minor edits": Google asks that the updated date (and sitemap lastmod) only
 * change for significant updates, so typo fixes can keep the previous date.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

const TALA_SEO_MINOR_EDIT = '_tala_seo_minor_edit';

/**
 * What a post type needs before it can be published. Filterable per site.
 *
 * @param string $post_type Post type.
 * @return string[] Any of: description, featured_image, featured_image_alt.
 */
function tala_seo_publish_requirements( $post_type ) {
	$defaults = array(
		'post' => array( 'description', 'featured_image', 'featured_image_alt' ),
		'page' => array( 'description' ),
	);
	return (array) apply_filters( 'tala_seo_publish_requirements', $defaults[ $post_type ] ?? array(), $post_type );
}

/**
 * What still stops a post from being published.
 *
 * @param string $post_type    Post type.
 * @param string $description  Meta description.
 * @param int    $thumbnail_id Featured image attachment ID.
 * @return array<string,string> Requirement => message.
 */
function tala_seo_publish_problems( $post_type, $description, $thumbnail_id ) {
	$needed   = tala_seo_publish_requirements( $post_type );
	$problems = array();
	$is_image = $thumbnail_id > 0 && wp_attachment_is_image( $thumbnail_id );

	if ( in_array( 'description', $needed, true ) && '' === tala_seo_clean_text( $description ) ) {
		$problems['description'] = __( 'Add a meta description in the SEO panel.', 'tala-seo' );
	}
	if ( in_array( 'featured_image', $needed, true ) && ! $is_image ) {
		$problems['featured_image'] = __( 'Set a featured image.', 'tala-seo' );
	}
	if ( in_array( 'featured_image_alt', $needed, true ) && $is_image
		&& '' === trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ) ) {
		$problems['featured_image_alt'] = __( 'Describe the featured image in its "Alternative text" field.', 'tala-seo' );
	}
	return $problems;
}

add_action( 'init', 'tala_seo_guard_setup', 20 );

/**
 * Registers the minor-edit flag and the REST hooks for every SEO post type.
 */
function tala_seo_guard_setup(): void {
	foreach ( tala_seo_post_types() as $type ) {
		register_post_meta(
			$type,
			TALA_SEO_MINOR_EDIT,
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
		add_filter( "rest_pre_insert_{$type}", 'tala_seo_rest_guard', 10, 2 );
		add_action( "rest_after_insert_{$type}", 'tala_seo_reset_minor_edit' );
	}
}

/**
 * Block-editor and REST saves: refuse to publish with a clear message the editor shows.
 *
 * @param stdClass|WP_Error $prepared Post about to be saved.
 * @param WP_REST_Request   $request  Request.
 * @return stdClass|WP_Error
 */
function tala_seo_rest_guard( $prepared, $request ) {
	if ( is_wp_error( $prepared ) || str_contains( $request->get_route(), '/autosaves' ) ) {
		return $prepared;
	}
	$id       = (int) ( $prepared->ID ?? 0 );
	$type     = $id ? get_post_type( $id ) : ( $prepared->post_type ?? '' );
	$current  = $id ? get_post_status( $id ) : 'draft';
	$target   = $prepared->post_status ?? $current;
	$was_live = in_array( $current, array( 'publish', 'future' ), true );
	$meta     = $request->get_param( 'meta' );
	$meta     = is_array( $meta ) ? $meta : array();

	if ( $was_live && ! empty( $meta[ TALA_SEO_MINOR_EDIT ] ) ) {
		$GLOBALS['tala_seo_minor_edit'][ $id ] = true;
	}
	if ( ! in_array( $target, array( 'publish', 'future' ), true ) ) {
		return $prepared;
	}

	$touches_description = array_key_exists( TALA_SEO_DESCRIPTION, $meta );
	$touches_image       = $request->has_param( 'featured_media' );
	// A live post is only stopped when this save breaks something, so old posts stay editable.
	if ( $was_live && ! $touches_description && ! $touches_image ) {
		return $prepared;
	}

	if ( $touches_description ) {
		$description = (string) $meta[ TALA_SEO_DESCRIPTION ];
		if ( '' === trim( $description ) && $id ) {
			$description = tala_seo_legacy_value( $id, 'description' ); // Migrated description still counts.
		}
	} else {
		$description = $id ? tala_seo_post_description( $id ) : '';
	}
	$thumbnail = $touches_image ? (int) $request['featured_media'] : ( $id ? (int) get_post_thumbnail_id( $id ) : 0 );
	$problems  = tala_seo_publish_problems( $type, $description, $thumbnail );

	if ( $was_live ) {
		$touched  = array_merge(
			$touches_description ? array( 'description' ) : array(),
			$touches_image ? array( 'featured_image', 'featured_image_alt' ) : array()
		);
		$problems = array_intersect_key( $problems, array_flip( $touched ) );
	}
	if ( ! $problems ) {
		return $prepared;
	}
	$message = $was_live
		/* translators: %s: list of things to fix. */
		? __( 'This post is live, so it still needs this: %s', 'tala-seo' )
		/* translators: %s: list of things to fix. */
		: __( 'To publish: %s', 'tala-seo' );
	return new WP_Error(
		'tala_seo_not_ready',
		sprintf( $message, implode( ' ', $problems ) ),
		array(
			'status'   => 400,
			'problems' => array_keys( $problems ),
		)
	);
}

add_filter( 'wp_insert_post_data', 'tala_seo_guard_insert', 20, 2 );

/**
 * Every other way of saving (Quick Edit, WP-CLI, plugins): keep the post as a draft and say why.
 * Also keeps the modified date for minor edits.
 *
 * @param array<string, mixed> $data    Sanitized post data.
 * @param array<string, mixed> $postarr Raw post data.
 * @return array<string, mixed>
 */
function tala_seo_guard_insert( $data, $postarr ) {
	$id = (int) ( $postarr['ID'] ?? 0 );

	if ( $id && ! empty( $GLOBALS['tala_seo_minor_edit'][ $id ] ) ) {
		$old = get_post( $id );
		if ( $old ) {
			$data['post_modified']     = $old->post_modified;
			$data['post_modified_gmt'] = $old->post_modified_gmt;
		}
	}

	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ! in_array( $data['post_status'], array( 'publish', 'future' ), true )
		|| ! in_array( $data['post_type'], tala_seo_post_types(), true )
		|| ( $id && in_array( get_post_status( $id ), array( 'publish', 'future' ), true ) ) ) {
		return $data;
	}

	$description = (string) ( $postarr['meta_input'][ TALA_SEO_DESCRIPTION ] ?? ( $id ? tala_seo_post_description( $id ) : '' ) );
	$thumbnail   = (int) ( $postarr['_thumbnail_id'] ?? ( $id ? get_post_thumbnail_id( $id ) : 0 ) );
	$problems    = tala_seo_publish_problems( $data['post_type'], $description, $thumbnail );
	if ( ! $problems ) {
		return $data;
	}

	$data['post_status'] = 'draft';
	/* translators: %s: list of things to fix. */
	$message = sprintf( __( 'Kept as a draft. %s', 'tala-seo' ), implode( ' ', $problems ) );
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::warning( $message );
	} elseif ( get_current_user_id() ) {
		set_transient( 'tala_seo_notice_' . get_current_user_id(), $message, 120 );
	}
	return $data;
}

/**
 * The minor-edit switch applies to one save only.
 *
 * @param WP_Post $post Saved post.
 */
function tala_seo_reset_minor_edit( $post ): void {
	unset( $GLOBALS['tala_seo_minor_edit'][ $post->ID ] );
	if ( get_post_meta( $post->ID, TALA_SEO_MINOR_EDIT, true ) ) {
		delete_post_meta( $post->ID, TALA_SEO_MINOR_EDIT );
	}
}

add_action( 'admin_notices', 'tala_seo_guard_notice' );

/**
 * Shows why a Quick Edit "Publish" was kept as a draft.
 */
function tala_seo_guard_notice(): void {
	$key     = 'tala_seo_notice_' . get_current_user_id();
	$message = get_transient( $key );
	if ( $message ) {
		delete_transient( $key );
		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
	}
}
