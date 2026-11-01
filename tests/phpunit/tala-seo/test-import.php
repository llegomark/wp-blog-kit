<?php
/**
 * Moving from another SEO plugin: its values are read until they are imported, and
 * `wp tala-seo import` copies them into Tala SEO's fields.
 *
 * @package TalaTests
 */

/**
 * Import.
 */
class Test_Tala_SEO_Import extends Tala_SEO_Test_Case {

	/**
	 * Clears what the command printed before.
	 */
	public function set_up(): void {
		parent::set_up();
		WP_CLI::reset();
		$this->set_pretty_permalinks();
	}

	/**
	 * Runs `wp tala-seo import`.
	 *
	 * @param bool                  $dry_run Whether to pass --dry-run.
	 * @param array<string, string> $options Other options, such as from.
	 * @return string The success message.
	 */
	private function import( bool $dry_run = false, array $options = array() ): string {
		WP_CLI::reset();
		( new Tala_SEO_CLI() )->import( array(), array_merge( $options, $dry_run ? array( 'dry-run' => true ) : array() ) );
		$success = array_values( array_filter( WP_CLI::$messages, static fn( $message ) => 'success' === $message[0] ) );
		return $success[0][1] ?? '';
	}

	/**
	 * A post by an author, carrying another plugin's values.
	 *
	 * @param array<string, mixed> $meta Post meta.
	 * @return int Post ID.
	 */
	private function legacy_post( array $meta ): int {
		return self::factory()->post->create(
			array(
				'post_author' => self::factory()->user->create( array( 'role' => 'author' ) ),
				'meta_input'  => $meta,
			)
		);
	}

	/**
	 * Posts: titles, descriptions, canonicals, noindex, permanent redirects and primary
	 * categories are copied; variables, temporary or members-only redirects and "always index"
	 * are not; Tala SEO's own values are never overwritten.
	 */
	public function test_posts(): void {
		update_option( 'autodescription-site-settings', array() ); // The SEO Framework ran on this site.
		$first  = self::factory()->category->create();
		$second = self::factory()->category->create();
		$tsf    = $this->legacy_post(
			array(
				'_genesis_title'         => 'TSF title',
				'_genesis_description'   => 'TSF description.',
				'_genesis_canonical_uri' => 'https://example.org/original/',
				'_genesis_noindex'       => '1',
				'redirect'               => 'https://example.org/tsf-target/',
				'_primary_term_category' => $second,
			)
		);
		wp_set_post_categories( $tsf, array( $first, $second ) );
		$seopress  = $this->legacy_post(
			array(
				'_seopress_titles_title'         => '%%post_title%% %%sep%% %%sitetitle%%',
				'_seopress_titles_desc'          => 'SEOPress description.',
				'_seopress_robots_index'         => 'yes',
				'_seopress_redirections_enabled' => 'yes',
				'_seopress_redirections_value'   => 'https://example.org/sp-target/',
				'_seopress_redirections_type'    => '301',
			)
		);
		$temporary = $this->legacy_post(
			array(
				'_seopress_redirections_enabled' => 'yes',
				'_seopress_redirections_value'   => 'https://example.org/temporary/',
				'_seopress_redirections_type'    => '302',
			)
		);
		$members   = $this->legacy_post(
			array(
				'_seopress_redirections_enabled'       => 'yes',
				'_seopress_redirections_value'         => 'https://example.org/members/',
				'_seopress_redirections_logged_status' => 'only_logged_in',
			)
		);
		$always    = $this->legacy_post( array( '_genesis_noindex' => '-1' ) );
		$own       = $this->legacy_post(
			array(
				TALA_SEO_TITLE   => 'Own title',
				'_genesis_title' => 'TSF title',
			)
		);

		$this->assertStringStartsWith( 'Dry run, nothing changed. Would copy', $this->import( true ) );
		$this->assertSame( '', get_post_meta( $tsf, TALA_SEO_TITLE, true ), 'A dry run changes nothing.' );

		$this->assertStringStartsWith( 'Copied', $this->import() );
		$this->assertSame( 'TSF title', get_post_meta( $tsf, TALA_SEO_TITLE, true ) );
		$this->assertSame( 'TSF description.', get_post_meta( $tsf, TALA_SEO_DESCRIPTION, true ) );
		$this->assertSame( 'https://example.org/original/', get_post_meta( $tsf, TALA_SEO_CANONICAL, true ) );
		$this->assertSame( '1', get_post_meta( $tsf, TALA_SEO_NOINDEX, true ) );
		$this->assertSame( 'https://example.org/tsf-target/', get_post_meta( $tsf, TALA_SEO_REDIRECT, true ) );
		$this->assertSame( (string) $second, (string) get_post_meta( $tsf, TALA_SEO_PRIMARY_CATEGORY, true ) );

		$this->assertSame( '', get_post_meta( $seopress, TALA_SEO_TITLE, true ), 'A title with variables is skipped.' );
		$this->assertSame( 'SEOPress description.', get_post_meta( $seopress, TALA_SEO_DESCRIPTION, true ) );
		$this->assertSame( '1', get_post_meta( $seopress, TALA_SEO_NOINDEX, true ) );
		$this->assertSame( 'https://example.org/sp-target/', get_post_meta( $seopress, TALA_SEO_REDIRECT, true ) );
		$this->assertSame( '', get_post_meta( $temporary, TALA_SEO_REDIRECT, true ), '302 is not imported.' );
		$this->assertSame( '', get_post_meta( $members, TALA_SEO_REDIRECT, true ), 'Members-only is not imported.' );
		$this->assertFalse( metadata_exists( 'post', $always, TALA_SEO_NOINDEX ), '"Always index" is not noindex.' );
		$this->assertSame( 'Own title', get_post_meta( $own, TALA_SEO_TITLE, true ), 'Never overwritten' );

		$this->assertSame( 'Copied 0 value(s).', $this->import(), 'A second run copies nothing.' );
	}

