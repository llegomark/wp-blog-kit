<?php
/**
 * Admin: editor assets, Settings → SEO, and the site readiness checklist.
 *
 * @package TalaSEO
 */

defined( 'ABSPATH' ) || exit;

add_action( 'enqueue_block_editor_assets', 'tala_seo_editor_assets' );

/**
 * Loads the SEO panel into the post editor (not the Site Editor).
 */
function tala_seo_editor_assets(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, tala_seo_post_types(), true ) ) {
		return;
	}
	$base = plugins_url( 'assets/', TALA_SEO_FILE );
	wp_enqueue_script(
		'tala-seo-editor',
		$base . 'editor.js',
		array( 'wp-components', 'wp-core-data', 'wp-data', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins', 'wp-block-editor' ),
		TALA_SEO_VERSION,
		true
	);
	wp_enqueue_style( 'tala-seo-editor', $base . 'editor.css', array(), TALA_SEO_VERSION );

	$requirements = array();
	foreach ( tala_seo_post_types() as $type ) {
		$requirements[ $type ] = tala_seo_publish_requirements( $type );
	}
	$config = array(
		'keys'            => array(
			'title'       => TALA_SEO_TITLE,
			'description' => TALA_SEO_DESCRIPTION,
			'canonical'   => TALA_SEO_CANONICAL,
			'noindex'     => TALA_SEO_NOINDEX,
			'minorEdit'   => TALA_SEO_MINOR_EDIT,
		),
		'requirements'    => $requirements,
		'siteName'        => tala_seo_site_name(),
		'separator'       => '|',
		'defaultCategory' => (int) get_option( 'default_category' ),
		'sitePublic'      => (bool) get_option( 'blog_public' ),
	);
	wp_add_inline_script( 'tala-seo-editor', 'window.talaSeo = ' . wp_json_encode( $config ) . ';', 'before' );
	wp_set_script_translations( 'tala-seo-editor', 'tala-seo' );
}

add_action( 'admin_menu', 'tala_seo_admin_menu' );

/**
 * Adds Settings → SEO.
 */
function tala_seo_admin_menu(): void {
	add_options_page( __( 'SEO', 'tala-seo' ), __( 'SEO', 'tala-seo' ), 'manage_options', 'tala-seo', 'tala_seo_settings_page' );
}

/**
 * Site-wide SEO setup checks.
 *
 * @return array<int,array{ok:bool,text:string,link:string}>
 */
function tala_seo_readiness() {
	$checks = array();

	$public   = (bool) get_option( 'blog_public' );
	$checks[] = array(
		'ok'   => $public,
		'text' => $public ? __( 'Search engines may index the site.', 'tala-seo' ) : __( 'Search engines are blocked. Untick "Discourage search engines from indexing this site".', 'tala-seo' ),
		'link' => admin_url( 'options-reading.php' ),
	);

	$pretty   = '' !== (string) get_option( 'permalink_structure' );
	$checks[] = array(
		'ok'   => $pretty,
		'text' => $pretty ? __( 'Readable URLs are on.', 'tala-seo' ) : __( 'Turn on readable URLs: choose "Post name".', 'tala-seo' ),
		'link' => admin_url( 'options-permalink.php' ),
	);

	$icon     = (int) get_option( 'site_icon' );
	$checks[] = array(
		'ok'   => $icon > 0,
		'text' => $icon ? __( 'Site icon (favicon) is set.', 'tala-seo' ) : __( 'Add a site icon (favicon): a square PNG of at least 512 × 512 px.', 'tala-seo' ),
		'link' => admin_url( 'options-general.php' ),
	);

	$logo     = tala_seo_logo();
	$logo_ok  = $logo && min( $logo['width'], $logo['height'] ) >= 112;
	$checks[] = array(
		'ok'   => $logo_ok,
		'text' => $logo_ok ? __( 'Logo for search results is at least 112 × 112 px.', 'tala-seo' ) : __( 'Add a site logo or site icon of at least 112 × 112 px (Google shows it for your organization).', 'tala-seo' ),
		'link' => admin_url( 'options-general.php' ),
	);

	$tagline  = '' !== trim( (string) get_bloginfo( 'description' ) );
	$checks[] = array(
		'ok'   => $tagline,
		'text' => $tagline ? __( 'Tagline is set (it is the home page heading).', 'tala-seo' ) : __( 'Add a tagline: it becomes the home page heading and description.', 'tala-seo' ),
		'link' => admin_url( 'options-general.php' ),
	);

	$missing = array();
	foreach ( get_users(
		array(
			'has_published_posts' => array( 'post' ),
			'fields'              => array( 'ID', 'display_name' ),
		)
	) as $user ) {
		if ( '' === trim( (string) get_the_author_meta( 'description', $user->ID ) ) ) {
			$missing[] = $user->display_name;
		}
	}
	$checks[] = array(
		'ok'   => ! $missing,
		'text' => $missing
			/* translators: %s: author names. */
			? sprintf( __( 'Add a biography (Users → Profile → Biographical Info) for: %s.', 'tala-seo' ), implode( ', ', $missing ) )
			: __( 'Every author has a biography.', 'tala-seo' ),
		'link' => admin_url( 'users.php' ),
	);

	return $checks;
}

