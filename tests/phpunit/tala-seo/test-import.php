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
	 * @param bool $dry_run Whether to pass --dry-run.
	 * @return string The success message.
	 */
	private function import( bool $dry_run = false ): string {
		WP_CLI::reset();
		( new Tala_SEO_CLI() )->import( array(), $dry_run ? array( 'dry-run' => true ) : array() );
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

		$this->import();
		$this->assertSame( '', get_post_meta( $theirs, TALA_SEO_REDIRECT, true ), 'Not from a contributor' );
		$this->assertSame( 'https://example.org/author/', get_post_meta( $own, TALA_SEO_REDIRECT, true ) );
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
}