	/**
	 * The plain "redirect" key is a custom field anyone editing a post can add, unseen in Tala SEO's
	 * panel: a contributor's goes live only if its author could set a redirect on the published post,
	 * which a contributor can't. Every redirect copied or skipped is listed with its target.
	 */
	public function test_redirect_from_a_custom_field_needs_a_trusted_author(): void {
		update_option( 'autodescription-site-settings', array() );
		$contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$author      = self::factory()->user->create( array( 'role' => 'author' ) );
		$theirs      = self::factory()->post->create(
			array(
				'post_author' => $contributor,
				'meta_input'  => array( 'redirect' => 'https://example.org/contributor/' ),
			)
		);
		$own         = self::factory()->post->create(
			array(
				'post_author' => $author,
				'meta_input'  => array( 'redirect' => 'https://example.org/author/' ),
			)
		);
		$seopress    = static fn( string $target ): array => array(
			'_seopress_redirections_enabled' => 'yes',
			'_seopress_redirections_type'    => '301',
			'_seopress_redirections_value'   => $target,
		);
		$planted     = self::factory()->post->create(
			array(
				'post_author' => $contributor,
				'meta_input'  => $seopress( 'https://example.org/planted/' ),
			)
		);
		$reviewed    = self::factory()->post->create(
			array(
				'post_author' => $author,
				'meta_input'  => $seopress( 'https://example.org/reviewed/' ),
			)
		);

		$this->import();
		$this->assertSame( '', get_post_meta( $theirs, TALA_SEO_REDIRECT, true ), 'Not from a contributor' );
		$this->assertSame( 'https://example.org/author/', get_post_meta( $own, TALA_SEO_REDIRECT, true ) );
		$this->assertSame( '', get_post_meta( $planted, TALA_SEO_REDIRECT, true ), "SEOPress's field on a contributor's post: the same gate" );
		$this->assertSame( 'https://example.org/reviewed/', get_post_meta( $reviewed, TALA_SEO_REDIRECT, true ) );
		$printed = implode( "\n", array_column( WP_CLI::$messages, 1 ) );
		$this->assertStringContainsString( "Post $theirs", $printed );
		$this->assertStringContainsString( 'https://example.org/contributor/', $printed, 'The skipped target is named.' );
		$this->assertStringContainsString( 'https://example.org/author/', $printed, 'The copied target is named.' );
	}

