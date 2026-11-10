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
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id, $user_id ) {
					return user_can( $user_id, 'edit_post', $post_id );
				},
			)
		);
		add_filter( "rest_pre_insert_{$type}", 'tala_seo_rest_guard', 10, 2 );
		add_action( "rest_after_insert_{$type}", 'tala_seo_reset_minor_edit' );
	}
}

/**
 * Block-editor and REST saves: refuse to publish with a clear message the editor shows. A save
 * that passes is marked as checked for the insert that follows, because WordPress saves the post
 * before its description and featured image, so tala_seo_guard_insert() would see neither yet.
 *
 * @param stdClass|WP_Error $prepared Post about to be saved.
 * @param WP_REST_Request   $request  Request.
 * @return stdClass|WP_Error
 */
function tala_seo_rest_guard( $prepared, $request ) {
	$result                           = tala_seo_rest_check( $prepared, $request );
	$GLOBALS['tala_seo_rest_checked'] = ! is_wp_error( $result );
	return $result;
}

/**
 * Checks a REST save.
 *
 * @param stdClass|WP_Error $prepared Post about to be saved.
 * @param WP_REST_Request   $request  Request.
 * @return stdClass|WP_Error
 */
function tala_seo_rest_check( $prepared, $request ) {
	if ( is_wp_error( $prepared ) ) {
		return $prepared;
	}
	if ( str_contains( $request->get_route(), '/autosaves' ) ) {
		// An autosave never takes a post live: WordPress writes an author's own draft directly, so a
		// status sent with it would publish without the check below.
		if ( in_array( $prepared->post_status ?? '', array( 'publish', 'future' ), true ) ) {
			unset( $prepared->post_status );
		}
		return $prepared;
	}
	$id     = (int) ( $prepared->ID ?? 0 );
	$type   = $id ? get_post_type( $id ) : ( $prepared->post_type ?? '' );
	$denied = tala_seo_rest_check_targets( $request, $id, (string) $type );
	if ( $denied ) {
		return $denied;
	}
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
	// A live post is only stopped when this save breaks something, so old posts stay editable. What
	// a save sends along unchanged breaks nothing: the block editor sends every meta field when one
	// of them changes, so an old post without a description could otherwise save none of them.
	if ( $was_live ) {
		$touches_description = $touches_description && tala_seo_changes_description( $id, $meta[ TALA_SEO_DESCRIPTION ] );
		$touches_image       = $touches_image && (int) get_post_thumbnail_id( $id ) !== (int) $request['featured_media'];
	}
	if ( $was_live && ! $touches_description && ! $touches_image ) {
		return $prepared;
	}

	if ( $touches_description ) {
		// Only text counts: WordPress saves the post before it rejects meta of another type (a
		// number, a list), so a publish checked against "123" would go live with nothing stored.
		$description = is_string( $meta[ TALA_SEO_DESCRIPTION ] ) ? $meta[ TALA_SEO_DESCRIPTION ] : '';
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

/**
 * The redirect and canonical fields in a REST save by someone who may not set them
 * (tala_seo_can_set_target()). A save that changes one is refused here, before anything is written:
 * WordPress itself would save the post first and refuse the field afterwards. One sent along as it
 * is, as the block editor sends every meta field whenever one changes, is taken out of the request,
 * so the rest is saved; left in, WordPress would refuse a field that has no stored value yet.
 *
 * @param WP_REST_Request $request   Request.
 * @param int             $post_id   Post ID, or 0 for a new post.
 * @param string          $post_type Post type.
 * @return WP_Error|null
 */
function tala_seo_rest_check_targets( $request, $post_id, $post_type ) {
	$meta = $request->get_param( 'meta' );
	if ( ! is_array( $meta ) || tala_seo_can_set_target( get_current_user_id(), $post_id, $post_type ) ) {
		return null;
	}
	foreach ( array( TALA_SEO_REDIRECT, TALA_SEO_CANONICAL ) as $key ) {
		if ( ! array_key_exists( $key, $meta ) ) {
			continue;
		}
		$value  = $meta[ $key ] ?? '';
		$stored = $post_id ? (string) get_post_meta( $post_id, $key, true ) : '';
		if ( ! is_string( $value ) || ( $value !== $stored && tala_seo_sanitize_url( $value ) !== $stored ) ) {
			return new WP_Error(
				'tala_seo_cannot_set_target',
				__( 'Only people who can publish may set or change a redirect or a canonical URL. Ask an editor to add it.', 'tala-seo' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		unset( $meta[ $key ] );
	}
	$request->set_param( 'meta', $meta );
	return null;
}

/**
 * Whether a description sent with a save differs from the post's own. One that isn't text always
 * does: it is no description, whatever the post has.
 *
 * @param int   $post_id Post ID.
 * @param mixed $value   The description as sent.
 * @return bool
 */
function tala_seo_changes_description( $post_id, $value ) {
	$stored = (string) get_post_meta( $post_id, TALA_SEO_DESCRIPTION, true );
	return ! is_string( $value ) || ( $value !== $stored && tala_seo_sanitize_line( $value ) !== $stored );
}

add_filter( 'wp_insert_post_data', 'tala_seo_guard_insert', 20, 2 );

/**
 * Every other way of saving (Quick Edit, WP-CLI, plugins): the post isn't published and keeps the
 * status it had (a new one becomes a draft), and the person saving is told why. Also keeps the
 * modified date for minor edits.
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

	// A REST save was checked in tala_seo_rest_guard() just before this insert, also when a plugin or
	// WP-CLI runs one inside PHP (rest_do_request(), where REST_REQUEST isn't defined).
	$rest_checked = ! empty( $GLOBALS['tala_seo_rest_checked'] );
	unset( $GLOBALS['tala_seo_rest_checked'] );

	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || $rest_checked
		|| ! in_array( $data['post_status'], array( 'publish', 'future' ), true )
		|| ! in_array( $data['post_type'], tala_seo_post_types(), true )
		|| ( $id && in_array( get_post_status( $id ), array( 'publish', 'future' ), true ) ) ) {
		return $data;
	}

	$given       = $postarr['meta_input'][ TALA_SEO_DESCRIPTION ] ?? null;
	$description = null === $given ? ( $id ? tala_seo_post_description( $id ) : '' ) : tala_seo_scalar( $given );
	$thumbnail   = (int) ( $postarr['_thumbnail_id'] ?? ( $id ? get_post_thumbnail_id( $id ) : 0 ) );
	$problems    = tala_seo_publish_problems( $data['post_type'], $description, $thumbnail );
	if ( ! $problems ) {
		return $data;
	}

	// The post stays where it was: one waiting for review keeps its place in the queue, and a private
	// one stays private. A new post, or one taken out of the trash, becomes a draft.
	$stored              = $id ? (string) get_post_status( $id ) : '';
	$data['post_status'] = in_array( $stored, array( '', 'auto-draft', 'trash', 'inherit', 'new' ), true ) ? 'draft' : $stored;

	// WordPress dated the post when it was asked to publish it, before this filter ran. A draft or a
	// post waiting for review has an open date, set when it goes live; kept, the date of this try
	// would become its publish date whenever it is published later. A date the author gave stays
	// (the same test WordPress makes: no GMT date was passed in).
	$date_given = (string) ( $postarr['post_date_gmt'] ?? '' );
	if ( in_array( $date_given, array( '', '0000-00-00 00:00:00' ), true )
		&& in_array( $data['post_status'], get_post_stati( array( 'date_floating' => true ) ), true ) ) {
		$data['post_date_gmt'] = '0000-00-00 00:00:00';
	}

	$message = sprintf( tala_seo_guard_kept_as( $data['post_status'] ), implode( ' ', $problems ) );
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::warning( $message );
	} elseif ( get_current_user_id() ) {
		set_transient( 'tala_seo_notice_' . get_current_user_id(), $message, 120 );
	}
	return $data;
}

/**
 * What a refused publish says about the status the post keeps, with %s for the things to fix.
 *
 * @param string $status The status the post keeps.
 * @return string
 */
function tala_seo_guard_kept_as( $status ) {
	return match ( $status ) {
		/* translators: %s: list of things to fix. */
		'draft'   => __( 'Kept as a draft. %s', 'tala-seo' ),
		/* translators: %s: list of things to fix. */
		'pending' => __( 'Kept as pending review. %s', 'tala-seo' ),
		/* translators: %s: list of things to fix. */
		'private' => __( 'Kept private. %s', 'tala-seo' ),
		/* translators: %s: list of things to fix. */
		default   => __( 'Not published. %s', 'tala-seo' ),
	};
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
 * Shows why a Quick Edit "Publish" wasn't published.
 */
function tala_seo_guard_notice(): void {
	$key     = 'tala_seo_notice_' . get_current_user_id();
	$message = get_transient( $key );
	if ( $message ) {
		delete_transient( $key );
		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
	}
}
