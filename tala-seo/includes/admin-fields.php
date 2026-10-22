<?php
/**
 * Admin forms for the archive SEO fields (terms.php): on the category and tag screens, and on
 * user profiles (author page, plus the author's Fediverse handle).
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', 'tala_seo_term_field_hooks' );

/**
 * Adds the fields to every taxonomy that has them.
 */
function tala_seo_term_field_hooks(): void {
	foreach ( tala_seo_term_taxonomies() as $taxonomy ) {
		add_action( "{$taxonomy}_add_form_fields", 'tala_seo_term_add_fields' );
		add_action( "{$taxonomy}_edit_form_fields", 'tala_seo_term_edit_fields' );
		add_action( "created_{$taxonomy}", 'tala_seo_save_term_fields' );
		add_action( "edited_{$taxonomy}", 'tala_seo_save_term_fields' );
	}
}

/**
 * Help texts shared by the add and edit forms.
 *
 * @return array{title:string,description:string,noindex:string}
 */
function tala_seo_term_field_help() {
	return array(
		'title'       => __( 'Leave empty to use the name. The site name is added at the end.', 'tala-seo' ),
		'description' => __( 'Shown by Google under the title. Leave empty to use the description above.', 'tala-seo' ),
		'noindex'     => __( 'Adds noindex and leaves this page out of the sitemap.', 'tala-seo' ),
	);
}

/**
 * Fields on the "Add New Category/Tag" form.
 */
function tala_seo_term_add_fields(): void {
	$help = tala_seo_term_field_help();
	wp_nonce_field( 'tala_seo_term', 'tala_seo_term_nonce' );
	?>
	<div class="form-field">
		<label for="tala-seo-title"><?php esc_html_e( 'SEO title', 'tala-seo' ); ?></label>
		<input type="text" id="tala-seo-title" name="tala_seo_title" value="" aria-describedby="tala-seo-title-help">
		<p id="tala-seo-title-help"><?php echo esc_html( $help['title'] ); ?></p>
	</div>
	<div class="form-field">
		<label for="tala-seo-description"><?php esc_html_e( 'Meta description', 'tala-seo' ); ?></label>
		<textarea id="tala-seo-description" name="tala_seo_description" rows="3" aria-describedby="tala-seo-description-help"></textarea>
		<p id="tala-seo-description-help"><?php echo esc_html( $help['description'] ); ?></p>
	</div>
	<div class="form-field">
		<label><input type="checkbox" name="tala_seo_noindex" value="1"> <?php esc_html_e( 'Hide this page from search engines', 'tala-seo' ); ?></label>
		<p><?php echo esc_html( $help['noindex'] ); ?></p>
	</div>
	<?php
}

/**
 * Fields on the "Edit Category/Tag" form.
 *
 * @param WP_Term $term Term being edited.
 */