	/**
	 * Categories, tags and authors.
	 */
	public function test_terms_and_authors(): void {
		$tsf = self::factory()->category->create();
		update_term_meta(
			$tsf,
			'autodescription-term-settings',
			array(
				'doctitle'    => 'TSF category',
				'description' => 'TSF category description.',
				'noindex'     => 1,
			)
		);
		$seopress = self::factory()->category->create();
		update_term_meta( $seopress, '_seopress_titles_title', 'SEOPress category' );
		update_term_meta( $seopress, '_seopress_robots_index', 'yes' );
		$always = self::factory()->category->create();
		update_term_meta( $always, 'autodescription-term-settings', array( 'noindex' => -1 ) );

		$tsf_author = self::factory()->user->create();
		update_user_meta(
			$tsf_author,
			'autodescription-user-settings',
			array(
				'doctitle'           => 'TSF author',
				'noindex'            => 1,
				'fediverse_page'     => '@tsf@example.social',
				'fediverse_page_url' => 'https://example.social/@tsf',
			)
		);
		$yoast_author = self::factory()->user->create();
		update_user_meta( $yoast_author, 'wpseo_title', 'Yoast author' );
		update_user_meta( $yoast_author, 'wpseo_noindex_author', 'on' );

		$this->import();
		$this->assertSame( 'TSF category', get_term_meta( $tsf, TALA_SEO_TITLE, true ) );
		$this->assertSame( 'TSF category description.', get_term_meta( $tsf, TALA_SEO_DESCRIPTION, true ) );
		$this->assertSame( '1', get_term_meta( $tsf, TALA_SEO_NOINDEX, true ) );
		$this->assertSame( 'SEOPress category', get_term_meta( $seopress, TALA_SEO_TITLE, true ) );
		$this->assertSame( '1', get_term_meta( $seopress, TALA_SEO_NOINDEX, true ) );
		$this->assertFalse( metadata_exists( 'term', $always, TALA_SEO_NOINDEX ), '"Always index" is not noindex.' );

		$this->assertSame( 'TSF author', get_user_meta( $tsf_author, TALA_SEO_TITLE, true ) );
		$this->assertSame( '1', get_user_meta( $tsf_author, TALA_SEO_NOINDEX, true ) );
		$this->assertSame( '@tsf@example.social', get_user_meta( $tsf_author, 'tala_seo_fediverse', true ) );
		$this->assertSame( 'https://example.social/@tsf', get_user_meta( $tsf_author, 'tala_fediverse_url', true ) );
		$this->assertSame( 'Yoast author', get_user_meta( $yoast_author, TALA_SEO_TITLE, true ) );
		$this->assertSame( '1', get_user_meta( $yoast_author, TALA_SEO_NOINDEX, true ) );
	}

	/**
	 * Until the import, the other plugin's values are used on the site.
	 */
	public function test_values_are_used_before_the_import(): void {
		update_option( 'blogname', 'Guro Notes' );
		$category = self::factory()->category->create();
		update_term_meta( $category, 'autodescription-term-settings', array( 'doctitle' => 'TSF category' ) );
		$this->publish_post( array( 'post_category' => array( $category ) ) );
		$this->go_to( get_term_link( $category ) );
		$this->assertSame( 'TSF category | Guro Notes', wp_get_document_title() );

		// A post published while Yoast SEO was in use.
		$post = $this->publish_post();
		delete_post_meta( $post, TALA_SEO_DESCRIPTION );
		update_post_meta( $post, '_yoast_wpseo_metadesc', 'A description from Yoast SEO.' );
		update_post_meta( $post, '_yoast_wpseo_canonical', 'https://example.org/original/' );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( 'A description from Yoast SEO.', $this->meta( 'description' ) );
		$this->assertSame( 'https://example.org/original/', $this->canonical() );
	}

	/**
	 * `wp tala-seo audit` lists the published posts that miss what the publish check requires.
	 */
	public function test_audit(): void {
		$ready = $this->publish_post();
		$old   = $this->publish_post();
		delete_post_meta( $old, TALA_SEO_DESCRIPTION ); // Published before Tala SEO.
		( new Tala_SEO_CLI() )->audit( array(), array() );
		$rows = WP_CLI::$tables[0]['items'] ?? array();
		$this->assertSame( array( $old ), array_column( $rows, 'ID' ) );
		$this->assertSame( 'description', $rows[0]['missing'] );
		$this->assertNotContains( $ready, array_column( $rows, 'ID' ) );
	}

