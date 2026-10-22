<?php
/**
 * Author photos on the front end: the photo set in the profile replaces Gravatar, which also saves
 * a request to gravatar.com on every article. The profile fields themselves are in admin.php.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'pre_get_avatar_data', 'tala_seo_local_avatar', 10, 2 );

/**
 * Uses the author photo for avatars when one is set.
 *
 * @param array<string, mixed> $args        Avatar arguments.
 * @param mixed                $id_or_email User ID, email, WP_User, WP_Post or WP_Comment.
 * @return array<string, mixed>
 */
function tala_seo_local_avatar( $args, $id_or_email ) {
	// On an article, avatars sit below the text (author box, comments): load them lazily.
	if ( ! is_admin() && is_singular( 'post' ) ) {
		$args['loading'] = 'lazy';
	}
	$user_id = 0;
	if ( is_numeric( $id_or_email ) ) {
		$user_id = (int) $id_or_email;
	} elseif ( $id_or_email instanceof WP_User ) {
		$user_id = $id_or_email->ID;
	} elseif ( $id_or_email instanceof WP_Post ) {
		$user_id = (int) $id_or_email->post_author;
	} elseif ( $id_or_email instanceof WP_Comment ) {
		$user_id = (int) $id_or_email->user_id;
	} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$user    = get_user_by( 'email', $id_or_email );
		$user_id = $user ? $user->ID : 0;
	}
	$photo = $user_id ? (int) get_user_meta( $user_id, 'tala_seo_photo', true ) : 0;
	if ( ! $photo ) {
		return $args;
	}
	$size = max( 1, (int) ( $args['size'] ?? 96 ) );
	$src  = wp_get_attachment_image_src( $photo, $size > 150 ? 'medium' : 'thumbnail' );
	if ( $src ) {
		$args['url']          = $src[0];
		$args['found_avatar'] = true;
	}
	return $args;
}