add_action( 'admin_init', 'tala_seo_settings_fields' );

/**
 * Settings → SEO fields, through the Settings API (the option itself is registered on init).
 */
function tala_seo_settings_fields(): void {
	add_settings_section(
		'tala_seo_publisher',
		__( 'Site owner', 'tala-seo' ),
		static function () {
			echo '<p>' . esc_html__( 'Shown to Google on the home page as the organization or person behind the site.', 'tala-seo' ) . '</p>';
		},
		'tala-seo'
	);
	add_settings_field( 'tala_seo_publisher_type', __( 'Who runs this site', 'tala-seo' ), 'tala_seo_field_publisher_type', 'tala-seo', 'tala_seo_publisher' );
	add_settings_field( 'tala_seo_publisher_name', __( 'Name', 'tala-seo' ), 'tala_seo_field_publisher_name', 'tala-seo', 'tala_seo_publisher', array( 'label_for' => 'tala-seo-publisher-name' ) );
	add_settings_field( 'tala_seo_same_as', __( 'Official profiles', 'tala-seo' ), 'tala_seo_field_same_as', 'tala-seo', 'tala_seo_publisher', array( 'label_for' => 'tala-seo-same-as' ) );

	add_settings_section( 'tala_seo_sharing', __( 'Home page and sharing', 'tala-seo' ), '__return_null', 'tala-seo' );
	add_settings_field( 'tala_seo_home_description', __( 'Home page description', 'tala-seo' ), 'tala_seo_field_home_description', 'tala-seo', 'tala_seo_sharing', array( 'label_for' => 'tala-seo-home-description' ) );
	add_settings_field( 'tala_seo_default_image', __( 'Default share image', 'tala-seo' ), 'tala_seo_field_default_image', 'tala-seo', 'tala_seo_sharing' );

	add_settings_section( 'tala_seo_search', __( 'Search results', 'tala-seo' ), '__return_null', 'tala-seo' );
	add_settings_field( 'tala_seo_tag_min_posts', __( 'Tag pages', 'tala-seo' ), 'tala_seo_field_tag_min_posts', 'tala-seo', 'tala_seo_search', array( 'label_for' => 'tala-seo-tag-min-posts' ) );
}

/**
 * Field: organization or person.
 */
function tala_seo_field_publisher_type(): void {
	$type = tala_seo_settings()['publisher_type'];
	?>
	<fieldset>
		<label><input type="radio" name="tala_seo[publisher_type]" value="organization" <?php checked( 'person' !== $type ); ?>> <?php esc_html_e( 'An organization or brand', 'tala-seo' ); ?></label><br>
		<label><input type="radio" name="tala_seo[publisher_type]" value="person" <?php checked( 'person' === $type ); ?>> <?php esc_html_e( 'A person', 'tala-seo' ); ?></label>
	</fieldset>
	<?php
}

/**
 * Field: publisher name.
 */
function tala_seo_field_publisher_name(): void {
	printf(
		'<input type="text" class="regular-text" id="tala-seo-publisher-name" name="tala_seo[publisher_name]" value="%s" placeholder="%s"><p class="description">%s</p>',
		esc_attr( tala_seo_settings()['publisher_name'] ),
		esc_attr( tala_seo_site_name() ),
		esc_html__( 'Leave empty to use the site title. Google expects the same name as the site name.', 'tala-seo' )
	);
}

/**
 * Field: official profile URLs (sameAs).
 */
function tala_seo_field_same_as(): void {
	printf(
		'<textarea class="large-text" rows="4" id="tala-seo-same-as" name="tala_seo[same_as]" placeholder="https://www.facebook.com/yourpage">%s</textarea><p class="description">%s</p>',
		esc_textarea( implode( "\n", tala_seo_settings()['same_as'] ) ),
		esc_html__( 'One URL per line: Facebook page, YouTube channel and so on.', 'tala-seo' )
	);
}

/**
 * Field: home page meta description.
 */
function tala_seo_field_home_description(): void {
	printf(
		'<textarea class="large-text" rows="3" id="tala-seo-home-description" name="tala_seo[home_description]" placeholder="%s">%s</textarea><p class="description">%s</p>',
		esc_attr( get_bloginfo( 'description' ) ),
		esc_textarea( tala_seo_settings()['home_description'] ),
		esc_html__( 'Leave empty to use the tagline.', 'tala-seo' )
	);
}