	/**
	 * The import lists each canonical address it copies, like redirects, so they can be checked.
	 */
	public function test_copied_canonicals_are_listed(): void {
		$post = $this->legacy_post( array( '_yoast_wpseo_canonical' => 'https://example.org/original/' ) );
		$this->import();
		$printed = implode( "\n", array_column( WP_CLI::$messages, 1 ) );
		$this->assertStringContainsString( "Post $post canonical: https://example.org/original/", $printed );
	}

	/**
	 * The audit prints titles as plain one-line text, and in CSV a title can't start a spreadsheet
	 * formula.
	 */
	public function test_audit_titles_are_plain(): void {
		$post = $this->publish_post( array( 'post_title' => "=SUM(1)\u{009B}x\ny" ) );
		delete_post_meta( $post, TALA_SEO_DESCRIPTION );
		( new Tala_SEO_CLI() )->audit( array(), array( 'format' => 'csv' ) );
		$this->assertSame( "'=SUM(1) x y", WP_CLI::$tables[0]['items'][0]['title'] );
		WP_CLI::reset();
		( new Tala_SEO_CLI() )->audit( array(), array() );
		$this->assertSame( '=SUM(1) x y', WP_CLI::$tables[0]['items'][0]['title'], 'A table is not a spreadsheet.' );
	}

	/**
	 * The messages the last command printed at a level.
	 *
	 * @param string $level warning, log, success.
	 * @return string[]
	 */
	private function said( string $level ): array {
		return array_column( array_values( array_filter( WP_CLI::$messages, static fn( $message ) => $level === $message[0] ) ), 1 );
	}

