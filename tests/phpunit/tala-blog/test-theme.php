<?php
/**
 * Tala Blog's PHP: block filters, theme colours, patterns, templates and template parts.
 *
 * @package TalaTests
 */

/**
 * Theme.
 */
class Test_Tala_Blog_Theme extends Tala_SEO_Test_Case {

	/**
	 * Readable URLs, as on a real site.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_pretty_permalinks();
	}

	/**
	 * Renders one block in the context of a post.
	 *
	 * @param string $markup  Block markup.
	 * @param int    $post_id Post the block shows.
	 * @return string
	 */
	private function render( string $markup, int $post_id ): string {
		$block = new WP_Block(
			parse_blocks( $markup )[0],
			array(
				'postId'   => $post_id,
				'postType' => 'post',
			)
		);
		return $block->render();
	}

	/**
	 * Inside a Navigation block, a Page List gives only its items, so the menu is one list.
	 * Anywhere else it keeps its own list.
	 */
	public function test_page_list_inside_navigation(): void {
		$this->publish_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'About',
			)
		);
		$menu = do_blocks( '<!-- wp:navigation {"overlayMenu":"never"} --><!-- wp:page-list /--><!-- /wp:navigation -->' );
		$this->assertStringContainsString( '>About</a>', $menu );
		$this->assertDoesNotMatchRegularExpression( '#<ul[^>]*>\s*<ul#', $menu, 'No list directly inside a list' );
		$this->assertStringNotContainsString( 'wp-block-page-list', $menu );

		$this->assertStringContainsString( '<ul class="wp-block-page-list">', do_blocks( '<!-- wp:page-list /-->' ) );
	}

	/**
	 * The logo beside the site title is decorative; a Site Logo anywhere else keeps its alt text.
	 */
	public function test_logo_beside_the_title_is_decorative(): void {
		update_option( 'site_logo', self::$image_id );
		$beside = do_blocks( '<!-- wp:site-logo {"width":28,"isLink":false,"className":"tala-logo"} /-->' );
		$this->assertStringContainsString( 'alt=""', $beside );
		$this->assertStringNotContainsString( '<a ', $beside, 'Not a second link to the home page' );
		$this->assertMatchesRegularExpression( '/<img[^>]* width="28" height="\d+"/', $beside, 'Its size is set (no layout shift).' );

		$elsewhere = do_blocks( '<!-- wp:site-logo /-->' );
		$this->assertStringContainsString( 'alt="A test cover image"', $elsewhere );

		delete_option( 'site_logo' );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:site-logo {"className":"tala-logo"} /-->' ) ), 'No logo, no element' );
	}

	/**
	 * Search forms can be named for screen readers (two on a page get different names).
	 */
	public function test_search_block_can_be_named(): void {
		$search = WP_Block_Type_Registry::get_instance()->get_registered( 'core/search' );
		$this->assertTrue( $search->supports['ariaLabel'] ?? false );
		$this->assertStringContainsString( 'aria-label="Articles"', do_blocks( '<!-- wp:search {"ariaLabel":"Articles","label":"Search"} /-->' ) );
	}

	/**
	 * The browser's theme colour follows theme.json in light and dark mode.
	 */
	public function test_theme_colour(): void {
		$meta = get_echo( 'tala_blog_theme_color' );
		$this->assertStringContainsString( '<meta name="theme-color" content="#fcfcfc" media="(prefers-color-scheme: light)">', $meta );
		$this->assertStringContainsString( '<meta name="theme-color" content="#121212" media="(prefers-color-scheme: dark)">', $meta );
	}

	/**
	 * The article's dates read "Published" and "Updated"; an update on the publishing day isn't
	 * shown; dates in lists stay unlabelled.
	 */
	public function test_article_dates_are_labelled(): void {
		$post = $this->publish_post( array( 'post_date' => '2026-03-01 08:00:00' ) );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- a modified date WordPress itself won't write.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => '2026-03-01 15:00:00',
				'post_modified_gmt' => '2026-03-01 15:00:00',
			),
			array( 'ID' => $post )
		);
		clean_post_cache( $post );

		$this->go_to( get_permalink( $post ) );
		$published = $this->render( '<!-- wp:post-date /-->', $post );
		$this->assertStringContainsString( '<span class="tala-date-label">Published</span><time', $published );
		$this->assertSame( '', $this->render( '<!-- wp:post-date {"displayType":"modified"} /-->', $post ), 'Updated on the same day' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- a modified date WordPress itself won't write.
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => '2026-03-20 15:00:00',
				'post_modified_gmt' => '2026-03-20 15:00:00',
			),
			array( 'ID' => $post )
		);
		clean_post_cache( $post );
		$this->go_to( get_permalink( $post ) );
		$this->assertStringContainsString( '<span class="tala-date-label">Updated</span>', $this->render( '<!-- wp:post-date {"displayType":"modified"} /-->', $post ) );

		$this->go_to( home_url( '/' ) );
		$this->assertStringNotContainsString( 'tala-date-label', $this->render( '<!-- wp:post-date /-->', $post ), 'In a list' );
	}

	/**
	 * List thumbnails tell the browser their real width, so phones load a small file.
	 */
	public function test_list_thumbnail_sizes(): void {
		$post  = $this->publish_post();
		$thumb = $this->render( '<!-- wp:post-featured-image {"sizeSlug":"medium","className":"tala-list-thumb"} /-->', $post );
		$this->assertMatchesRegularExpression( '/sizes="(auto, )?\(min-width: 40em\) 8rem, 5\.5rem"/', $thumb );

		$other = $this->render( '<!-- wp:post-featured-image /-->', $post );
		$this->assertStringNotContainsString( '5.5rem', $other );
	}

	/**
	 * Lighter pages: no emoji script, all block CSS inlined, no remote patterns.
	 */
	public function test_page_weight_settings(): void {
		$this->assertFalse( has_action( 'wp_head', 'print_emoji_detection_script' ) );
		$this->assertFalse( has_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' ) );
		$this->assertSame( 60000, apply_filters( 'styles_inline_size_limit', 20000 ) );
		$this->assertFalse( apply_filters( 'should_load_remote_block_patterns', true ) );
	}

	/**
	 * Every pattern registers and parses; the hidden ones stay out of the inserter.
	 */
	public function test_patterns(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$files    = glob( dirname( __DIR__, 3 ) . '/tala-blog/patterns/*.php' );
		$this->assertNotEmpty( $files );
		foreach ( $files as $file ) {
			$slug    = 'tala-blog/' . basename( $file, '.php' );
			$pattern = $registry->get_registered( $slug );
			$this->assertIsArray( $pattern, $slug );
			$this->assertFalse( $pattern['inserter'] ?? true, "$slug stays out of the inserter" );
			$this->assertNotEmpty( parse_blocks( $pattern['content'] ), $slug );
		}
		$this->assertStringContainsString( '"slug":"tala-blog/site-brand"', $registry->get_registered( 'tala-blog/header' )['content'] );
		$this->assertStringContainsString( '"slug":"tala-blog/site-brand"', $registry->get_registered( 'tala-blog/navigation-overlay' )['content'] );
	}

	/**
	 * Template parts and their areas; search results and the 404 page use the footer without
	 * search, as they have a search form at the top.
	 */
	public function test_templates_and_parts(): void {
		$areas = array(
			'header'                => 'header',
			'footer'                => 'footer',
			'footer-without-search' => 'footer',
			'navigation-overlay'    => 'navigation-overlay',
		);
		foreach ( $areas as $slug => $area ) {
			$part = get_block_template( 'tala-blog//' . $slug, 'wp_template_part' );
			$this->assertInstanceOf( WP_Block_Template::class, $part, $slug );
			$this->assertSame( $area, $part->area, $slug );
		}
		foreach ( array( 'search', '404' ) as $slug ) {
			$this->assertStringContainsString( '"slug":"footer-without-search"', get_block_template( 'tala-blog//' . $slug )->content, $slug );
		}
		foreach ( array( 'index', 'home', 'single', 'page', 'archive', 'author' ) as $slug ) {
			$this->assertStringContainsString( '"slug":"footer",', get_block_template( 'tala-blog//' . $slug )->content, $slug );
		}
	}

	/**
	 * The header: logo and title, and the menu named "Primary" that opens the theme's overlay on phones.
	 */
	public function test_header(): void {
		$this->publish_post( array( 'post_type' => 'page' ) );
		$header = do_blocks( '<!-- wp:template-part {"slug":"header","theme":"tala-blog","tagName":"header"} /-->' );
		$this->assertStringContainsString( 'tala-brand', $header );
		$this->assertMatchesRegularExpression( '/<nav[^>]* aria-label="Primary"/', $header );
		$this->assertStringContainsString( 'disable-default-overlay', $header, 'The theme\'s own overlay' );
		$this->assertStringContainsString( 'wp-block-navigation-overlay-close', $header );
	}
}