/**
 * Field: default share image.
 */
function tala_seo_field_default_image(): void {
	$id    = (int) tala_seo_settings()['default_image'];
	$image = $id ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
	?>
	<img id="tala-seo-default-image-preview" src="<?php echo esc_url( $image ); ?>" alt="" style="max-width:300px;height:auto;display:block;margin-bottom:8px" <?php echo $image ? '' : 'hidden'; ?>>
	<input type="hidden" id="tala-seo-default-image" name="tala_seo[default_image]" value="<?php echo esc_attr( $id ? (string) $id : '' ); ?>">
	<button type="button" class="button" data-tala-media="tala-seo-default-image" data-preview="tala-seo-default-image-preview" data-title="<?php esc_attr_e( 'Default share image', 'tala-seo' ); ?>" data-button="<?php esc_attr_e( 'Use this image', 'tala-seo' ); ?>"><?php esc_html_e( 'Choose image', 'tala-seo' ); ?></button>
	<button type="button" class="button-link" data-tala-media-clear="tala-seo-default-image" data-preview="tala-seo-default-image-preview"><?php esc_html_e( 'Remove', 'tala-seo' ); ?></button>
	<p class="description"><?php esc_html_e( 'Shown when the home page or a page without a featured image is shared. Use a 1200 × 675 px picture, not the logo.', 'tala-seo' ); ?></p>
	<?php
}

/**
 * Field: minimum number of posts before a tag page is shown to search engines.
 */
function tala_seo_field_tag_min_posts(): void {
	printf(
		'<input type="number" class="small-text" id="tala-seo-tag-min-posts" name="tala_seo[tag_min_posts]" value="%d" min="0" max="100" step="1" aria-describedby="tala-seo-tag-min-posts-help"><p class="description" id="tala-seo-tag-min-posts-help">%s</p>',
		(int) tala_seo_settings()['tag_min_posts'],
		esc_html__( 'Minimum number of posts a tag page needs before search engines may index it. Smaller tag pages get noindex and stay out of the sitemap: a page that lists a single post adds nothing to search results. 0 or 1 lets every tag page be indexed.', 'tala-seo' )
	);
}

/**
 * Renders Settings → SEO.
 */
function tala_seo_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// get_sitemap_url() answers even while sitemaps are off, so ask the sitemap server first.
	$server  = wp_sitemaps_get_server();
	$sitemap = $server->sitemaps_enabled() ? (string) get_sitemap_url( 'index' ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'SEO', 'tala-seo' ); ?></h1>

		<h2><?php esc_html_e( 'Setup checklist', 'tala-seo' ); ?></h2>
		<ul>
			<?php foreach ( tala_seo_readiness() as $check ) : ?>
				<li>
					<span aria-hidden="true" style="color:<?php echo esc_attr( $check['ok'] ? '#00a32a' : '#d63638' ); ?>"><?php echo $check['ok'] ? '✓' : '✕'; ?></span>
					<?php echo esc_html( $check['text'] ); ?>
					<?php if ( ! $check['ok'] ) : ?>
						<a href="<?php echo esc_url( $check['link'] ); ?>"><?php esc_html_e( 'Fix', 'tala-seo' ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<p>
			<?php if ( '' !== $sitemap ) : ?>
				<?php esc_html_e( 'Sitemap:', 'tala-seo' ); ?> <a href="<?php echo esc_url( $sitemap ); ?>"><?php echo esc_html( $sitemap ); ?></a>.
				<?php esc_html_e( 'Submit it in Google Search Console. Verify the domain there with a DNS record in Cloudflare; no plugin is needed for that.', 'tala-seo' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'The sitemap is published once search engines may index the site.', 'tala-seo' ); ?>
			<?php endif; ?>
		</p>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'tala_seo' );
			do_settings_sections( 'tala-seo' );
			submit_button( __( 'Save changes', 'tala-seo' ) );
			?>
		</form>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', 'tala_seo_admin_assets' );

/**
 * Media picker for Settings → SEO and user profiles.
 *
 * @param string $hook Admin page.
 */