	/**
	 * Yoast SEO's site-wide settings fill Settings → SEO (only what is still at its default), turn
	 * off attachment pages it redirected, and hide the tag pages it hid as a rule, except one let in
	 * on purpose.
	 */
	public function test_yoast_site_settings(): void {
		update_option(
			'wpseo_titles',
			array(
				'separator'            => 'sc-dash',
				'metadesc-home-wpseo'  => 'Practical guides for teachers, by teachers.',
				'company_or_person'    => 'company',
				'company_name'         => 'Guro Notes Inc.',
				'disable-attachment'   => true,
				'noindex-tax-category' => false,
				'noindex-tax-post_tag' => true,
				'noindex-author-wpseo' => false,
			)
		);
		update_option(
			'wpseo_social',
			array(
				'facebook_site'     => 'https://www.facebook.com/guronotes/',
				'twitter_site'      => 'guronotes',
				'other_social_urls' => array( 'https://www.youtube.com/@guronotes', 'not a url' ),
				'og_default_image'  => set_url_scheme( (string) wp_get_attachment_url( self::$image_id ), 'http' ),
			)
		);
		update_option(
			'wpseo',
			array(
				'googleverify' => 'gWDk0KEWEAEKs4V5uaDNe2Hmb5TboTz8zJNXLoS1AtY',
				'msverify'     => '509AC9662108B4BE62203A99F67F9CC9',
			)
		);
		update_option(
			'wpseo_local',
			array(
				'business_type'             => 'EducationalOrganization',
				'location_address'          => 'Block 2 Lot 10 Unit A Venus Street',
				'location_address_2'        => 'Crestview Homes Subdivision Brgy. Mayamot',
				'location_city'             => 'Antipolo',
				'location_state'            => 'Rizal',
				'location_zipcode'          => '1870',
				'location_country'          => 'PH',
				'location_phone'            => '09260211602',
				'location_email'            => 'support@example.org',
				'opening_hours_monday_from' => '09:00',
			)
		);
		update_option( 'wp_attachment_pages_enabled', '1' );
		$hidden_tag = self::factory()->tag->create();
		$open_tag   = self::factory()->tag->create();
		$category   = self::factory()->category->create();
		update_option( 'wpseo_taxonomy_meta', array( 'post_tag' => array( $open_tag => array( 'wpseo_noindex' => 'index' ) ) ) );
		$this->set_setting( 'home_description', 'Our own words.' );

		$this->assertSame( 'Dry run, nothing changed. Would copy 11 value(s).', $this->import( true ), 'Ten settings and one tag.' );
		$this->assertSame( '|', tala_seo_settings()['separator'] );
		$this->assertSame( '1', get_option( 'wp_attachment_pages_enabled' ) );

		$this->import();
		$settings = tala_seo_settings();
		$this->assertSame( '-', $settings['separator'] );
		$this->assertSame( 'Our own words.', $settings['home_description'], 'A value of Tala SEO\'s own stays.' );
		$this->assertSame( 'organization', $settings['publisher_type'] );
		$this->assertSame( 'Guro Notes Inc.', $settings['publisher_name'] );
		$this->assertSame( array( 'https://www.facebook.com/guronotes/', 'https://x.com/guronotes', 'https://www.youtube.com/@guronotes' ), $settings['same_as'] );
		$this->assertSame( self::$image_id, $settings['default_image'], 'Found from its address, whatever its scheme.' );
		$this->assertSame( 'gWDk0KEWEAEKs4V5uaDNe2Hmb5TboTz8zJNXLoS1AtY', $settings['verify']['google'] );
		$this->assertSame( '509AC9662108B4BE62203A99F67F9CC9', $settings['verify']['bing'] );
		$this->assertSame( '0', get_option( 'wp_attachment_pages_enabled' ) );
		$this->assertSame( 'EducationalOrganization', $settings['organization_type'] );
		$this->assertSame( 'Block 2 Lot 10 Unit A Venus Street, Crestview Homes Subdivision Brgy. Mayamot', $settings['street_address'] );
		$this->assertSame( array( 'Antipolo', 'Rizal', '1870', 'PH' ), array( $settings['locality'], $settings['region'], $settings['postal_code'], $settings['country'] ) );
		$this->assertSame( '09260211602', $settings['telephone'] );
		$this->assertSame( 'support@example.org', $settings['email'] );
		$this->assertSame( '1', get_term_meta( $hidden_tag, TALA_SEO_NOINDEX, true ) );
		$this->assertFalse( metadata_exists( 'term', $open_tag, TALA_SEO_NOINDEX ), 'A tag Yoast SEO let in stays in.' );
		$this->assertFalse( metadata_exists( 'term', $category, TALA_SEO_NOINDEX ), 'Categories were indexed.' );

		$this->set_setting( 'separator', ':' );
		$this->assertStringContainsString( 'Copied 0 value(s)', $this->import(), 'Nothing new the second time.' );
		$this->assertSame( ':', tala_seo_settings()['separator'], 'And nothing overwritten.' );
	}

