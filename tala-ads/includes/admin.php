<?php
/**
 * Settings → Ads.
 *
 * @package TalaAds
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'tala_ads_admin_menu' );

/**
 * Adds Settings → Ads.
 */
function tala_ads_admin_menu(): void {
	add_options_page( __( 'Ads', 'tala-ads' ), __( 'Ads', 'tala-ads' ), 'manage_options', 'tala-ads', 'tala_ads_settings_page' );
}

add_filter( 'plugin_action_links_' . plugin_basename( TALA_ADS_FILE ), 'tala_ads_action_links' );

/**
 * A "Settings" link under the plugin's name in Plugins.
 *
 * @param string[] $links Action links.
 * @return string[]
 */
function tala_ads_action_links( $links ) {
	array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=tala-ads' ) ), esc_html__( 'Settings', 'tala-ads' ) ) );
	return $links;
}

add_action( 'admin_init', 'tala_ads_settings_fields' );

/**
 * The form's sections and fields, through the Settings API (the option is registered on init).
 */
function tala_ads_settings_fields(): void {
	add_settings_section( 'tala_ads_adsense', __( 'Google AdSense', 'tala-ads' ), 'tala_ads_section_adsense', 'tala-ads' );
	add_settings_field(
		'tala_ads_adsense',
		__( 'Publisher ID', 'tala-ads' ),
		'tala_ads_field_text',
		'tala-ads',
		'tala_ads_adsense',
		array(
			'label_for'   => 'tala-ads-adsense',
			'key'         => 'adsense',
			'placeholder' => 'ca-pub-0000000000000000',
			'help'        => __( 'Paste the ID, or any code AdSense gives you (the Auto ads code, an ads.txt line): only the publisher ID is kept.', 'tala-ads' ),
		)
	);
	add_settings_field( 'tala_ads_consent', __( 'Google\'s messages', 'tala-ads' ), 'tala_ads_field_consent', 'tala-ads', 'tala_ads_adsense' );
	add_settings_field( 'tala_ads_error_protection', __( 'Error protection', 'tala-ads' ), 'tala_ads_field_error_protection', 'tala-ads', 'tala_ads_adsense', array( 'label_for' => 'tala-ads-error-protection' ) );

	add_settings_section( 'tala_ads_gtm', __( 'Google Tag Manager', 'tala-ads' ), '__return_null', 'tala-ads' );
	add_settings_field(
		'tala_ads_gtm',
		__( 'Container ID', 'tala-ads' ),
		'tala_ads_field_text',
		'tala-ads',
		'tala_ads_gtm',
		array(
			'label_for'   => 'tala-ads-gtm',
			'key'         => 'gtm',
			'placeholder' => 'GTM-XXXXXXX',
			'help'        => __( 'Paste the ID or Tag Manager\'s code: only the container ID is kept. The container then loads Google Analytics and your other tags.', 'tala-ads' ),
		)
	);

	add_settings_section( 'tala_ads_search', __( 'Google search', 'tala-ads' ), 'tala_ads_section_search', 'tala-ads' );
	add_settings_field(
		'tala_ads_cse',
		__( 'Search engine ID', 'tala-ads' ),
		'tala_ads_field_text',
		'tala-ads',
		'tala_ads_search',
		array(
			'label_for'   => 'tala-ads-cse',
			'key'         => 'cse',
			'placeholder' => '0123456789abcdef0',
			'help'        => __( 'The "cx" of your engine in Programmable Search Engine (programmablesearchengine.google.com). Paste the ID or Google\'s code: only the ID is kept.', 'tala-ads' ),
		)
	);
	add_settings_field( 'tala_ads_search_page', __( 'Results page', 'tala-ads' ), 'tala_ads_field_search_page', 'tala-ads', 'tala_ads_search', array( 'label_for' => 'tala-ads-search-page' ) );

	add_settings_section( 'tala_ads_innity', __( 'Innity', 'tala-ads' ), 'tala_ads_section_innity', 'tala-ads' );
	$innity = array(
		'innity_key'     => array( __( 'Publisher key', 'tala-ads' ), '0123456789abcdef0123456789abcdef', __( 'The 32 letters and digits that come first in innity_adZoneAsync.display( … ). Pasting the whole zone tag works too.', 'tala-ads' ) ),
		'innity_zone'    => array( __( 'Zone ID', 'tala-ads' ), '100000', __( 'The number that comes second, for a 300 × 250 zone. Pasting the whole zone tag works too.', 'tala-ads' ) ),
		'innity_channel' => array( __( 'Channels', 'tala-ads' ), 'TECHNOLOGY,LIFESTYLE', __( 'Optional: the topics after "channel" in the zone tag, separated by commas.', 'tala-ads' ) ),
	);
	foreach ( $innity as $key => list( $label, $placeholder, $help ) ) {
		add_settings_field(
			'tala_ads_' . $key,
			$label,
			'tala_ads_field_text',
			'tala-ads',
			'tala_ads_innity',
			array(
				'label_for'   => 'tala-ads-' . str_replace( '_', '-', $key ),
				'key'         => $key,
				'placeholder' => $placeholder,
				'help'        => $help,
			)
		);
	}
}

