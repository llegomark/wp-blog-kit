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
	 * Renders one block in the context of a post, with that post as the current one, as in a Query
	 * Loop or on its own page (some core blocks read the global post).
	 *
	 * @param string $markup  Block markup.
	 * @param int    $post_id Post the block shows.
	 * @return string
	 */
	private function render( string $markup, int $post_id ): string {
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );
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
	 * An article's sections, as headings of one level in a row.
	 *
	 * @param string[] $titles Heading texts.
	 * @param int      $level  Heading level.
	 * @return string Block markup.
	 */
	private function headings( array $titles, int $level = 2 ): string {
		$markup = '';
		foreach ( $titles as $title ) {
			$attrs   = 2 === $level ? '' : ' {"level":' . $level . '}';
			$markup .= "<!-- wp:heading$attrs -->\n<h$level class=\"wp-block-heading\">$title</h$level>\n<!-- /wp:heading -->\n\n";
		}
		return $markup;
	}

	/**
	 * "In this article": after the opening paragraph, before the first section, a navigation
	 * landmark that folds into one line and links each section (and its subsections) to an anchor
	 * that describes it. Anchors a heading has are kept (unless used twice, or the classic editor's);
	 * new ones come from the heading's words and never repeat an id of the page; headings in answers, deeper levels and headings marked
	 * tala-no-contents stay out.
	 */
	public function test_table_of_contents(): void {
		$content = <<<'HTML'
<!-- wp:paragraph -->
<p>Opening paragraph.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Plan the week</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Monday &amp; Tuesday</h3>
<!-- /wp:heading -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="kept-anchor">Check the forms</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Too deep</h4>
<!-- /wp:heading -->

<!-- wp:details -->
<details class="wp-block-details"><summary>A question</summary><!-- wp:heading -->
<h2 class="wp-block-heading">Inside an answer</h2>
<!-- /wp:heading --></details>
<!-- /wp:details -->

<!-- wp:heading {"className":"tala-no-contents"} -->
<h2 class="wp-block-heading tala-no-contents">Left out</h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"tala-no-contents"} -->
<div class="wp-block-group tala-no-contents"><!-- wp:heading -->
<h2 class="wp-block-heading">Also left out</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Plan the week</h2>
<!-- /wp:heading -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Comments</h2>
<!-- /wp:heading -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="mce_7">Left by the classic editor</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p id="twice">A paragraph with an id.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="twice">An id used twice</h2>
<!-- /wp:heading -->
HTML;
		$post    = $this->publish_post( array( 'post_content' => $content ) );
		$this->go_to( get_permalink( $post ) );
		$html = $this->render( '<!-- wp:post-content /-->', $post );

		$this->assertMatchesRegularExpression( '#Opening paragraph\.</p>\s*<nav class="tala-contents" aria-label="In this article"><details><summary><span class="tala-contents-title">In this article</span> <span class="tala-contents-count">6 sections</span></summary><ol>#', $html, 'After the opening, folded.' );
		$this->assertStringContainsString(
			'<ol><li><a href="#plan-the-week">Plan the week</a><ol><li><a href="#monday-tuesday">Monday &amp; Tuesday</a></li></ol></li>'
			. '<li><a href="#kept-anchor">Check the forms</a></li>'
			. '<li><a href="#plan-the-week-2">Plan the week</a></li>'
			. '<li><a href="#comments-2">Comments</a></li>'
			. '<li><a href="#left-by-the-classic-editor">Left by the classic editor</a></li>'
			. '<li><a href="#an-id-used-twice">An id used twice</a></li></ol></details></nav>',
			$html
		);
		foreach ( array( 'plan-the-week', 'monday-tuesday', 'kept-anchor', 'plan-the-week-2', 'comments-2', 'left-by-the-classic-editor', 'an-id-used-twice' ) as $id ) {
			$this->assertSame( 1, substr_count( $html, ' id="' . $id . '"' ), $id );
		}
		foreach ( array( 'Too deep', 'Inside an answer', 'Left out', 'Also left out' ) as $text ) {
			$this->assertSame( 1, substr_count( $html, $text ), $text );
		}
		$this->assertStringContainsString( '<h4 class="wp-block-heading">Too deep</h4>', $html, 'Headings out of it are left as they are.' );
		$this->assertStringNotContainsString( 'data-tala-contents', $html );
		$this->assertSame( 11, preg_match_all( '/<h[1-6][\s>]/', $html ), 'The article\'s eleven headings; the box adds none.' );
		$this->assertStringNotContainsString( 'mce_7', $html );
		$this->assertSame( 1, substr_count( $html, 'id="twice"' ), 'The paragraph keeps it.' );
	}

	/**
	 * The box goes before the top-level block holding the first section; it needs enough sections
	 * (3 by default, a setting); it can be turned off; only the article being read has one; HTML
	 * WordPress can't read is left alone.
	 */
	public function test_table_of_contents_when_and_where(): void {
		$grouped = "<!-- wp:paragraph -->\n<p>Opening.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:group -->\n<div class=\"wp-block-group\">"
			. $this->headings( array( 'One' ) ) . "</div>\n<!-- /wp:group -->\n\n" . $this->headings( array( 'Two', 'Three' ) );
		$post    = $this->publish_post( array( 'post_content' => $grouped ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertMatchesRegularExpression( '#Opening\.</p>\s*<nav class="tala-contents"[^>]*>.*</nav><div class="wp-block-group[^"]*">\s*<h2 id="one" class="wp-block-heading">One</h2>#s', $this->render( '<!-- wp:post-content /-->', $post ) );

		$two = $this->publish_post( array( 'post_content' => $this->headings( array( 'One', 'Two' ) ) . $this->headings( array( 'A', 'B', 'C' ), 3 ) ) );
		$this->go_to( get_permalink( $two ) );
		$this->assertStringNotContainsString( 'tala-contents', $this->render( '<!-- wp:post-content /-->', $two ), 'Two sections: subsections don\'t count.' );
		set_theme_mod( 'tala_blog_contents_sections', '2' );
		$this->assertStringContainsString( '2 sections', $this->render( '<!-- wp:post-content /-->', $two ), 'Unless two are enough.' );
		remove_theme_mod( 'tala_blog_contents_sections' );

		$this->go_to( get_permalink( $post ) );
		set_theme_mod( 'tala_blog_contents', false );
		$this->assertStringNotContainsString( 'tala-contents', $this->render( '<!-- wp:post-content /-->', $post ), 'Turned off.' );
		remove_theme_mod( 'tala_blog_contents' );

		$this->go_to( home_url( '/' ) );
		$this->assertStringNotContainsString( 'tala-contents', $this->render( '<!-- wp:post-content /-->', $post ), 'Not in a list.' );
		$page = $this->publish_post(
			array(
				'post_type'    => 'page',
				'post_content' => $grouped,
			)
		);
		$this->go_to( get_permalink( $page ) );
		$this->assertStringNotContainsString( 'tala-contents', $this->render( '<!-- wp:post-content /-->', $page ), 'Not on a page.' );

		$broken = '<div class="wp-block-post-content"><h2>One</h2><h2>Two</h2><h2>Three</h2><h2';
		$this->assertSame( $broken, tala_blog_add_contents( $broken, 3 ), 'HTML that can\'t be read stays as it is.' );

		$this->assertSame( 3, tala_blog_sanitize_sections( 'many' ) );
		$this->assertSame( 2, tala_blog_sanitize_sections( '1' ) );
		$this->assertSame( 10, tala_blog_sanitize_sections( 40 ) );
		$this->assertSame( 5, tala_blog_sanitize_sections( '5' ) );
		$this->assertSame( 'plan-the-week-with-your-grade-5-class-and-check-every-form', tala_blog_anchor( 'Plan the week with your Grade 5 class, and check every form before Friday' ), 'Cut between words.' );
		$this->assertSame( 'section', tala_blog_anchor( '!!!' ) );

		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$manager = new WP_Customize_Manager();
		tala_blog_customize_register( $manager );
		$this->assertNotNull( $manager->get_section( 'tala_blog_contents' ) );
		$this->assertSame( '1', $manager->get_setting( 'tala_blog_contents' )->default, 'On by default.' );
		$this->assertSame( 'checkbox', $manager->get_control( 'tala_blog_contents' )->type );
		$this->assertSame( 'number', $manager->get_control( 'tala_blog_contents_sections' )->type );
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

		update_option( 'date_format', 'F j, Y' );
		update_option( 'time_format', 'g:i a' );
		$this->go_to( get_permalink( $post ) );
		$published = $this->render( '<!-- wp:post-date /-->', $post );
		$this->assertStringContainsString( '<span class="tala-date-label">Published</span><time', $published );
		$this->assertStringContainsString( '>March 1, 2026 at 8:00 am</time>', $published, 'With the time' );
		$this->assertStringContainsString( '>2026-03-01</time>', $this->render( '<!-- wp:post-date {"format":"Y-m-d"} /-->', $post ), "The block's own format" );
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
		$updated = $this->render( '<!-- wp:post-date {"displayType":"modified"} /-->', $post );
		$this->assertStringContainsString( '<span class="tala-date-label">Updated</span>', $updated );
		$this->assertStringContainsString( '>March 20, 2026 at 3:00 pm</time>', $updated );

		$this->go_to( home_url( '/' ) );
		$listed = $this->render( '<!-- wp:post-date /-->', $post );
		$this->assertStringNotContainsString( 'tala-date-label', $listed, 'In a list' );
		$this->assertStringContainsString( '>March 1, 2026</time>', $listed, 'No time in a list' );
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
		// One search form per page: at the top of every page but the home page, in its footer there.
		foreach ( array( 'search', '404', 'index', 'single', 'page', 'archive', 'author' ) as $slug ) {
			$content = get_block_template( 'tala-blog//' . $slug )->content;
			$this->assertStringContainsString( '"slug":"footer-without-search"', $content, $slug );
			if ( '404' !== $slug ) { // Its search form is in its text pattern (tala-blog/hidden-404).
				$this->assertStringContainsString( '"slug":"tala-blog/search"', $content, $slug );
			}
		}
		$home = get_block_template( 'tala-blog//home' )->content;
		$this->assertStringContainsString( '"slug":"footer",', $home );
		$this->assertStringNotContainsString( '"slug":"tala-blog/search"', $home );
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

	/**
	 * A post without a title gets its date as the heading, linked in lists, so it can be told apart
	 * and opened (Theme Unit Test: "No Title"). A post with a title keeps it.
	 */
	public function test_untitled_post_heading(): void {
		$untitled = $this->publish_post(
			array(
				'post_title' => '',
				'post_date'  => '2026-10-05 08:00:00',
			)
		);
		$date     = get_the_date( '', $untitled );
		$this->assertSame( '<h1 class="wp-block-post-title">' . $date . '</h1>', $this->render( '<!-- wp:post-title {"level":1} /-->', $untitled ) );
		$this->assertSame(
			'<h2 class="wp-block-post-title"><a href="' . get_permalink( $untitled ) . '">' . $date . '</a></h2>',
			$this->render( '<!-- wp:post-title {"level":2,"isLink":true} /-->', $untitled )
		);

		$titled = $this->publish_post( array( 'post_title' => 'Seating plans' ) );
		$this->assertStringContainsString( '>Seating plans</h1>', $this->render( '<!-- wp:post-title {"level":1} /-->', $titled ) );
	}

	/**
	 * A sticky post is marked "Pinned" at the top of the home page's first page, and nowhere else.
	 */
	public function test_sticky_post_is_pinned(): void {
		$sticky = $this->publish_post( array( 'post_category' => array( 1 ) ) );
		stick_post( $sticky );
		$other = $this->publish_post();
		$date  = '<!-- wp:post-date /-->';

		$this->go_to( home_url( '/' ) );
		$this->assertStringContainsString( '<span class="tala-pinned">Pinned</span><time', $this->render( $date, $sticky ) );
		$this->assertStringNotContainsString( 'Pinned', $this->render( $date, $other ) );
		$this->assertStringNotContainsString( 'Pinned', $this->render( '<!-- wp:post-date {"displayType":"modified"} /-->', $sticky ), 'Only the publish date' );

		$this->go_to( get_category_link( 1 ) );
		$this->assertStringNotContainsString( 'Pinned', $this->render( $date, $sticky ), 'Not pinned on archives' );
	}

	/**
	 * Comments by the article's author are marked "Author"; a reader's aren't, even with the same name.
	 */
	public function test_author_comment_badge(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$post   = $this->publish_post( array( 'post_author' => $author ) );
		$badge  = '<span class="tala-comment-badge">Author</span>';
		$render = static function ( int $comment ): string {
			$block = new WP_Block( parse_blocks( '<!-- wp:comment-author-name /-->' )[0], array( 'commentId' => $comment ) );
			return $block->render();
		};

		$own = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post,
				'user_id'         => $author,
				'comment_author'  => 'Ana',
			)
		);
		$this->assertStringContainsString( 'Ana</a> ' . $badge . '</div>', $render( $own ) );

		$reader = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post,
				'comment_author'  => 'Ana',
			)
		);
		$this->assertStringNotContainsString( $badge, $render( $reader ) );
	}

	/**
	 * The comment form's heading is a level two, like the comments' title; under the comments of a
	 * post that no longer takes them, a note says so (not where there are no comments, or on pages).
	 */
	public function test_comment_form(): void {
		$post = $this->publish_post();
		$form = '<!-- wp:post-comments-form /-->';
		$this->assertStringContainsString( '<h2 id="reply-title" class="comment-reply-title">', $this->render( $form, $post ) );

		$closed = '<p class="tala-comments-closed">Comments are closed.</p>';
		wp_update_post(
			array(
				'ID'             => $post,
				'comment_status' => 'closed',
			)
		);
		$this->assertStringNotContainsString( $closed, $this->render( $form, $post ), 'No comments: nothing to explain' );

		self::factory()->comment->create( array( 'comment_post_ID' => $post ) );
		$this->assertSame( $closed, $this->render( $form, $post ) );

		wp_update_post(
			array(
				'ID'            => $post,
				'post_password' => 'secret',
			)
		);
		$this->assertSame( '', trim( $this->render( $form, $post ) ), 'Not while the comments are hidden by a password' );

		$page = $this->publish_post(
			array(
				'post_type'      => 'page',
				'comment_status' => 'closed',
			)
		);
		self::factory()->comment->create( array( 'comment_post_ID' => $page ) );
		$this->assertSame( '', trim( $this->render( $form, $page ) ), 'Not on pages' );
	}

	/**
	 * The templates show Tala SEO's breadcrumbs through a pattern that has nothing in it without the
	 * plugin, so the theme works, and the editor raises no missing block, on its own.
	 */
	public function test_breadcrumbs_only_with_the_plugin(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		foreach ( array( 'index', 'single', 'page', 'archive', 'author' ) as $slug ) {
			$content = $this->with_patterns( get_block_template( 'tala-blog//' . $slug )->content );
			$this->assertStringNotContainsString( 'wp:tala-seo/', $content, $slug );
			$this->assertStringContainsString( '"slug":"tala-blog/breadcrumbs"', $content, $slug );
		}
		$this->assertIsArray( $registry->get_registered( 'tala-blog/breadcrumbs' ) );

		// The pattern's content as WordPress makes it when a page first uses it.
		$content = static function (): string {
			ob_start();
			include get_theme_file_path( 'patterns/breadcrumbs.php' );
			return trim( (string) ob_get_clean() );
		};
		$this->assertSame( '<!-- wp:tala-seo/breadcrumbs /-->', $content() );
		unregister_block_type( 'tala-seo/breadcrumbs' );
		try {
			$this->assertSame( '', $content() );
		} finally {
			tala_seo_register_breadcrumbs_block();
		}
	}

	/**
	 * The labels the theme adds ("Published", "Pinned", "Author") are inserted as written: a
	 * translation holding "$0" or a backslash shows as it is.
	 */
	public function test_labels_are_inserted_as_written(): void {
		$odd    = static fn( $translation, $text, $domain ) => 'tala-blog' === $domain && in_array( $text, array( 'Published', 'Pinned', 'Author' ), true ) ? $text . ' $0 \1' : $translation;
		add_filter( 'gettext', $odd, 10, 3 );
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$post   = $this->publish_post( array( 'post_author' => $author ) );

		$this->go_to( get_permalink( $post ) );
		$this->assertStringContainsString( 'Published $0 \1</span><time', $this->render( '<!-- wp:post-date /-->', $post ) );

		stick_post( $post );
		$this->go_to( home_url( '/' ) );
		$this->assertStringContainsString( 'Pinned $0 \1</span><time', $this->render( '<!-- wp:post-date /-->', $post ) );

		$comment = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post,
				'user_id'         => $author,
			)
		);
		$block   = new WP_Block( parse_blocks( '<!-- wp:comment-author-name /-->' )[0], array( 'commentId' => $comment ) );
		$this->assertStringContainsString( 'Author $0 \1</span></div>', $block->render() );
	}

	/**
	 * Inside a Navigation block, the Page List's own list is unwrapped only when the HTML starts with
	 * that list: anything another plugin put before it is left as it is.
	 */
	public function test_page_list_unwrapped_only_from_its_own_list(): void {
		$instance = new WP_Block(
			array(
				'blockName'    => 'core/page-list',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array( 'showSubmenuIcon' => true )
		);
		$list     = '<ul class="wp-block-page-list"><li>Home</li></ul>';
		$this->assertSame( '<li>Home</li>', tala_blog_page_list_in_navigation( $list, array(), $instance ) );
		$this->assertSame( 'Note: a > b ' . $list, tala_blog_page_list_in_navigation( 'Note: a > b ' . $list, array(), $instance ) );
	}

	/**
	 * Markup with the markup of the theme patterns it uses, at any depth, put after it. The
	 * breadcrumbs pattern stays a reference: its markup is the plugin's block.
	 *
	 * @param string $markup Block markup.
	 * @return string
	 */
	private function with_patterns( string $markup ): string {
		$registry = WP_Block_Patterns_Registry::get_instance();
		preg_match_all( '#"slug":"(tala-blog/[a-z0-9-]+)"#', $markup, $slugs );
		foreach ( array_unique( $slugs[1] ) as $slug ) {
			if ( 'tala-blog/breadcrumbs' !== $slug ) {
				$markup .= "\n" . $this->with_patterns( (string) ( $registry->get_registered( $slug )['content'] ?? '' ) );
			}
		}
		return $markup;
	}

	/**
	 * What the Site Editor keeps of a style variation when it lists it as a colour or typography
	 * preset: only what sits under one of the keys, at any depth, as filterObjectByProperties() in
	 * WordPress's global styles code does. A variation that keeps all of its settings and styles this
	 * way is a preset; any other is listed as a whole style.
	 *
	 * @param array<mixed> $data Settings or styles.
	 * @param string[]     $keys Keys to keep.
	 * @return array<mixed>
	 */
	private static function only( array $data, array $keys ): array {
		$kept = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $keys, true ) ) {
				$kept[ $key ] = $value;
			} elseif ( is_array( $value ) && self::only( $value, $keys ) ) {
				$kept[ $key ] = self::only( $value, $keys );
			}
		}
		return $kept;
	}

	/**
	 * WCAG contrast ratio of two colours written #rrggbb.
	 *
	 * @param string $a Colour.
	 * @param string $b Colour.
	 * @return float
	 */
	private static function contrast( string $a, string $b ): float {
		$luminance = static function ( string $hex ): float {
			$channels = array_map(
				static function ( string $pair ): float {
					$value = hexdec( $pair ) / 255;
					return $value <= 0.04045 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
				},
				str_split( substr( $hex, 1 ), 2 )
			);
			return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
		};
		$light     = max( $luminance( $a ), $luminance( $b ) );
		$dark      = min( $luminance( $a ), $luminance( $b ) );
		return ( $light + 0.05 ) / ( $dark + 0.05 );
	}

	/**
	 * A JSON file of the theme, decoded.
	 *
	 * @param string $file Path in the theme.
	 * @return array<string, mixed>
	 */
	private static function theme_json( string $file ): array {
		return (array) wp_json_file_decode( get_theme_file_path( $file ), array( 'associative' => true ) );
	}

	/**
	 * Saves the site's styles as the Site Editor does when someone picks a colour preset: the
	 * preset's settings, as WordPress serves style variations to the editor, become the site's own.
	 *
	 * @param array<string, mixed> $variation Decoded variation file.
	 * @param bool                 $filtered  Whether the person saving can't save unfiltered HTML,
	 *                                        so WordPress keeps only the safe settings (presets).
	 */
	private function choose_variation( array $variation, bool $filtered = false ): void {
		// An administrator, as in the Site Editor: the styles are saved for the theme only by someone
		// who may assign it to them.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post     = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
		$settings = ( new WP_Theme_JSON( $variation ) )->get_raw_data()['settings'] ?? array();
		if ( $filtered ) {
			$safe     = WP_Theme_JSON::remove_insecure_properties(
				array(
					'version'  => WP_Theme_JSON::LATEST_SCHEMA,
					'settings' => $settings,
				)
			);
			$settings = $safe['settings'] ?? array();
		}
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post['ID'],
					'post_content' => (string) wp_json_encode(
						array(
							'version'                     => WP_Theme_JSON::LATEST_SCHEMA,
							'isGlobalStylesUserThemeJSON' => true,
							'settings'                    => $settings,
						)
					),
				)
			)
		);
		WP_Theme_JSON_Resolver::clean_cached_data();
		wp_clean_theme_json_cache();
	}

	/**
	 * "Keep reading" under an article: a navigation landmark of its own name, before the author box,
	 * with the older and newer articles as cards whose label sits inside the link. An article with
	 * no other article to point to (a site's only one) gets no landmark, heading included.
	 */
	public function test_post_navigation_is_a_named_landmark(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$older  = $this->publish_post(
			array(
				'post_title'  => 'Seating plans',
				'post_date'   => '2026-01-05 08:00:00',
				'post_author' => $author,
			)
		);
		$newer  = $this->publish_post(
			array(
				'post_title'  => 'Reading corners',
				'post_date'   => '2026-02-05 08:00:00',
				'post_author' => $author,
			)
		);
		$after  = '<!-- wp:pattern {"slug":"tala-blog/post-footer"} /-->';

		$this->go_to( get_permalink( $newer ) );
		$html = $this->render( $after, $newer );
		$tags = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $tags->next_tag( array( 'tag_name' => 'nav' ) ), 'A navigation landmark' );
		$this->assertSame( 'Keep reading', $tags->get_attribute( 'aria-label' ) );
		$this->assertMatchesRegularExpression( '#<nav\b.*<h2[^>]*>Keep reading</h2>.*</nav>#s', $html );
		$this->assertMatchesRegularExpression( '#<nav\b.*<a [^>]*rel="prev"[^>]*><span class="post-navigation-link__label">Older article</span> <span class="post-navigation-link__title">Seating plans</span></a>.*</nav>#s', $html, 'The label is part of the link, so the whole card is the target.' );
		$this->assertStringNotContainsString( 'Newer article', $html, 'The newest article has no newer one.' );
		$this->assertLessThan( strpos( $html, 'wp-block-post-author' ), strpos( $html, '<nav' ), 'Before the author box' );

		wp_delete_post( $older, true );
		$this->go_to( get_permalink( $newer ) );
		$html = $this->render( $after, $newer );
		$this->assertStringContainsString( 'wp-block-post-author', $html, 'The author box stays' );
		$this->assertStringNotContainsString( '<nav', $html, 'Nothing to link to: no landmark' );
		$this->assertStringNotContainsString( 'Keep reading', $html, 'Nor its heading' );

		$words = '<!-- wp:group {"tagName":"nav"} --><nav class="wp-block-group"><p>Soon</p></nav><!-- /wp:group -->';
		$this->assertSame( '', do_blocks( $words ), 'A navigation landmark without links is left out, whatever it says' );
		$linked = '<!-- wp:group {"tagName":"nav"} --><nav class="wp-block-group"><p><a href="/about/">About</a></p></nav><!-- /wp:group -->';
		$this->assertStringContainsString( '<a href="/about/">About</a>', do_blocks( $linked ), 'One with a link stays' );
	}

	/**
	 * "Keep reading" lists up to three recent articles from the article's own categories (any of
	 * them), newest first, never the article itself or one from other categories; a pinned post
	 * takes no special place. Without any, the list is left out and the cards stand alone.
	 */
	public function test_related_articles(): void {
		$teaching = self::factory()->category->create( array( 'name' => 'Teaching' ) );
		$forms    = self::factory()->category->create( array( 'name' => 'Forms' ) );
		$other    = self::factory()->category->create( array( 'name' => 'Other' ) );
		$post     = static fn( string $title, string $date, array $categories ): array => array(
			'post_title'    => $title,
			'post_date'     => $date,
			'post_category' => $categories,
		);
		$current  = $this->publish_post( $post( 'Reading corners', '2026-03-01 08:00:00', array( $teaching, $forms ) ) );
		$oldest   = $this->publish_post( $post( 'Seating plans', '2026-01-05 08:00:00', array( $teaching ) ) );
		$this->publish_post( $post( 'Class rules', '2026-01-10 08:00:00', array( $teaching ) ) );
		$this->publish_post( $post( 'Forms to keep', '2026-02-10 08:00:00', array( $forms ) ) );
		$newest = $this->publish_post( $post( 'Homework habits', '2026-04-01 08:00:00', array( $teaching ) ) );
		$budget = $this->publish_post( $post( 'Budget season', '2026-02-20 08:00:00', array( $other ) ) );
		stick_post( $oldest );
		$after = '<!-- wp:pattern {"slug":"tala-blog/post-footer"} /-->';

		$this->go_to( get_permalink( $current ) );
		$html = $this->render( $after, $current );
		preg_match_all( '#<h3 class="wp-block-post-title"><a href="[^"]*"[^>]*>([^<]*)</a></h3>#', $html, $titles );
		$this->assertSame( array( 'Homework habits', 'Forms to keep', 'Class rules' ), $titles[1], 'Newest three of its categories; the pinned oldest one takes no special place' );
		$this->assertStringNotContainsString( '>Budget season<', substr( $html, 0, (int) strpos( $html, 'tala-post-nav__links' ) ), 'Other categories are not related (the older-article card below may still be one)' );
		$this->assertStringContainsString( 'tala-list-thumb', $html, 'With thumbnails' );
		$this->assertStringContainsString( 'Newer article</span> <span class="post-navigation-link__title">Homework habits', $html, 'The cards follow' );
		$this->assertStringNotContainsString( 'wp-block-post-date__modified-date', $html );

		$this->go_to( get_permalink( $newest ) );
		$html = $this->render( $after, $newest );
		$this->assertStringContainsString( '>Reading corners<', $html, 'Related to the newest one too' );

		$alone = $this->publish_post( $post( 'Budget season two', '2026-05-01 08:00:00', array( $other ) ) );
		wp_delete_post( $budget, true );
		$this->go_to( get_permalink( $alone ) );
		$html = $this->render( $after, $alone );
		$this->assertStringNotContainsString( 'wp-block-query', $html, 'No article of its category: the list is left out' );
		$this->assertStringContainsString( 'Older article', $html, 'The cards stay' );
	}

	/**
	 * Global styles a site administrator saved can hold a palette entry that isn't a list, or a
	 * colour that isn't text (a multisite administrator's styles are saved as written). The theme
	 * colour and the dark-colour lookup take them in their stride; a page never fails over them.
	 */
	public function test_damaged_palette_is_harmless(): void {
		$damaged = array(
			'version'  => 3,
			'settings' => array(
				'color' => array(
					'palette' => array(
						'theme' => array(
							array(
								'slug'  => 'base',
								'color' => array( '#fcfcfc' ),
							),
							'contrast',
							array( 'slug' => 'accent' ),
						),
					),
				),
			),
		);
		$result  = tala_blog_preset_dark_colors( new WP_Theme_JSON_Data( $damaged, 'custom' ) );
		$this->assertInstanceOf( WP_Theme_JSON_Data::class, $result );
		$this->assertArrayNotHasKey( 'custom', $result->get_data()['settings'], 'No preset matches a damaged palette' );

		$inject = static fn( $theme_json ) => $theme_json->update_with( $damaged );
		add_filter( 'wp_theme_json_data_user', $inject );
		WP_Theme_JSON_Resolver::clean_cached_data();
		$meta = get_echo( 'tala_blog_theme_color' );
		remove_filter( 'wp_theme_json_data_user', $inject );
		WP_Theme_JSON_Resolver::clean_cached_data();
		$this->assertStringNotContainsString( 'prefers-color-scheme: light', $meta, 'No light colour to announce' );
		$this->assertStringContainsString( 'content="#121212" media="(prefers-color-scheme: dark)"', $meta, "The theme's own dark colour" );
	}

	/**
	 * The theme's style variations: three colour presets and two typography presets.
	 */
	public function test_style_variations_are_offered(): void {
		$this->assertEqualsCanonicalizing(
			array( 'Blue', 'Green', 'Sepia', 'Serif', 'Serif headings' ),
			wp_list_pluck( WP_Theme_JSON_Resolver::get_style_variations(), 'title' )
		);
	}

	/**
	 * The theme's colours and each colour preset: the six colours, in light and in dark mode, with
	 * text at 6:1 or more on the page and on the washed background, as the readme says; and the
	 * presets hold only colours, so the Site Editor lists them as palettes (their dark colours
	 * included), not as whole styles.
	 */
	public function test_colour_presets(): void {
		$theme = self::theme_json( 'theme.json' );
		$names = wp_list_pluck( $theme['settings']['color']['palette'], 'name', 'slug' );
		$files = (array) glob( get_theme_file_path( 'styles/colors/*.json' ) );
		$this->assertCount( 3, $files );

		$palettes = array( 'theme.json' => $theme );
		foreach ( $files as $file ) {
			$name   = basename( (string) $file );
			$preset = self::theme_json( 'styles/colors/' . $name );
			$parts  = array_intersect_key( $preset, array_flip( array( 'settings', 'styles' ) ) );
			$this->assertSame( $parts, self::only( $parts, array( 'color' ) ), "$name: colours only" );
			$this->assertSame( $names, wp_list_pluck( $preset['settings']['color']['palette'], 'name', 'slug' ), "$name: the theme's colour names" );
			$palettes[ $name ] = $preset;
		}

		$pairs = array( array( 'contrast', 'base' ), array( 'muted', 'base' ), array( 'accent', 'base' ), array( 'contrast', 'wash' ), array( 'muted', 'wash' ), array( 'accent', 'wash' ) );
		foreach ( $palettes as $name => $data ) {
			$schemes = array(
				'light' => wp_list_pluck( $data['settings']['color']['palette'], 'color', 'slug' ),
				'dark'  => (array) ( $data['settings']['custom']['color']['dark'] ?? array() ),
			);
			foreach ( $schemes as $scheme => $colors ) {
				$this->assertEqualsCanonicalizing( array_keys( $names ), array_keys( $colors ), "$name, $scheme: six colours" );
				foreach ( $colors as $color ) {
					$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/i', $color, "$name, $scheme" );
				}
				foreach ( $pairs as list( $text, $background ) ) {
					$this->assertGreaterThanOrEqual( 6, self::contrast( $colors[ $text ], $colors[ $background ] ), "$name, $scheme: $text on $background" );
				}
			}
		}
	}

	/**
	 * Typography presets use only the theme's system fonts (nothing to download) and set only
	 * typography, so the Site Editor lists them as typography presets. Each lists the theme's font
	 * families as they are, and names its fonts as CSS variables: the Site Editor's preview of a
	 * preset reads the fonts from the preset itself, in that form.
	 */
	public function test_typography_presets(): void {
		$theme    = self::theme_json( 'theme.json' )['settings']['typography']['fontFamilies'];
		$families = wp_list_pluck( $theme, 'slug' );
		$files    = (array) glob( get_theme_file_path( 'styles/typography/*.json' ) );
		$this->assertCount( 2, $files );
		foreach ( $files as $file ) {
			$name   = basename( (string) $file );
			$preset = self::theme_json( 'styles/typography/' . $name );
			$parts  = array_intersect_key( $preset, array_flip( array( 'settings', 'styles' ) ) );
			$this->assertSame( $parts, self::only( $parts, array( 'typography' ) ), "$name: typography only" );
			$this->assertSame( array( 'typography' => array( 'fontFamilies' => $theme ) ), $preset['settings'] ?? null, "$name: the theme's font families, no others" );
			$this->assertStringNotContainsString( 'var:preset', (string) wp_json_encode( $preset ), "$name: fonts as CSS variables" );
			preg_match_all( '/var\(--wp--preset--font-family--([a-z0-9-]+)\)/', (string) wp_json_encode( $preset, JSON_UNESCAPED_SLASHES ), $used );
			$this->assertNotEmpty( $used[1], $name );
			$this->assertSame( array(), array_diff( $used[1], $families ), "$name: the theme's font families" );
		}
	}

	/**
	 * The browser's theme colour and the dark-mode colours follow a chosen colour preset, also when
	 * the person who chose it can't save unfiltered HTML: WordPress then keeps only the palette, and
	 * the dark colours come from the preset's file. A palette changed after choosing a preset is the
	 * site's own, with the theme's dark colours.
	 */
	public function test_dark_colours_follow_a_colour_preset(): void {
		$sepia     = self::theme_json( 'styles/colors/sepia.json' );
		$dark_base = $sepia['settings']['custom']['color']['dark']['base'];
		$light     = '<meta name="theme-color" content="' . wp_list_pluck( $sepia['settings']['color']['palette'], 'color', 'slug' )['base'] . '" media="(prefers-color-scheme: light)">';
		$dark      = '<meta name="theme-color" content="' . $dark_base . '" media="(prefers-color-scheme: dark)">';
		try {
			foreach ( array(
				'unfiltered' => false,
				'filtered'   => true,
			) as $case => $filtered ) {
				$this->choose_variation( $sepia, $filtered );
				$meta = get_echo( 'tala_blog_theme_color' );
				$this->assertStringContainsString( $light, $meta, $case );
				$this->assertStringContainsString( $dark, $meta, $case );
				$this->assertStringContainsString( '--wp--custom--color--dark--base: ' . $dark_base, wp_get_global_stylesheet( array( 'variables' ) ), $case );
			}

			$changed = $sepia;
			$changed['settings']['color']['palette'][0]['color'] = '#ffffff';
			$this->choose_variation( $changed, true );
			$this->assertStringContainsString( 'content="#121212" media="(prefers-color-scheme: dark)"', get_echo( 'tala_blog_theme_color' ) );
		} finally {
			WP_Theme_JSON_Resolver::clean_cached_data();
			wp_clean_theme_json_cache();
		}
	}

	/**
	 * Article lists show numbered pages only when Appearance → Customize → Article lists asks for
	 * them; the Customizer offers the switch, off by default.
	 */
	public function test_page_numbers_are_a_choice(): void {
		$numbers = '<div class="wp-block-query-pagination-numbers"><span class="page-numbers current">1</span><a class="page-numbers" href="/page/388/">388</a></div>';
		$this->assertSame( '', tala_blog_page_numbers( $numbers ), 'Off by default.' );
		set_theme_mod( 'tala_blog_page_numbers', true );
		$this->assertSame( $numbers, tala_blog_page_numbers( $numbers ) );
		remove_theme_mod( 'tala_blog_page_numbers' );
		$this->assertSame( 10, has_filter( 'render_block_core/query-pagination-numbers', 'tala_blog_page_numbers' ) );

		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$manager = new WP_Customize_Manager();
		tala_blog_customize_register( $manager );
		$this->assertNotNull( $manager->get_section( 'tala_blog_lists' ) );
		$this->assertEmpty( $manager->get_setting( 'tala_blog_page_numbers' )->default, 'Off by default.' );
		$this->assertSame( 'checkbox', $manager->get_control( 'tala_blog_page_numbers' )->type );
	}
}