	/**
	 * With settings from both Yoast SEO and Rank Math and neither active, the site-wide settings
	 * wait for --from; with --from=rank-math, Rank Math's settings and its post values come first.
	 */
	public function test_rank_math_site_settings(): void {
		update_option( 'wpseo_titles', array( 'separator' => 'sc-dash' ) );
		update_option(
			'rank-math-options-titles',
			array(
				'title_separator'            => '&bull;',
				'homepage_description'       => 'Free downloadable resources for teachers.',
				'knowledgegraph_type'        => 'company',
				'knowledgegraph_name'        => 'tala test blog',
				'social_url_facebook'        => 'https://www.facebook.com/talatest/',
				'social_additional_profiles' => "https://www.youtube.com/@talatest\nhttps://www.tiktok.com/@talatest",
				'tax_category_robots'        => array( 'noindex' ),
				'tax_category_custom_robots' => 'on',
				'tax_post_tag_robots'        => array( 'noindex' ),
				'tax_post_tag_custom_robots' => 'off',
				'disable_author_archives'    => 'on',
				'local_business_type'        => 'EducationalOrganization',
				'local_address'              => array(
					'streetAddress'   => 'Block 2 Venus Street',
					'addressLocality' => 'Antipolo City',
					'addressRegion'   => 'Rizal',
					'postalCode'      => '1870',
					'addressCountry'  => 'PH',
				),
				'phone_numbers'              => array(
					array(
						'type'   => 'customer support',
						'number' => '+639761082833',
					),
				),
				'email'                      => 'support@example.net',
			)
		);
		update_option( 'rank-math-options-general', array( 'attachment_redirect_urls' => 'on' ) );
		update_option( 'wp_attachment_pages_enabled', '1' );
		$category = self::factory()->category->create();
		$tag      = self::factory()->tag->create();
		$author   = self::factory()->user->create( array( 'role' => 'author' ) );
		$post     = $this->publish_post(
			array(
				'post_author' => $author,
				'meta_input'  => array(
					'_yoast_wpseo_metadesc' => 'An old Yoast SEO description.',
					'rank_math_description' => 'The description Rank Math showed.',
				),
			)
		);
		delete_post_meta( $post, TALA_SEO_DESCRIPTION );

		$this->import( true );
		$this->assertStringContainsString( '--from=yoast or --from=rank-math', implode( ' ', $this->said( 'warning' ) ) );

		$this->import( false, array( 'from' => 'rank-math' ) );
		$settings = tala_seo_settings();
		$this->assertSame( '•', $settings['separator'] );
		$this->assertSame( 'Free downloadable resources for teachers.', $settings['home_description'] );
		$this->assertSame( '', $settings['publisher_name'], 'The site\'s own name, in other letters, is the default.' );
		$this->assertSame( array( 'https://www.facebook.com/talatest/', 'https://www.youtube.com/@talatest', 'https://www.tiktok.com/@talatest' ), $settings['same_as'] );
		$this->assertSame( '0', get_option( 'wp_attachment_pages_enabled' ) );
		$this->assertSame( 'EducationalOrganization', $settings['organization_type'] );
		$this->assertSame( array( 'Block 2 Venus Street', 'Antipolo City', 'Rizal', '1870', 'PH' ), array( $settings['street_address'], $settings['locality'], $settings['region'], $settings['postal_code'], $settings['country'] ) );
		$this->assertSame( '+639761082833', $settings['telephone'] );
		$this->assertSame( 'support@example.net', $settings['email'] );
		$this->assertSame( '1', get_term_meta( $category, TALA_SEO_NOINDEX, true ), 'Categories were hidden as a rule.' );
		$this->assertFalse( metadata_exists( 'term', $tag, TALA_SEO_NOINDEX ), 'Tags followed the site\'s robots setting.' );
		$this->assertSame( '1', get_user_meta( $author, TALA_SEO_NOINDEX, true ), 'Author pages were switched off.' );
		$this->assertSame( 'The description Rank Math showed.', get_post_meta( $post, TALA_SEO_DESCRIPTION, true ) );
	}

	/**
	 * Titles made with variables are filled in, and the site name the plugin added comes off; a
	 * title that is just the post's own, or has a variable without a value here, isn't copied.
	 */
	public function test_titles_with_variables(): void {
		$titles = array(
			'%%title%% %%page%% %%sep%% %%sitename%%' => '',
			'Updated DepEd Order No. 13, s. 2017 %%sep%% %%sitename%%' => 'Updated DepEd Order No. 13, s. 2017',
			'%title% for grade 6 %sep% %sitename%'    => 'Planning a lesson for grade 6',
			'Lesson plans | Tala Test Blog'           => 'Lesson plans',
			'Tala Test Blog: lesson plans'            => 'lesson plans',
			'%%cf_grade%% lesson plans'               => '',
		);
		$posts  = array();
		foreach ( array_keys( $titles ) as $title ) {
			$posts[ $title ] = $this->legacy_post( array( str_contains( $title, '%%' ) || ! str_contains( $title, '%' ) ? '_yoast_wpseo_title' : 'rank_math_title' => $title ) );
			wp_update_post(
				array(
					'ID'         => $posts[ $title ],
					'post_title' => 'Planning a lesson',
				)
			);
		}
		$excerpt = $this->legacy_post( array( '_yoast_wpseo_metadesc' => '%%excerpt%%' ) );
		wp_update_post(
			array(
				'ID'           => $excerpt,
				'post_excerpt' => 'A short summary.',
			)
		);
		update_option( 'blogname', 'Tala Test Blog' );

		$this->import();
		foreach ( $titles as $title => $expected ) {
			$this->assertSame( $expected, (string) get_post_meta( $posts[ $title ], TALA_SEO_TITLE, true ), $title );
		}
		$this->assertSame( 'A short summary.', get_post_meta( $excerpt, TALA_SEO_DESCRIPTION, true ) );
	}