function tala_seo_term_edit_fields( $term ): void {
	$help = tala_seo_term_field_help();
	// Values another SEO plugin left behind apply until `wp tala-seo import` copies them here.
	$legacy_title       = tala_seo_legacy_term_value( $term, 'title' );
	$legacy_description = tala_seo_legacy_term_value( $term, 'description' );
	$legacy_hidden      = '1' === tala_seo_legacy_term_value( $term, 'noindex' ) && ! get_term_meta( $term->term_id, TALA_SEO_NOINDEX, true );
	?>
	<tr class="form-field">
		<th scope="row"><label for="tala-seo-title"><?php esc_html_e( 'SEO title', 'tala-seo' ); ?></label></th>
		<td>
			<?php wp_nonce_field( 'tala_seo_term', 'tala_seo_term_nonce' ); ?>
			<input type="text" id="tala-seo-title" name="tala_seo_title" value="<?php echo esc_attr( (string) get_term_meta( $term->term_id, TALA_SEO_TITLE, true ) ); ?>" placeholder="<?php echo esc_attr( '' !== $legacy_title ? $legacy_title : $term->name ); ?>" aria-describedby="tala-seo-title-help">
			<p class="description" id="tala-seo-title-help"><?php echo esc_html( $help['title'] ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="tala-seo-description"><?php esc_html_e( 'Meta description', 'tala-seo' ); ?></label></th>
		<td>
			<textarea id="tala-seo-description" name="tala_seo_description" rows="3" placeholder="<?php echo esc_attr( $legacy_description ); ?>" aria-describedby="tala-seo-description-help"><?php echo esc_textarea( (string) get_term_meta( $term->term_id, TALA_SEO_DESCRIPTION, true ) ); ?></textarea>
			<p class="description" id="tala-seo-description-help"><?php echo esc_html( $help['description'] ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e( 'Search engines', 'tala-seo' ); ?></th>
		<td>
			<label><input type="checkbox" name="tala_seo_noindex" value="1" <?php checked( (bool) get_term_meta( $term->term_id, TALA_SEO_NOINDEX, true ) ); ?>> <?php esc_html_e( 'Hide this page from search engines', 'tala-seo' ); ?></label>
			<p class="description"><?php echo esc_html( $help['noindex'] ); ?></p>
			<?php if ( $legacy_hidden ) : ?>
				<p class="description"><?php esc_html_e( 'Hidden now by a setting from another SEO plugin. Run "wp tala-seo import" to manage it here.', 'tala-seo' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

add_action( 'admin_enqueue_scripts', 'tala_seo_term_form_script' );

/**
 * WordPress adds terms without reloading and then empties the text fields, but leaves checkboxes as
 * they were: untick "hide" after each add, so the next term isn't hidden by accident.
 *
 * @param string $hook Admin page.
 */
function tala_seo_term_form_script( $hook ): void {
	if ( 'edit-tags.php' !== $hook ) {
		return;
	}
	wp_add_inline_script(
		'admin-tags',
		"jQuery( document ).on( 'ajaxSuccess', function ( event, xhr, settings ) {
	if ( typeof settings.data === 'string' && settings.data.indexOf( 'action=add-tag' ) !== -1 && xhr.responseText.indexOf( '<wp_error' ) === -1 ) {
		jQuery( '#addtag input[name=\"tala_seo_noindex\"]' ).prop( 'checked', false );
	}
} );"
	);
}

/**
 * Saves the term fields. Quick Edit and other saves without the form's nonce leave them alone.
 *
 * @param int $term_id Term ID.
 */
function tala_seo_save_term_fields( $term_id ): void {
	if ( ! isset( $_POST['tala_seo_term_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tala_seo_term_nonce'] ) ), 'tala_seo_term' )
		|| ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}
	$values = array(
		TALA_SEO_TITLE       => tala_seo_clean_text( sanitize_text_field( wp_unslash( $_POST['tala_seo_title'] ?? '' ) ) ),
		TALA_SEO_DESCRIPTION => tala_seo_clean_text( sanitize_textarea_field( wp_unslash( $_POST['tala_seo_description'] ?? '' ) ) ),
		TALA_SEO_NOINDEX     => empty( $_POST['tala_seo_noindex'] ) ? '' : '1',
	);
	foreach ( $values as $key => $value ) {
		if ( '' === $value ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, $value );
		}
	}
}

/* ---- Author pages and the Fediverse handle, on user profiles ---- */

add_action( 'show_user_profile', 'tala_seo_author_fields', 11 );
add_action( 'edit_user_profile', 'tala_seo_author_fields', 11 );

/**
 * The "Author page in search results" section of the profile screen.
 *
 * @param WP_User $user User being edited.
 */