function tala_seo_admin_assets( $hook ): void {
	if ( ! in_array( $hook, array( 'settings_page_tala-seo', 'profile.php', 'user-edit.php' ), true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'tala-seo-admin', plugins_url( 'assets/admin.js', TALA_SEO_FILE ), array( 'media-editor' ), TALA_SEO_VERSION, true );
}

add_action( 'admin_notices', 'tala_seo_hidden_site_notice' );

/**
 * On every admin screen while the site is hidden from search engines (Settings → Reading): an
 * easy switch to forget at launch, and nothing appears in Google until it is turned back.
 * The block editor shows the same warning in its pre-publish checklist (editor.js).
 */
function tala_seo_hidden_site_notice(): void {
	$screen = get_current_screen();
	if ( get_option( 'blog_public' ) || ! current_user_can( 'manage_options' ) || ( $screen && 'options-reading' === $screen->id ) ) {
		return;
	}
	wp_admin_notice(
		sprintf(
			'<strong>%s</strong> %s <a href="%s">%s</a>',
			esc_html__( 'Search engines are blocked.', 'tala-seo' ),
			esc_html__( 'Nothing on this site can appear in Google. When the site is ready, untick "Discourage search engines from indexing this site".', 'tala-seo' ),
			esc_url( admin_url( 'options-reading.php' ) ),
			esc_html__( 'Open Reading settings', 'tala-seo' )
		),
		array(
			'type'               => 'error',
			'additional_classes' => array( 'tala-seo-hidden-site' ),
		)
	);
}

add_action( 'admin_notices', 'tala_seo_dashboard_notice' );

/**
 * On the dashboard, points out unfinished SEO setup.
 */
function tala_seo_dashboard_notice(): void {
	$screen = get_current_screen();
	if ( ! $screen || 'dashboard' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$todo = count( array_filter( tala_seo_readiness(), static fn( $c ) => ! $c['ok'] ) );
	if ( $todo ) {
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			/* translators: %d: number of unfinished items. */
			esc_html( sprintf( _n( 'SEO setup has %d item to finish.', 'SEO setup has %d items to finish.', $todo, 'tala-seo' ), $todo ) ),
			esc_url( admin_url( 'options-general.php?page=tala-seo' ) ),
			esc_html__( 'Open the checklist', 'tala-seo' )
		);
	}
}

/* ---- User profiles: social profile links and the author photo ---- */

add_filter( 'user_contactmethods', 'tala_seo_contact_methods' );

/**
 * Adds social profile fields to Users → Profile → Contact Info.
 *
 * @param array<string, string> $methods Contact methods.
 * @return array<string, string>
 */
function tala_seo_contact_methods( $methods ) {
	return array_merge( $methods, tala_seo_social_fields() );
}

add_action( 'show_user_profile', 'tala_seo_photo_field' );
add_action( 'edit_user_profile', 'tala_seo_photo_field' );

/**
 * The "Author photo" field on the profile screen.
 *
 * @param WP_User $user User being edited.
 */
function tala_seo_photo_field( $user ): void {
	$id  = (int) get_user_meta( $user->ID, 'tala_seo_photo', true );
	$src = $id ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
	?>
	<h2><?php esc_html_e( 'Author photo', 'tala-seo' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Photo', 'tala-seo' ); ?></th>
			<td>
				<img id="tala-seo-photo-preview" src="<?php echo esc_url( $src ); ?>" alt="" width="96" height="96" style="border-radius:50%;object-fit:cover;display:block;margin-bottom:8px" <?php echo $src ? '' : 'hidden'; ?>>
				<input type="hidden" id="tala-seo-photo" name="tala_seo_photo" value="<?php echo esc_attr( $id ? (string) $id : '' ); ?>">
				<button type="button" class="button" data-tala-media="tala-seo-photo" data-preview="tala-seo-photo-preview" data-title="<?php esc_attr_e( 'Author photo', 'tala-seo' ); ?>" data-button="<?php esc_attr_e( 'Use this photo', 'tala-seo' ); ?>"><?php esc_html_e( 'Choose photo', 'tala-seo' ); ?></button>
				<button type="button" class="button-link" data-tala-media-clear="tala-seo-photo" data-preview="tala-seo-photo-preview"><?php esc_html_e( 'Remove', 'tala-seo' ); ?></button>
				<p class="description"><?php esc_html_e( 'A real photo of the author: square, at least 300 × 300 px. Shown with their articles and on their author page instead of Gravatar.', 'tala-seo' ); ?></p>
				<?php wp_nonce_field( 'tala_seo_photo', 'tala_seo_photo_nonce' ); ?>
			</td>
		</tr>
	</table>
	<?php
}

add_action( 'personal_options_update', 'tala_seo_save_photo' );
add_action( 'edit_user_profile_update', 'tala_seo_save_photo' );

/**
 * Saves the author photo.
 *
 * @param int $user_id User ID.
 */
function tala_seo_save_photo( $user_id ): void {
	if ( ! current_user_can( 'edit_user', $user_id )
		|| ! isset( $_POST['tala_seo_photo_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tala_seo_photo_nonce'] ) ), 'tala_seo_photo' ) ) {
		return;
	}
	$id = absint( $_POST['tala_seo_photo'] ?? 0 );
	if ( $id && wp_attachment_is_image( $id ) ) {
		update_user_meta( $user_id, 'tala_seo_photo', $id );
	} else {
		delete_user_meta( $user_id, 'tala_seo_photo' );
	}
}