/**
 * The AdSense section's introduction, and whether ads.txt lists the publisher ID.
 */
function tala_ads_section_adsense(): void {
	echo '<p>' . esc_html__( 'Tala Ads adds the AdSense code to every page except error pages and searches that found nothing. Google then places the ads: choose the formats (in-page, anchor, vignette, multiplex) and how many under Ads → By site in AdSense.', 'tala-ads' ) . '</p>';
	$status = tala_ads_ads_txt_status( tala_ads_settings()['adsense'] );
	if ( $status ) {
		printf( '<p><span aria-hidden="true" style="color:%s">%s</span> %s</p>', esc_attr( $status['ok'] ? '#00a32a' : '#d63638' ), $status['ok'] ? '✓' : '✕', esc_html( $status['text'] ) );
	}
}

/**
 * The Google search section's introduction.
 */
function tala_ads_section_search(): void {
	echo '<p>' . esc_html__( 'With an engine ID and a results page, Google answers every search on the site: WordPress\'s search forms send their searches to the results page, where the page\'s Search block (or, without one, the end of the page) shows Google\'s search box and results. Results for a search get noindex, as Google asks for internal search results.', 'tala-ads' ) . '</p>';
}

/**
 * Field: the results page for Google search.
 */
function tala_ads_field_search_page(): void {
	$select = wp_dropdown_pages(
		array(
			'name'              => 'tala_ads[search_page]',
			'id'                => 'tala-ads-search-page',
			'selected'          => tala_ads_settings()['search_page'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- an integer.
			'show_option_none'  => esc_html__( '— None (WordPress search) —', 'tala-ads' ),
			'option_none_value' => '0',
			'echo'              => false,
		)
	);
	if ( '' === $select ) {
		echo '<p>' . esc_html__( 'Publish a page first, for example "Search", with a Search block where the results should appear.', 'tala-ads' ) . '</p>';
		return;
	}
	echo $select; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() escapes its output.
	echo '<p class="description">' . esc_html__( 'A published page, for example "Search", with a Search block where the results should appear.', 'tala-ads' ) . '</p>';
}

/**
 * The Innity section's introduction.
 */
function tala_ads_section_innity(): void {
	echo '<p>' . esc_html__( 'An Innity box (300 × 250) after the text of each post and page, with its space kept free while the ad loads so the page doesn\'t move. Fill in the values from the zone tag Innity gave you; leave them empty for no box. Innity\'s universal tags (full-screen phone formats) aren\'t offered: they shift the page as it loads.', 'tala-ads' ) . '</p>';
}

/**
 * Whether ads.txt in WordPress's folder lists the publisher ID, or null without an ID.
 *
 * @param string $adsense Publisher ID ("ca-pub-…").
 * @return array{ok:bool,text:string}|null
 */
function tala_ads_ads_txt_status( $adsense ) {
	if ( '' === $adsense ) {
		return null;
	}
	$publisher = substr( $adsense, 3 );
	$url       = home_url( '/ads.txt' );
	if ( ! is_readable( ABSPATH . 'ads.txt' ) ) {
		return array(
			'ok'   => false,
			/* translators: %s: address of the site's ads.txt. */
			'text' => sprintf( __( 'There is no ads.txt in WordPress\'s folder. AdSense needs %s to list your publisher ID (AdSense shows the line under Sites). If your server provides ads.txt another way, ignore this.', 'tala-ads' ), $url ),
		);
	}
	$listed = str_contains( (string) file_get_contents( ABSPATH . 'ads.txt' ), $publisher );
	return array(
		'ok'   => $listed,
		'text' => $listed
			/* translators: %s: address of the site's ads.txt. */
			? sprintf( __( '%s lists your publisher ID.', 'tala-ads' ), $url )
			/* translators: 1: address of the site's ads.txt, 2: publisher ID. */
			: sprintf( __( '%1$s doesn\'t list %2$s: add the line AdSense shows under Sites, or AdSense may hold back ads.', 'tala-ads' ), $url, $publisher ),
	);
}

/**
 * Field: one setting as a line of text.
 *
 * @param array{label_for:string,key:string,placeholder:string,help:string} $args Field arguments.
 */
function tala_ads_field_text( $args ): void {
	$settings = tala_ads_settings();
	printf(
		'<input type="text" class="regular-text code" id="%1$s" name="tala_ads[%2$s]" value="%3$s" placeholder="%4$s" autocomplete="off" spellcheck="false" aria-describedby="%1$s-help"><p class="description" id="%1$s-help">%5$s</p>',
		esc_attr( $args['label_for'] ),
		esc_attr( $args['key'] ),
		esc_attr( (string) ( $settings[ $args['key'] ] ?? '' ) ),
		esc_attr( $args['placeholder'] ),
		esc_html( $args['help'] )
	);
}

/**
 * Field: Google's messages (consent and ad blocking recovery).
 */
function tala_ads_field_consent(): void {
	printf(
		'<label><input type="checkbox" name="tala_ads[consent]" value="1" aria-describedby="tala-ads-consent-help" %s> %s</label><p class="description" id="tala-ads-consent-help">%s</p>',
		checked( tala_ads_settings()['consent'], true, false ),
		esc_html__( 'Load Google\'s consent and ad blocking recovery messages', 'tala-ads' ),
		esc_html__( 'The messages you set up under Privacy & messaging in AdSense, which come from one Google tag: the consent message, which Google needs to show personalized ads to visitors from the European Economic Area, the UK and Switzerland, and the ad blocking recovery message.', 'tala-ads' )
	);
}

/**
 * Field: Google's error protection code for the ad blocking recovery message.
 */
function tala_ads_field_error_protection(): void {
	$code = tala_ads_settings()['error_protection'];
	$can  = current_user_can( 'unfiltered_html' );
	printf(
		'<textarea class="large-text code" rows="4" id="tala-ads-error-protection" name="tala_ads[error_protection]" spellcheck="false" aria-describedby="tala-ads-error-protection-help"%s>%s</textarea><p class="description" id="tala-ads-error-protection-help">%s</p>',
		$can ? '' : ' readonly',
		esc_textarea( $code ),
		esc_html(
			$can
				? __( 'Optional. When an ad blocker stops the recovery message itself, this asks the reader to allow ads. In AdSense, under Privacy & messaging → Ad blocking recovery → Tagging per site, copy the "Error protection message" code and paste it here whole. It is kept only as Google gives it, for this site\'s publisher ID, and goes on pages with ads while Google\'s messages are on.', 'tala-ads' )
				: __( 'Only people who may add scripts to this site can change this code.', 'tala-ads' )
		)
	);
	if ( '' !== $code ) {
		printf(
			'<p><span aria-hidden="true" style="color:#00a32a">✓</span> %s</p>',
			/* translators: %s: size of the code in kilobytes. */
			esc_html( sprintf( __( 'Google\'s error protection code is saved (%s KB).', 'tala-ads' ), number_format_i18n( strlen( $code ) / 1024, 1 ) ) )
		);
	}
}

/**
 * Renders Settings → Ads.
 */
function tala_ads_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Ads', 'tala-ads' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'tala_ads' );
			do_settings_sections( 'tala-ads' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}