function tala_seo_author_fields( $user ): void {
	?>
	<h2><?php esc_html_e( 'Author page in search results', 'tala-seo' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="tala-seo-author-title"><?php esc_html_e( 'SEO title', 'tala-seo' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'tala_seo_author', 'tala_seo_author_nonce' ); ?>
				<input type="text" class="regular-text" id="tala-seo-author-title" name="tala_seo_author_title" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, TALA_SEO_TITLE, true ) ); ?>" placeholder="<?php echo esc_attr( $user->display_name ); ?>">
				<p class="description"><?php esc_html_e( 'Leave empty to use the display name.', 'tala-seo' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="tala-seo-author-description"><?php esc_html_e( 'Meta description', 'tala-seo' ); ?></label></th>
			<td>
				<textarea class="large-text" rows="3" id="tala-seo-author-description" name="tala_seo_author_description"><?php echo esc_textarea( (string) get_user_meta( $user->ID, TALA_SEO_DESCRIPTION, true ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Leave empty to use the Biographical Info.', 'tala-seo' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Search engines', 'tala-seo' ); ?></th>
			<td>
				<label><input type="checkbox" name="tala_seo_author_noindex" value="1" <?php checked( (bool) get_user_meta( $user->ID, TALA_SEO_NOINDEX, true ) ); ?>> <?php esc_html_e( 'Hide the author page from search engines', 'tala-seo' ); ?></label>
				<p class="description"><?php esc_html_e( 'Adds noindex and leaves the author page out of the sitemap. The articles themselves stay indexed.', 'tala-seo' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="tala-seo-fediverse"><?php esc_html_e( 'Fediverse handle', 'tala-seo' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="tala-seo-fediverse" name="tala_seo_fediverse" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, 'tala_seo_fediverse', true ) ); ?>" placeholder="@name@mastodon.social" autocomplete="off" spellcheck="false">
				<p class="description"><?php esc_html_e( 'Your handle on Mastodon or another Fediverse server. When your articles are shared there, they credit you (fediverse:creator). Put your profile address under Contact Info as well.', 'tala-seo' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

// After WordPress saved the profile, so a rejected profile (an invalid e-mail address or Fediverse
// handle) saves none of these either.
add_action( 'profile_update', 'tala_seo_save_author_fields' );

/**
 * Saves the author section.
 *
 * @param int $user_id User ID.
 */
function tala_seo_save_author_fields( $user_id ): void {
	if ( ! tala_seo_author_form_verified( $user_id ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in tala_seo_author_form_verified().
	$values = array(
		TALA_SEO_TITLE       => tala_seo_clean_text( sanitize_text_field( wp_unslash( $_POST['tala_seo_author_title'] ?? '' ) ) ),
		TALA_SEO_DESCRIPTION => tala_seo_clean_text( sanitize_textarea_field( wp_unslash( $_POST['tala_seo_author_description'] ?? '' ) ) ),
		TALA_SEO_NOINDEX     => empty( $_POST['tala_seo_author_noindex'] ) ? '' : '1',
		'tala_seo_fediverse' => tala_seo_sanitize_fediverse_handle( sanitize_text_field( wp_unslash( $_POST['tala_seo_fediverse'] ?? '' ) ) ),
	);
	// phpcs:enable
	foreach ( $values as $key => $value ) {
		if ( '' === $value ) {
			delete_user_meta( $user_id, $key );
		} else {
			update_user_meta( $user_id, $key, $value );
		}
	}
}

add_action( 'user_profile_update_errors', 'tala_seo_author_field_errors', 10, 3 );

/**
 * Reports a Fediverse handle that isn't in the "@name@server" form.
 *
 * @param WP_Error $errors Profile errors.
 * @param bool     $update Whether an existing user is updated.
 * @param stdClass $user   User data.
 */
function tala_seo_author_field_errors( $errors, $update, $user ): void {
	$user_id = isset( $user->ID ) ? (int) $user->ID : 0;
	if ( ! $update || ! tala_seo_author_form_verified( $user_id ) ) {
		return;
	}
	$raw = sanitize_text_field( wp_unslash( $_POST['tala_seo_fediverse'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
	if ( '' !== $raw && '' === tala_seo_sanitize_fediverse_handle( $raw ) ) {
		$errors->add( 'tala_seo_fediverse', __( '<strong>Error:</strong> The Fediverse handle should look like @name@mastodon.social.', 'tala-seo' ) );
	}
}

/**
 * Whether the author section was submitted with a valid nonce by someone who may edit this user.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function tala_seo_author_form_verified( $user_id ) {
	return $user_id > 0
		&& isset( $_POST['tala_seo_author_nonce'] )
		&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tala_seo_author_nonce'] ) ), 'tala_seo_author' )
		&& current_user_can( 'edit_user', $user_id );
}