	/**
	 * `wp tala-seo describe` writes the missing descriptions from the opening paragraphs: whole
	 * sentences that fit, else whole words with an ellipsis (an abbreviation's dot isn't a sentence
	 * end); posts with a description or a password are left alone.
	 */
	public function test_describe(): void {
		$paragraph = static fn( string $text ): string => '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->';
		$sentences = $this->publish_post(
			array(
				'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Before you start</h2><!-- /wp:heading -->'
					. $paragraph( 'Download:' )
					. $paragraph( 'This module was designed and written with you in mind. It helps you create rhythmic patterns in music class. The scope of this module permits it to be used in many learning situations.' ),
			)
		);
		$abbrev    = $this->publish_post(
			array(
				'post_content' => $paragraph( 'The guidelines in this order cover the conduct of school activities as set in DepEd Order No. 13, s. 2017 and all the orders that came after it, for every school.' ),
			)
		);
		$short     = $this->publish_post(
			array(
				'post_content' => '<!-- wp:group --><div class="wp-block-group">' . $paragraph( 'A short note about lesson plans for new teachers.' ) . '</div><!-- /wp:group -->',
			)
		);
		$intro     = 'This Self-Learning Module was prepared so that you can continue your studies at home.';
		$modules   = array();
		foreach ( array( 'fractions', 'decimals', 'ratios' ) as $topic ) {
			$modules[ $topic ] = $this->publish_post( array( 'post_content' => $paragraph( $intro ) . $paragraph( "In this module you will learn to work with $topic in everyday problems." ) ) );
		}
		$described = $this->publish_post( array( 'post_content' => $paragraph( 'Some text that is long enough to describe the post well.' ) ) );
		$locked    = $this->publish_post(
			array(
				'post_content'  => $paragraph( 'Members only: the full lesson plan for the week, with worksheets.' ),
				'post_password' => 'secret',
			)
		);

		// Published as the publish check asks, then left without a description, as older posts are.
		foreach ( array_merge( array( $sentences, $abbrev, $short, $locked ), array_values( $modules ) ) as $id ) {
			delete_post_meta( $id, TALA_SEO_DESCRIPTION );
		}

		WP_CLI::reset();
		( new Tala_SEO_CLI() )->describe( array(), array( 'dry-run' => true ) );
		$this->assertSame( array( 'Dry run, nothing changed. Would write 6 description(s).' ), $this->said( 'success' ) );
		$this->assertSame( '', get_post_meta( $sentences, TALA_SEO_DESCRIPTION, true ) );

		WP_CLI::reset();
		( new Tala_SEO_CLI() )->describe( array(), array() );
		$this->assertSame( 'This module was designed and written with you in mind. It helps you create rhythmic patterns in music class.', get_post_meta( $sentences, TALA_SEO_DESCRIPTION, true ) );
		$cut = (string) get_post_meta( $abbrev, TALA_SEO_DESCRIPTION, true );
		$this->assertStringStartsWith( 'The guidelines in this order cover the conduct of school activities as set in DepEd Order No. 13, s. 2017', $cut, 'Not cut at the dot of "No." or "s.".' );
		$this->assertStringEndsWith( '…', $cut );
		$this->assertLessThanOrEqual( 155, mb_strlen( $cut ) );
		$this->assertSame( 'A short note about lesson plans for new teachers.', get_post_meta( $short, TALA_SEO_DESCRIPTION, true ) );
		$this->assertSame( 'In this module you will learn to work with decimals in everyday problems.', get_post_meta( $modules['decimals'], TALA_SEO_DESCRIPTION, true ), 'The introduction three posts share is passed over.' );
		$this->assertSame( 'A test description.', get_post_meta( $described, TALA_SEO_DESCRIPTION, true ) );
		$this->assertSame( '', get_post_meta( $locked, TALA_SEO_DESCRIPTION, true ) );
	}
}
