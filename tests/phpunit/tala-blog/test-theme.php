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
	 * The browser's theme colour follows theme.json in light and dark mode: the paper of each.
	 */
	public function test_theme_colour(): void {
		$meta = get_echo( 'tala_blog_theme_color' );
		$this->assertStringContainsString( '<meta name="theme-color" content="#f4f4f2" media="(prefers-color-scheme: light)">', $meta );
		$this->assertStringContainsString( '<meta name="theme-color" content="#16181d" media="(prefers-color-scheme: dark)">', $meta );
	}

	/**
	 * The Light/Dark switch. A small script in <head>, printed before any CSS, applies the reader's
	 * stored choice (only "light" or "dark", read inside try: storage can be blocked) and marks the
	 * page as running JavaScript; it sets the button's state (aria-pressed) and never its name. The
	 * header has the button: one name, "Dark theme" (translatable), its state and two decorative icons.
	 */
	public function test_theme_switch(): void {
		add_filter( 'gettext_with_context_tala-blog', static fn( $translation, $text ) => 'Dark theme' === $text ? "($text)" : $translation, 10, 2 );

		// A fresh style queue, so the theme's styles print in this <head> whatever printed before.
		$styles               = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_styles'] = new WP_Styles();
		$head                 = get_echo( 'wp_head' );
		$GLOBALS['wp_styles'] = $styles;
		$tag                  = '<script id="tala-blog-theme-switch-js">';
		$this->assertSame( 1, substr_count( $head, $tag ) );
		$at = (int) strpos( $head, $tag );
		$this->assertLessThan( strpos( $head, '<style' ), $at, 'Before any styles' );
		$this->assertLessThan( strpos( $head, 'rel="preload"' ), $at, 'Before the font preloads' );
		if ( preg_match( '/<link[^>]+rel=["\']stylesheet/', $head, $link, PREG_OFFSET_CAPTURE ) ) {
			$this->assertLessThan( $link[0][1], $at, 'Before any stylesheet' );
		}

		$this->assertSame( 1, preg_match( '#' . preg_quote( $tag, '#' ) . '(.*?)</script>#s', $head, $script ) );
		$js = $script[1];
		$this->assertLessThan( 1024, strlen( $js ), 'A tiny script' );
		$this->assertMatchesRegularExpression( '/try\s*\{\s*(\w+)\s*=\s*localStorage\.getItem\("tala-theme"\)/', $js, 'Reads the choice inside try' );
		$this->assertMatchesRegularExpression( '/try\s*\{\s*localStorage\.setItem\("tala-theme",/', $js, 'Stores the choice inside try' );
		$this->assertMatchesRegularExpression( '/if\s*\(\s*"light"\s*===\s*(\w+)\s*\|\|\s*"dark"\s*===\s*\1\s*\)\s*\{?\s*\w+\.dataset\.theme\s*=\s*\1\b/', $js, 'Only "light" or "dark" becomes the theme' );
		$this->assertMatchesRegularExpression( '/\.add\("tala-js"\)/', $js, '<html> gets the class tala-js' );
		$this->assertMatchesRegularExpression( '/\.setAttribute\("aria-pressed",/', $js, 'Sets the state' );
		$this->assertStringNotContainsString( 'aria-label', $js, 'Never changes the name' );
		$this->assertStringNotContainsString( 'Dark theme', $js );

		// The pattern file itself: rendering the header here would leave its translated text in the
		// pattern registry's cache, and number the next "Primary" menu "Primary 2", for later tests.
		$header = get_echo(
			static function (): void {
				include get_theme_file_path( 'patterns/header.php' );
			}
		);
		$this->assertStringContainsString( "<!-- wp:html -->\n<button", $header, 'An HTML block' );
		$this->assertSame( 1, preg_match( '#<button type="button" class="tala-theme-switch" aria-pressed="false" aria-label="\(Dark theme\)">(.*?)</button>#s', $header, $button ), 'The switch, named once, not pressed until the script knows the theme' );
		$this->assertSame( 2, preg_match_all( '#<svg[^>]* aria-hidden="true" focusable="false"#', $button[1] ), 'A sun and a moon, decorative' );
		$this->assertStringContainsString( 'tala-theme-switch__sun', $button[1] );
		$this->assertStringContainsString( 'tala-theme-switch__moon', $button[1] );
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
	 * "On this page": after the opening paragraph, before the first section, a disclosure that starts
	 * closed ("On this page", "N sections") and opens into a navigation landmark of the same name,
	 * which links each section (numbered, the numbers hidden from screen readers) and its
	 * subsections to an anchor that describes it. Anchors a heading has are kept (unless used twice, or the classic editor's);
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

		$this->assertSame( 1, preg_match( '#Opening paragraph\.</p>\s*<details class="tala-contents"><summary>(.*?)</summary><nav aria-label="On this page"><ol>#s', $html, $summary ), 'After the opening, closed.' );
		$this->assertStringContainsString( '<span class="tala-contents-title">On this page</span> <span class="tala-contents-count">6 sections</span>', $summary[1] );
		$this->assertSame( 2, preg_match_all( '#<svg[^>]* aria-hidden="true" focusable="false"#', $summary[1] ), 'A list icon and a chevron, decorative' );
		$num = static fn( string $n ): string => '<span class="tala-contents-num" aria-hidden="true">' . $n . '</span>';
		$this->assertStringContainsString(
			'<ol><li><a href="#plan-the-week">' . $num( '01' ) . 'Plan the week</a><ol><li><a href="#monday-tuesday">Monday &amp; Tuesday</a></li></ol></li>'
			. '<li><a href="#kept-anchor">' . $num( '02' ) . 'Check the forms</a></li>'
			. '<li><a href="#plan-the-week-2">' . $num( '03' ) . 'Plan the week</a></li>'
			. '<li><a href="#comments-2">' . $num( '04' ) . 'Comments</a></li>'
			. '<li><a href="#left-by-the-classic-editor">' . $num( '05' ) . 'Left by the classic editor</a></li>'
			. '<li><a href="#an-id-used-twice">' . $num( '06' ) . 'An id used twice</a></li></ol></nav></details>',
			$html
		);
		$this->assertStringNotContainsString( '<details class="tala-contents" open', $html, 'Closed at every width' );
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
		$this->assertMatchesRegularExpression( '#Opening\.</p>\s*<details class="tala-contents"[^>]*>.*</details><div class="wp-block-group[^"]*">\s*<h2 id="one" class="wp-block-heading">One</h2>#s', $this->render( '<!-- wp:post-content /-->', $post ) );

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
	 * The featured image isn't shown on its own article or page, also when the text repeats it in an
	 * Image block (at the top, further down or in a gallery). The text's other images stay, and the
	 * same text shown anywhere else keeps it.
	 */
	public function test_featured_image_is_not_repeated_in_the_text(): void {
		$other    = self::upload_image( self::factory(), 'cover-2.jpg', 'Another picture' );
		$image    = static fn( int $id ): string => sprintf(
			"<!-- wp:image {\"id\":%1\$d,\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"%2\$s\" alt=\"\" class=\"wp-image-%1\$d\"/><figcaption class=\"wp-element-caption\">Caption %1\$d</figcaption></figure>\n<!-- /wp:image -->\n\n",
			$id,
			wp_get_attachment_image_url( $id, 'large' )
		);
		$featured = self::$image_id;
		$content  = $image( $featured ) . "<!-- wp:paragraph -->\n<p>Opening.</p>\n<!-- /wp:paragraph -->\n\n" . $image( $other )
			. "<!-- wp:gallery {\"linkTo\":\"none\"} -->\n<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\">" . $image( $featured ) . $image( $other ) . "</figure>\n<!-- /wp:gallery -->\n\n"
			. "<!-- wp:paragraph -->\n<p>Closing.</p>\n<!-- /wp:paragraph -->";
		$post     = $this->publish_post( array( 'post_content' => $content ) );
		$this->assertSame( $featured, (int) get_post_thumbnail_id( $post ) );

		$this->go_to( get_permalink( $post ) );
		$html = $this->render( '<!-- wp:post-content /-->', $post );
		$this->assertStringNotContainsString( 'wp-image-' . $featured, $html, 'Its own article: not at the top, not in the gallery.' );
		$this->assertStringNotContainsString( 'Caption ' . $featured, $html, 'Its caption goes with it.' );
		$this->assertSame( 2, substr_count( $html, 'wp-image-' . $other ), 'The other images stay.' );
		$this->assertMatchesRegularExpression( '#^<div[^>]*class="[^"]*wp-block-post-content[^"]*"[^>]*>\s*<p(?: [^>]*)?>Opening\.</p>#', $html, 'The text starts with its first paragraph.' );
		$this->assertStringContainsString( 'Closing.</p>', $html );

		$page = $this->publish_post(
			array(
				'post_type'    => 'page',
				'post_content' => $content,
				'meta_input'   => array( '_thumbnail_id' => $featured ),
			)
		);
		$this->go_to( get_permalink( $page ) );
		$this->assertStringNotContainsString( 'wp-image-' . $featured, $this->render( '<!-- wp:post-content /-->', $page ), 'A page too.' );

		$another = $this->publish_post( array( 'post_content' => $image( $featured ) . "<!-- wp:paragraph -->\n<p>Text.</p>\n<!-- /wp:paragraph -->" ) );
		$this->go_to( get_permalink( $post ) );
		$this->assertStringContainsString( 'wp-image-' . $featured, $this->render( '<!-- wp:post-content /-->', $another ), 'Another post\'s text, shown on this article, keeps it.' );
		$this->go_to( home_url( '/' ) );
		$this->assertStringContainsString( 'wp-image-' . $featured, $this->render( '<!-- wp:post-content /-->', $post ), 'Not on its own page: as it is.' );
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
	 * Reading time: the words of the article's text (the markup, block delimiters and shortcodes
	 * stripped), at 220 a minute, rounded up, at least a minute. Words are split at whitespace, so a
	 * Filipino word with "ñ" is one word. The post isn't rendered for it (a form in it would count a
	 * second view). The tala-blog/reading-time binding writes it as "N min read".
	 */
	public function test_reading_minutes(): void {
		$paragraph = static fn( string $words ): string => "<!-- wp:paragraph -->\n<p>" . $words . "</p>\n<!-- /wp:paragraph -->";
		$words     = static fn( int $count, string $word = 'salita' ): string => trim( str_repeat( $word . ' ', $count ) );
		$minutes   = function ( string $content ): int {
			return tala_blog_reading_minutes( $this->publish_post( array( 'post_content' => $content ) ) );
		};

		$this->assertSame( 1, $minutes( '' ), 'No text: still a minute' );
		$this->assertSame( 1, $minutes( $paragraph( $words( 220 ) ) ), '220 words, the block comments not counted' );
		$this->assertSame( 2, $minutes( $paragraph( $words( 221 ) ) ), 'Rounded up' );
		$this->assertSame( 3, $minutes( $paragraph( $words( 300 ) ) . $paragraph( $words( 360 ) ) ) );
		$this->assertSame( 2, $minutes( $paragraph( $words( 221, 'niño' ) ) ), 'A word with "ñ" is one word' );
		$this->assertSame( 1, $minutes( $paragraph( '<strong>Isa</strong>, <em>dalawa</em>' ) ), 'Markup is not text' );

		$post = $this->publish_post( array( 'post_content' => $paragraph( $words( 441 ) ) ) );
		$this->assertSame( 3, tala_blog_reading_minutes( get_post( $post ) ), 'A post object too' );
		$this->assertTrue( get_block_bindings_source( 'tala-blog/reading-time' ) instanceof WP_Block_Bindings_Source );
		$bound = '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"tala-blog/reading-time"}}}} --><p></p><!-- /wp:paragraph -->';
		$this->assertMatchesRegularExpression( '#^<p[^>]*>3 min read</p>$#', trim( $this->render( $bound, $post ) ) );

		// The post isn't rendered to count it: its dynamic blocks don't run, its shortcodes aren't words.
		$renders = 0;
		register_block_type(
			'tala-test/counter',
			array(
				'render_callback' => static function () use ( &$renders ): string {
					++$renders;
					return '<p>' . str_repeat( 'salita ', 500 ) . '</p>';
				},
			)
		);
		try {
			$dynamic = $this->publish_post( array( 'post_content' => $paragraph( $words( 5 ) ) . '<!-- wp:tala-test/counter /-->' . $bound . '[gallery ids="1,2,3"]' ) );
			$this->assertSame( 1, tala_blog_reading_minutes( $dynamic ) );
			$this->assertSame( 0, $renders, 'Not rendered' );
			$this->assertSame( 1, tala_blog_reading_minutes( $this->publish_post( array( 'post_content' => $paragraph( $words( 219 ) . ' [gallery ids="1,2,3"]' ) ) ) ), 'A shortcode is no words' );
		} finally {
			unregister_block_type( 'tala-test/counter' );
		}

		// A post behind a password has no reading time until the password is entered.
		$locked = $this->publish_post(
			array(
				'post_content'  => $paragraph( $words( 441 ) ),
				'post_password' => 'secret',
			)
		);
		$this->assertTrue( post_password_required( $locked ) );
		$reading          = new WP_Block( parse_blocks( $bound )[0] );
		$reading->context = array( 'postId' => $locked );
		$this->assertSame( '', tala_blog_reading_time( array(), $reading ) );
		$main = $this->single_main( $locked );
		$this->assertStringNotContainsString( 'min read', $main );
		$this->assertStringNotContainsString( 'tala-reading-time', $main, 'Nor an empty paragraph' );
	}

	/**
	 * The article's main element as the single template renders it, for the post being read.
	 *
	 * @param int $post Post.
	 * @return string
	 */
	private function single_main( int $post ): string {
		$this->go_to( get_permalink( $post ) );
		$GLOBALS['post'] = get_post( $post );
		setup_postdata( $GLOBALS['post'] );
		$blocks = array_values( array_filter( parse_blocks( get_block_template( 'tala-blog//single' )->content ), static fn( $block ) => null !== $block['blockName'] ) );
		return render_block( $blocks[1] );
	}

	/**
	 * An article opens with its title band, as NASH's posts do: the article's header, a full-width
	 * graph-paper band holding the breadcrumbs (Tala SEO's), the first category as eyebrow, the title
	 * in display type, the excerpt as lead, "By {author}" and "Published · Updated · N min read".
	 * After the text, its footer lists the topics (categories and tags) as chips.
	 */
	public function test_title_band(): void {
		$teaching = self::factory()->category->create( array( 'name' => 'Teaching' ) );
		$forms    = self::factory()->category->create( array( 'name' => 'Assessment' ) );
		$author   = self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Ana Reyes',
			)
		);
		$post     = $this->publish_post(
			array(
				'post_title'    => 'Seating plans',
				'post_excerpt'  => 'A plan for every room.',
				'post_content'  => "<!-- wp:paragraph -->\n<p>" . trim( str_repeat( 'salita ', 400 ) ) . "</p>\n<!-- /wp:paragraph -->",
				'post_author'   => $author,
				'post_date'     => '2026-03-01 08:00:00',
				'post_category' => array( $teaching, $forms ),
			)
		);
		global $wpdb;
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

		$main = $this->single_main( $post );
		$this->assertMatchesRegularExpression( '#^<main[^>]*>\s*<article class="wp-block-group tala-article[ "][^>]*>\s*<header class="wp-block-group tala-band tala-band--paper[ "]#', trim( $main ), 'The article\'s header is its first band, on graph paper' );
		$band = substr( $main, 0, (int) strpos( $main, '</header>' ) );
		$this->assertStringNotContainsString( 'wp-block-post-content', $band );
		$this->assertSame( 1, substr_count( $main, '<h1' ) );

		$order = array(
			'breadcrumbs' => strpos( $band, 'wp-block-tala-seo-breadcrumbs' ),
			'eyebrow'     => strpos( $band, 'tala-eyebrow' ),
			'title'       => strpos( $band, '<h1' ),
			'lead'        => strpos( $band, 'tala-lead' ),
			'byline'      => strpos( $band, 'tala-byline' ),
			'published'   => strpos( $band, '>Published<' ),
			'updated'     => strpos( $band, '>Updated<' ),
			'reading'     => strpos( $band, '2 min read' ),
		);
		$this->assertNotContains( false, $order, implode( ', ', array_keys( $order, false, true ) ) );
		$sorted = $order;
		asort( $sorted );
		$this->assertSame( array_keys( $order ), array_keys( $sorted ), 'Breadcrumbs, eyebrow, title, lead, byline, dates, reading time' );

		$this->assertSame( 1, preg_match( '#<div class="[^"]*\btala-eyebrow\b[^"]*">(.*?)</div>#s', $band, $eyebrow ) );
		$this->assertSame( 1, substr_count( $eyebrow[1], '<a ' ), 'The first category only' );
		$this->assertStringContainsString( '>Assessment</a>', $eyebrow[1] );
		$this->assertMatchesRegularExpression( '#<h1 class="(?=[^"]*\bwp-block-post-title\b)(?=[^"]*\btala-display\b)[^"]*">Seating plans</h1>#', $band );
		$this->assertMatchesRegularExpression( '#class="[^"]*\btala-lead\b[^"]*"><p class="wp-block-post-excerpt__excerpt">A plan for every room. </p>#', $band );
		$this->assertMatchesRegularExpression( '#>By <a href="' . preg_quote( get_author_posts_url( $author ), '#' ) . '" rel="author"[^>]*>Ana Reyes</a><#', $band );
		$byline = new WP_Block( parse_blocks( '<!-- wp:post-author-name {"className":"tala-byline"} /-->' )[0], array( 'postId' => $post ) );
		$this->assertSame( '<span>Ana Reyes</span>', tala_blog_byline( '<span>Ana Reyes</span>', $byline->parsed_block, $byline ), 'Markup of another shape (another plugin\'s) is left as it is' );
		$this->assertMatchesRegularExpression( '#<p class="[^"]*\btala-reading-time\b[^"]*">2 min read</p>#', $band );

		// After the text, its footer: "Topics", every category as a chip.
		$this->assertSame( 1, preg_match( '#<footer class="wp-block-group tala-terms[ "].*?</footer>#s', $main, $topics ) );
		$this->assertLessThan( strpos( $main, 'tala-terms' ), strpos( $main, 'wp-block-post-content' ), 'After the text' );
		$this->assertMatchesRegularExpression( '#<p class="[^"]*\btala-eyebrow\b[^"]*">Topics</p>\s*<div class="wp-block-group tala-chips[ "]#', $topics[0] );
		$this->assertStringContainsString( '>Assessment</a>', $topics[0] );
		$this->assertStringContainsString( '>Teaching</a>', $topics[0] );
	}

	/**
	 * A post without a category, without an excerpt (nor a description for Tala SEO to give as
	 * one) and by an author without a name (the Theme Unit Test has such posts) gets no empty
	 * eyebrow, lead or byline: they are left out. The text's opening isn't repeated as a lead. Without
	 * a category or tag, the "Topics" footer is left out too.
	 */
	public function test_title_band_without_optional_parts(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- WordPress itself never saves an empty display name.
		$wpdb->update( $wpdb->users, array( 'display_name' => '' ), array( 'ID' => $author ) );
		clean_user_cache( $author );
		$post = $this->publish_post(
			array(
				'post_title'   => 'Bare post',
				'post_excerpt' => '',
				'post_content' => "<!-- wp:paragraph -->\n<p>Opening words of the text.</p>\n<!-- /wp:paragraph -->",
				'post_author'  => $author,
			)
		);
		wp_set_object_terms( $post, array(), 'category' );
		delete_post_meta( $post, TALA_SEO_DESCRIPTION );
		$this->assertSame( array(), wp_get_post_categories( $post ) );

		$main = $this->single_main( $post );
		$band = substr( $main, 0, (int) strpos( $main, '</header>' ) );
		$this->assertStringContainsString( '>Bare post</h1>', $band );
		$this->assertStringContainsString( '>Published<', $band );
		$this->assertStringContainsString( '1 min read', $band );
		foreach ( array( 'tala-eyebrow', 'tala-lead', 'tala-byline', 'wp-block-post-terms', 'wp-block-post-excerpt', 'rel="author"', 'Opening words' ) as $missing ) {
			$this->assertStringNotContainsString( $missing, $band, $missing );
		}
		$this->assertDoesNotMatchRegularExpression( '#<(p|div|a)\b[^>]*>\s*</\1>#', $band, 'No empty element' );
		$this->assertStringNotContainsString( 'tala-terms', $main, 'No category or tag: no topics, label and all' );
	}

	/**
	 * Without a lead of its own, WordPress doesn't cut one from the text just to leave it out: the
	 * text goes through the_content once, for the article, so what plugins add to it (ads) runs once.
	 * With one, the lead shows and the text still goes through once.
	 */
	public function test_lead_reads_the_text_once(): void {
		$post = $this->publish_post(
			array(
				'post_excerpt' => '',
				'post_content' => "<!-- wp:paragraph -->\n<p>Opening words of the text.</p>\n<!-- /wp:paragraph -->",
			)
		);
		delete_post_meta( $post, TALA_SEO_DESCRIPTION );
		$calls = 0;
		$count = static function ( $content ) use ( &$calls ) {
			++$calls;
			return $content;
		};
		add_filter( 'the_content', $count );
		$main = $this->single_main( $post );
		$this->assertSame( 1, $calls, 'No lead: once, for the article.' );
		$this->assertStringNotContainsString( 'tala-lead', $main );

		update_post_meta( $post, TALA_SEO_DESCRIPTION, 'A lead from the description.' );
		$calls = 0;
		$main  = $this->single_main( $post );
		remove_filter( 'the_content', $count );
		$this->assertSame( 1, $calls, 'A lead: still once.' );
		$this->assertStringContainsString( 'A lead from the description.', $main );
	}

	/**
	 * A Table block is a labelled region keyboard users can scroll, as NASH's tables are: a wide
	 * table scrolls sideways in its own box. Named by its caption, or "Table".
	 */
	public function test_table_scroll_region(): void {
		$table = '<!-- wp:table {"hasFixedLayout":false} --><figure class="wp-block-table"><table><tbody><tr><td>1</td></tr></tbody></table>%s</figure><!-- /wp:table -->';
		$plain = trim( do_blocks( sprintf( $table, '' ) ) );
		$this->assertMatchesRegularExpression( '#^<figure (?=[^>]*\bclass="wp-block-table")(?=[^>]*\brole="region")(?=[^>]*\baria-label="Table")(?=[^>]*\btabindex="0")[^>]*><table>#', $plain );
		$named = trim( do_blocks( sprintf( $table, '<figcaption class="wp-element-caption">Grades <em>by</em> quarter</figcaption>' ) ) );
		$this->assertStringContainsString( 'aria-label="Grades by quarter"', $named );
		$this->assertStringNotContainsString( 'tabindex', do_blocks( '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' ) );
	}

	/**
	 * A classic table (the classic editor's, a paste from a word processor) gets the same scrolling
	 * region as a Table block: wrapped once in a box that keyboard users can focus, named by its
	 * caption or "Table". The table stays a table, its own width (an inline width too) kept. A Table
	 * block's table, already in its region, and a table inside another table aren't wrapped again.
	 */
	public function test_classic_table_region(): void {
		$region = static fn( string $name ): string => '<div class="tala-table-scroll" role="region" tabindex="0" aria-label="' . $name . '">';
		$table  = '<table style="width: 100%;"><tbody><tr><td>1</td><td><table><tbody><tr><td>2</td></tr></tbody></table></td></tr></tbody></table>';
		$html   = apply_filters( 'the_content', "Before.\n\n" . $table . "\n\nAfter." );
		$this->assertSame( 1, substr_count( $html, 'tala-table-scroll' ), 'Once, around the outer table only' );
		$this->assertStringContainsString( $region( 'Table' ) . '<table style="width: 100%;">', $html, 'The table as it was, inline width and all' );
		$this->assertSame( 1, substr_count( $html, '</table></div>' ) );
		$this->assertSame( strrpos( $html, '</table>' ), strpos( $html, '</table></div>' ), 'Closed after the outer table, the inner one inside it' );
		$this->assertSame( $html, tala_blog_classic_table_region( $html ), 'Never wrapped twice' );

		$captioned = '<table><caption>Fees &amp; <em>dues</em></caption><tbody><tr><td>1</td></tr></tbody></table>';
		$this->assertStringContainsString( $region( 'Fees &amp; dues' ) . $captioned . '</div>', tala_blog_classic_table_region( $captioned ) );

		$block = apply_filters( 'the_content', '<!-- wp:table {"hasFixedLayout":false} --><figure class="wp-block-table"><table><tbody><tr><td>1</td></tr></tbody></table></figure><!-- /wp:table -->' );
		$this->assertStringNotContainsString( 'tala-table-scroll', $block, 'A Table block is its own region' );
		$this->assertSame( 1, substr_count( $block, 'role="region"' ) );
		$this->assertSame( 'No table.', tala_blog_classic_table_region( 'No table.' ) );
	}

	/**
	 * Article lists are ruled rows without pictures: on the left the date and the post's first
	 * category (one link, however many it has), on the right the linked title and the summary. Only
	 * a Categories block that asks for it (tala-first-term) is cut down to its first term.
	 */
	public function test_list_rows(): void {
		$names = array( 'Curriculum', 'Assessment', 'Budget' );
		$cats  = array_map( static fn( string $name ): int => self::factory()->category->create( array( 'name' => $name ) ), $names );
		$three = $this->publish_post(
			array(
				'post_title'    => 'Seating plans',
				'post_date'     => '2026-02-05 08:00:00',
				'post_category' => $cats,
			)
		);
		$this->publish_post(
			array(
				'post_title' => 'Reading corners',
				'post_date'  => '2026-01-05 08:00:00',
			)
		);
		$this->assertTrue( has_post_thumbnail( $three ), 'The posts have featured images' );

		$this->go_to( home_url( '/' ) );
		$blocks = array_values( array_filter( parse_blocks( get_block_template( 'tala-blog//home' )->content ), static fn( $block ) => null !== $block['blockName'] ) );
		$main   = render_block( $blocks[1] );
		$this->assertStringStartsWith( '<main', trim( $main ) );
		$this->assertStringNotContainsString( '<img', $main, 'No thumbnails' );
		$this->assertStringNotContainsString( 'wp-block-post-featured-image', $main );

		$tags = WP_HTML_Processor::create_fragment( $main );
		$this->assertNotNull( $tags );
		$rows  = array();
		$depth = 0;
		while ( $tags->next_tag() ) {
			if ( $rows && $tags->get_current_depth() <= $depth ) {
				$depth = PHP_INT_MAX; // Out of the row: wait for the next one.
			}
			if ( $tags->has_class( 'tala-row' ) ) {
				$rows[] = array(
					'time'  => 0,
					'terms' => array(),
					'title' => 0,
				);
				$depth  = $tags->get_current_depth();
				continue;
			}
			if ( ! $rows || PHP_INT_MAX === $depth ) {
				continue;
			}
			$row = count( $rows ) - 1;
			if ( 'TIME' === $tags->get_tag() ) {
				++$rows[ $row ]['time'];
			}
			if ( 'H2' === $tags->get_tag() && $tags->has_class( 'wp-block-post-title' ) ) {
				++$rows[ $row ]['title'];
			}
			$href = 'A' === $tags->get_tag() ? (string) $tags->get_attribute( 'href' ) : '';
			if ( str_contains( $href, '/category/' ) ) {
				$rows[ $row ]['terms'][] = $href;
			}
		}
		$this->assertNull( $tags->get_last_error() );
		$this->assertSame(
			array(
				array(
					'time'  => 1,
					'terms' => array( get_category_link( $cats[1] ) ),
					'title' => 1,
				),
				array(
					'time'  => 1,
					'terms' => array( get_category_link( 1 ) ),
					'title' => 1,
				),
			),
			$rows,
			'Each row: a date, the first category only (Assessment of three), a level-two title'
		);
		$this->assertMatchesRegularExpression( '#<div class="wp-block-group tala-row__meta[ "].*?<time\b.*?tala-first-term#s', $main, 'The date, then the category' );
		$this->assertStringContainsString( 'tala-row__text', $main );

		$all = $this->render( '<!-- wp:post-terms {"term":"category"} /-->', $three );
		$this->assertSame( 3, substr_count( $all, '<a ' ), 'Other Categories blocks keep every term' );
		$first = $this->render( '<!-- wp:post-terms {"term":"category","className":"tala-first-term"} /-->', $three );
		$this->assertSame( 1, substr_count( $first, '<a ' ) );
		$this->assertStringContainsString( '>Assessment</a>', $first );
		$this->assertStringNotContainsString( 'wp-block-post-terms__separator', $first );
		$this->assertMatchesRegularExpression( '#^<div class="[^"]*\btala-first-term\b[^"]*"><a [^>]*></a></div>$#', (string) preg_replace( '#>[^<]*</a>#', '></a>', $first ), 'Its wrapper and the link, nothing else' );
		$this->assertSame( '<p>x</p>', tala_blog_first_term( '<p>x</p>', array( 'attrs' => array( 'className' => 'tala-first-term' ) ) ), 'No link: left as it is' );
	}

	/**
	 * The home page opens with a graph-paper band: the site title as the page's display title, the
	 * tagline as its lead, a search field and the top-level categories as chips, in a navigation
	 * landmark named "Browse by topic" by its visible label.
	 */
	public function test_home_header_band(): void {
		$parent = self::factory()->category->create( array( 'name' => 'Teaching' ) );
		$child  = self::factory()->category->create(
			array(
				'name'   => 'Seating',
				'parent' => $parent,
			)
		);
		$this->publish_post( array( 'post_category' => array( $parent, $child ) ) );
		update_option( 'blogname', 'Guro Notes' );
		update_option( 'blogdescription', 'Notes for teachers' );

		$template = get_block_template( 'tala-blog//home' )->content;
		$main     = array_values( array_filter( parse_blocks( $template ), static fn( $block ) => null !== $block['blockName'] ) )[1];
		$band     = $main['innerBlocks'][0];
		$this->assertContains( 'tala-band--paper', explode( ' ', $band['attrs']['className'] ?? '' ) );

		$this->go_to( home_url( '/' ) );
		$html = render_block( $band );
		$this->assertMatchesRegularExpression( '#<h1 class="(?=[^"]*\bwp-block-site-title\b)(?=[^"]*\btala-display\b)[^"]*">Guro Notes</h1>#', $html, 'The site title, not linked, as the display title' );
		$this->assertSame( 1, substr_count( $html, '<h1' ) );
		$this->assertMatchesRegularExpression( '#<p class="(?=[^"]*\bwp-block-site-tagline\b)(?=[^"]*\btala-lead\b)[^"]*">Notes for teachers</p>#', $html );
		$this->assertMatchesRegularExpression( '#<form[^>]* role="search"#', $html, 'A search field' );
		$this->assertStringNotContainsString( 'wp-block-search__button-only', $html, 'A field, not the header\'s button' );
		$this->assertMatchesRegularExpression( '#<ul class="(?=[^"]*\bwp-block-categories\b)(?=[^"]*\btala-chips\b)[^"]*">#', $html, 'Categories as chips' );
		$this->assertStringContainsString( '>Teaching</a>', $html );
		$this->assertMatchesRegularExpression( '#<nav [^>]*aria-labelledby="([^"]+)"[^>]*>\s*<p [^>]*id="\1"[^>]*>Browse by topic</p>.*?tala-chips.*?</nav>#s', $html, 'A navigation landmark named by its visible label, around the chips' );
		$this->assertStringNotContainsString( 'aria-label=', substr( $html, (int) strpos( $html, '<nav' ) ), 'Named once, by the label' );
		$this->assertStringNotContainsString( '>Seating</a>', $html, 'Top-level categories only' );
		$this->assertLessThan( strpos( $html, 'tala-lead' ), strpos( $html, '<h1' ), 'Title, lead, search, chips' );
		$this->assertLessThan( strpos( $html, 'role="search"' ), strpos( $html, 'tala-lead' ) );
		$this->assertLessThan( strpos( $html, 'tala-chips' ), strpos( $html, 'role="search"' ) );
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
	 * Every pattern registers and parses; all but the Closing call to action (offered for the
	 * Call to action part) stay out of the inserter.
	 */
	public function test_patterns(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$files    = glob( dirname( __DIR__, 3 ) . '/tala-blog/patterns/*.php' );
		$this->assertNotEmpty( $files );
		foreach ( $files as $file ) {
			$slug    = 'tala-blog/' . basename( $file, '.php' );
			$pattern = $registry->get_registered( $slug );
			$this->assertIsArray( $pattern, $slug );
			if ( 'tala-blog/call-to-action' !== $slug ) {
				$this->assertFalse( $pattern['inserter'] ?? true, "$slug stays out of the inserter" );
			}
			$this->assertNotEmpty( parse_blocks( $pattern['content'] ), $slug );
		}
		$this->assertStringContainsString( '"slug":"tala-blog/site-brand"', $registry->get_registered( 'tala-blog/header' )['content'] );
		$this->assertStringContainsString( '"slug":"tala-blog/site-brand"', $registry->get_registered( 'tala-blog/navigation-overlay' )['content'] );
	}

	/**
	 * Template parts and their areas. Every template is the frame: the header part, then a main
	 * element of full-width bands (an article's or page's inside its article element), each holding
	 * one rail, then the footer part (the one footer:
	 * search lives in the header). Only search results (to refine the search, under their title) and
	 * the home page's title band keep a search form in the page; the 404 page has one in its text
	 * pattern (tala-blog/hidden-404).
	 */
	public function test_templates_and_parts(): void {
		$areas = array(
			'header'             => 'header',
			'footer'             => 'footer',
			'navigation-overlay' => 'navigation-overlay',
			'footer-note'        => 'uncategorized',
			'call-to-action'     => 'uncategorized',
		);
		foreach ( $areas as $slug => $area ) {
			$part = get_block_template( 'tala-blog//' . $slug, 'wp_template_part' );
			$this->assertInstanceOf( WP_Block_Template::class, $part, $slug );
			$this->assertSame( $area, $part->area, $slug );
		}
		$this->assertNull( get_block_template( 'tala-blog//footer-without-search', 'wp_template_part' ), 'One footer' );
		$classes = static fn( array $block ): array => preg_split( '/\s+/', $block['attrs']['className'] ?? '', -1, PREG_SPLIT_NO_EMPTY );
		foreach ( array( 'home', 'index', 'single', 'page', 'archive', 'author', 'search', '404' ) as $slug ) {
			$content = get_block_template( 'tala-blog//' . $slug )->content;
			$blocks  = array_values( array_filter( parse_blocks( $content ), static fn( $block ) => null !== $block['blockName'] ) );
			// Every page but the 404 closes with the Call to action part, between main and the footer.
			if ( '404' !== $slug ) {
				$this->assertSame( 'core/template-part', $blocks[2]['blockName'] ?? '', "$slug: the call to action" );
				$this->assertSame( 'call-to-action', $blocks[2]['attrs']['slug'] ?? '', "$slug: the call to action" );
				array_splice( $blocks, 2, 1 );
			}
			$this->assertSame( array( 'core/template-part', 'core/group', 'core/template-part' ), array_column( $blocks, 'blockName' ), $slug );
			$this->assertSame( 'header', $blocks[0]['attrs']['slug'], $slug );
			$this->assertSame( 'footer', $blocks[2]['attrs']['slug'], "$slug: the one footer" );

			$main = $blocks[1];
			$this->assertSame( 'main', $main['attrs']['tagName'] ?? '', $slug );
			$this->assertContains( 'tala-main', $classes( $main ), $slug );
			$this->assertSame( 'default', $main['attrs']['layout']['type'] ?? '', "$slug: bands run the full width" );
			$this->assertNotEmpty( $main['innerBlocks'], $slug );
			$bands = $main['innerBlocks'];
			if ( in_array( $slug, array( 'single', 'page' ), true ) ) {
				// An article or page is one article element: its title band (its header), then its text.
				$this->assertCount( 1, $bands, $slug );
				$this->assertSame( 'article', $bands[0]['attrs']['tagName'] ?? '', "$slug: one article" );
				$this->assertSame( 'header', $bands[0]['innerBlocks'][0]['attrs']['tagName'] ?? '', "$slug: its title band is its header" );
				$this->assertContains( 'tala-band--paper', $classes( $bands[0]['innerBlocks'][0] ), "$slug: on graph paper" );
				$bands = $bands[0]['innerBlocks'];
			}
			foreach ( $bands as $band ) {
				$this->assertSame( 'core/group', $band['blockName'], $slug );
				$this->assertContains( 'tala-band', $classes( $band ), "$slug: main holds bands" );
				$this->assertCount( 1, $band['innerBlocks'], "$slug: one rail per band" );
				$this->assertContains( 'tala-rail', $classes( $band['innerBlocks'][0] ), "$slug: each band holds a rail" );
			}
			$this->assertStringNotContainsString( 'footer-without-search', $content, $slug );
			if ( ! in_array( $slug, array( 'search', 'home' ), true ) ) {
				$this->assertStringNotContainsString( '"slug":"tala-blog/search"', $content, "$slug: search is in the header" );
			}
		}
		$this->assertStringContainsString( '"slug":"tala-blog/search"', WP_Block_Patterns_Registry::get_instance()->get_registered( 'tala-blog/hidden-404' )['content'] );
	}

	/**
	 * The header: logo and title, the menu named "Primary" (which opens the theme's overlay on
	 * phones), the site search as a button that opens into a field (the separator before it is CSS),
	 * and the Light/Dark switch, in that order, in a band with a rail like every section of the page.
	 */
	public function test_header(): void {
		$this->publish_post( array( 'post_type' => 'page' ) );
		$header = do_blocks( '<!-- wp:template-part {"slug":"header","theme":"tala-blog","tagName":"header"} /-->' );
		$this->assertMatchesRegularExpression( '/<div class="wp-block-group tala-band tala-header[ "]/', $header );
		$this->assertMatchesRegularExpression( '/<div class="wp-block-group tala-rail[ "]/', $header );
		$this->assertStringContainsString( 'disable-default-overlay', $header, 'The theme\'s own overlay' );
		$this->assertStringContainsString( 'wp-block-navigation-overlay-close', $header );
		$this->assertSame( 1, preg_match( '/<form[^>]* role="search"[^>]*>/', $header, $search ), 'One search form' );
		$this->assertStringContainsString( 'wp-block-search__button-only', $search[0] );
		$this->assertStringContainsString( 'wp-block-search__icon-button', $search[0] );
		$this->assertStringContainsString( 'aria-label="Site"', $search[0] );

		$order = array(
			'brand'   => strpos( $header, 'tala-brand' ),
			'menu'    => preg_match( '/<nav[^>]* aria-label="Primary"/', $header, $nav, PREG_OFFSET_CAPTURE ) ? $nav[0][1] : false,
			'overlay' => strpos( $header, 'wp-block-navigation-overlay-close' ),
			'search'  => strpos( $header, 'wp-block-search__button-only' ),
			'switch'  => strpos( $header, 'class="tala-theme-switch"' ),
		);
		$this->assertNotContains( false, $order );
		$sorted = $order;
		asort( $sorted );
		$this->assertSame( array_keys( $order ), array_keys( $sorted ), 'Brand, menu (and its overlay), search, switch' );
		// The separator is drawn by CSS: an empty Group would show its placeholder in the Site Editor.
		$this->assertStringNotContainsString( 'tala-separator', $header );
	}

	/**
	 * The footer, rendered as every template places it.
	 *
	 * @return string
	 */
	private function footer(): string {
		return do_blocks( '<!-- wp:template-part {"slug":"footer","theme":"tala-blog","tagName":"footer"} /-->' );
	}

	/**
	 * The breadcrumbs (open elements, outermost first, from the footer element) of the link with
	 * a given text in some HTML, or null when there is none.
	 *
	 * @param string $html HTML.
	 * @param string $text Link text.
	 * @return string[]|null
	 */
	private function link_breadcrumbs( string $html, string $text ): ?array {
		$tags = WP_HTML_Processor::create_fragment( $html );
		$this->assertNotNull( $tags );
		while ( $tags->next_token() ) {
			if ( '#text' === $tags->get_token_type() && $text === $tags->get_modifiable_text() ) {
				$crumbs = array_slice( $tags->get_breadcrumbs(), 0, -1 ); // Without the text itself.
				return array_slice( $crumbs, (int) array_search( 'FOOTER', $crumbs, true ) );
			}
		}
		return null;
	}

	/**
	 * The footer slab: a hairline grid of cells, the brand cell first (site title, tagline, the
	 * footer note part), then the site's menu as columns, then the bottom bar and the wordmark.
	 * Each top-level item with a submenu is a column under its label, with every submenu open
	 * (no buttons: nothing to open) and a third level listed under its parent; top-level items
	 * without a submenu gather in a last column. Without a menu, the pages make one column.
	 */
	public function test_footer_menu_columns(): void {
		$this->publish_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'Contact',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Menu',
				'post_content' => '<!-- wp:navigation-link {"label":"News","url":"/news/","kind":"custom"} /-->'
					. '<!-- wp:navigation-submenu {"label":"Grades","url":"/grades/","kind":"custom"} -->'
					. '<!-- wp:navigation-link {"label":"Grade 1","url":"/grade-1/","kind":"custom"} /-->'
					. '<!-- wp:navigation-submenu {"label":"Grade 2","url":"/grade-2/","kind":"custom"} -->'
					. '<!-- wp:navigation-link {"label":"Grade 2 Math","url":"/grade-2-math/","kind":"custom"} /-->'
					. '<!-- /wp:navigation-submenu -->'
					. '<!-- /wp:navigation-submenu -->'
					. '<!-- wp:navigation-link {"label":"About","url":"/about/","kind":"custom"} /-->',
			)
		);
		$footer = $this->footer();
		$this->assertMatchesRegularExpression( '#^<footer[^>]*>\s*<div class="wp-block-group tala-slab[ "]#', $footer );
		$this->assertMatchesRegularExpression( '/<div class="wp-block-group tala-rail[ "]/', $footer );
		$this->assertMatchesRegularExpression( '/<div (?=[^>]*class="wp-block-group tala-cells[ "])[^>]*style="--tala-columns:2"/', $footer, 'The cells know the columns' );
		$this->assertStringNotContainsString( 'tala-cells--stacked', $footer, 'Two columns sit beside the brand cell' );
		$this->assertSame( 1, preg_match_all( '/<nav [^>]*>/', $footer, $navs ), 'One menu' );
		$this->assertStringContainsString( 'tala-footer-menu', $navs[0][0] );
		// Core numbers a name it has already used on the page ("Footer 2"), as each test renders the footer again.
		$this->assertMatchesRegularExpression( '/ aria-label="Footer( \d+)?"/', $navs[0][0] );
		$this->assertStringContainsString( '--tala-columns:2', $navs[0][0], 'Grades, then News and About' );
		$this->assertStringNotContainsString( 'wp-block-navigation__submenu-icon', $footer, 'Submenus are open: no buttons' );
		$this->assertStringNotContainsString( '<button', $footer );
		$this->assertStringNotContainsString( 'wp-block-navigation__responsive-container', $footer, 'Never folded' );
		$this->assertStringNotContainsString( '>Contact<', $footer, 'The menu, not the pages' );

		$column = array( 'FOOTER', 'DIV', 'DIV', 'DIV', 'NAV', 'UL', 'LI' );
		$this->assertSame( array_merge( $column, array( 'A', 'SPAN' ) ), $this->link_breadcrumbs( $footer, 'Grades' ), 'A column, under its label' );
		$this->assertSame( array_merge( $column, array( 'UL', 'LI', 'A', 'SPAN' ) ), $this->link_breadcrumbs( $footer, 'Grade 1' ) );
		$this->assertSame( array_merge( $column, array( 'UL', 'LI', 'UL', 'LI', 'A', 'SPAN' ) ), $this->link_breadcrumbs( $footer, 'Grade 2 Math' ), 'The third level under its parent' );
		foreach ( array( 'News', 'About' ) as $leaf ) {
			$this->assertSame( array_merge( $column, array( 'UL', 'LI', 'A', 'SPAN' ) ), $this->link_breadcrumbs( $footer, $leaf ), "$leaf: in the last column" );
		}
		$order = array_map( static fn( $text ) => strpos( $footer, ">$text<" ), array( 'Grades', 'Grade 2 Math', 'News', 'About' ) );
		$this->assertNotContains( false, $order );
		$sorted = $order;
		sort( $sorted );
		$this->assertSame( $sorted, $order, 'Columns first, then the gathered items in their order' );
		$this->assertMatchesRegularExpression( '#<li class="wp-block-navigation-item tala-footer-menu__links"><ul class="wp-block-navigation__submenu-container">\s*<li[^>]*><a[^>]*><span[^>]*>News</span>#', $footer );

		// Brand cell first: the site title (no link, it's the footer), the tagline.
		$brand = strpos( $footer, 'tala-footer-brand' );
		$this->assertNotFalse( $brand );
		$this->assertLessThan( strpos( $footer, '<nav ' ), $brand );
		$this->assertMatchesRegularExpression( '#tala-footer-brand[^>]*>\s*<p class="wp-block-site-title">' . preg_quote( get_bloginfo( 'name' ), '#' ) . '</p>#', $footer );

		// Without a menu: the pages, in one column.
		foreach ( get_posts( array( 'post_type' => 'wp_navigation' ) ) as $menu ) {
			wp_delete_post( $menu->ID, true );
		}
		$footer = $this->footer();
		$this->assertSame( array_merge( $column, array( 'UL', 'LI', 'A' ) ), $this->link_breadcrumbs( $footer, 'Contact' ), 'Pages in one column' );
		$this->assertMatchesRegularExpression( '/<nav [^>]*--tala-columns:1[;"]/', $footer );
		$this->assertMatchesRegularExpression( '/<div (?=[^>]*class="wp-block-group tala-cells[ "])[^>]*style="--tala-columns:1"/', $footer );
		$this->assertStringNotContainsString( 'wp-block-page-list', $footer );
		$this->assertStringNotContainsString( 'tala-cells--stacked', $footer );

		// Three columns or more: the brand cell takes a row of its own (a class, for the CSS).
		$submenu = static fn( string $label ): string => '<!-- wp:navigation-submenu {"label":"' . $label . '","url":"/' . strtolower( $label ) . '/","kind":"custom"} -->'
			. '<!-- wp:navigation-link {"label":"' . $label . ' 1","url":"/' . strtolower( $label ) . '-1/","kind":"custom"} /-->'
			. '<!-- /wp:navigation-submenu -->';
		self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Three columns',
				'post_content' => $submenu( 'Grades' ) . $submenu( 'Issuances' ) . '<!-- wp:navigation-link {"label":"Search","url":"/search/","kind":"custom"} /-->',
			)
		);
		$footer  = $this->footer();
		$this->assertMatchesRegularExpression( '/<div (?=[^>]*class="wp-block-group tala-cells[ "][^"]*\btala-cells--stacked\b)[^>]*style="--tala-columns:3"/', $footer );
	}

	/**
	 * A submenu's title goes nowhere: in the header it is a button that opens its submenu, and where
	 * submenus are always open (the footer's columns, the phone menu) it is a label, not a link,
	 * also on its own page. The items under it stay links.
	 */
	public function test_submenu_titles(): void {
		self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Menu',
				'post_content' => '<!-- wp:navigation-submenu {"label":"Resources","url":"/resources/","kind":"custom"} -->'
					. '<!-- wp:navigation-link {"label":"DepEd Resources","url":"/category/resources/","kind":"custom"} /-->'
					. '<!-- /wp:navigation-submenu -->',
			)
		);
		$header = do_blocks( '<!-- wp:template-part {"slug":"header","theme":"tala-blog","tagName":"header"} /-->' );
		$this->assertMatchesRegularExpression( '#<button (?=[^>]*data-wp-on--click="actions\.toggleMenuOnClick")[^>]*aria-label="Resources submenu" class="wp-block-navigation-item__content wp-block-navigation-submenu__toggle"[^>]*><span class="wp-block-navigation-item__label">Resources</span></button>#', $header, 'The header: a button that opens it.' );
		$this->assertSame( 1, preg_match_all( '#<a class="wp-block-navigation-item__content"\s*><span class="wp-block-navigation-item__label">Resources</span>#', $header ), 'The phone menu: a label.' );
		$this->assertStringNotContainsString( 'href="/resources/"', $header );
		$this->assertSame( 2, substr_count( $header, 'href="/category/resources/"' ), 'Its items stay links.' );

		$footer = $this->footer();
		$this->assertMatchesRegularExpression( '#<a class="wp-block-navigation-item__content"\s*><span class="wp-block-navigation-item__label">Resources</span>#', $footer, 'The footer: a label over its column.' );
		$this->assertStringNotContainsString( 'href="/resources/"', $footer );
		$this->assertStringContainsString( 'href="/category/resources/"', $footer );
	}

	/**
	 * Appearance → Customize → Header and footer menus: the footer shows the menu chosen for it, and
	 * the header and its phone menu the one chosen for them, whichever is newer. With only the
	 * footer's chosen, the header keeps WordPress's pick among the other menus, so a new footer menu
	 * never takes the header over. A Navigation block with links of its own keeps them, and a choice
	 * that isn't a published menu counts as none.
	 */
	public function test_header_and_footer_menus(): void {
		$menu   = static fn( string $title, string $label, string $date ): int => self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_date'    => $date,
				'post_content' => '<!-- wp:navigation-link {"label":"' . $label . '","url":"/' . strtolower( $label ) . '/","kind":"custom"} /-->',
			)
		);
		$header = $menu( 'Header', 'Lessons', '2026-01-01 08:00:00' );
		$footer = $menu( 'Footer', 'Privacy', '2026-02-01 08:00:00' );
		$top    = static fn(): string => do_blocks( '<!-- wp:template-part {"slug":"header","theme":"tala-blog","tagName":"header"} /-->' );

		$this->assertStringContainsString( '>Privacy<', $top(), 'No choice: WordPress\'s pick, its newest menu, as before.' );

		set_theme_mod( 'tala_blog_footer_menu', (string) $footer );
		$this->assertStringContainsString( '>Privacy<', $this->footer() );
		$this->assertStringNotContainsString( '>Lessons<', $this->footer() );
		$this->assertStringContainsString( '>Lessons<', $top(), 'The footer\'s menu, newer, doesn\'t take the header over.' );
		$this->assertStringNotContainsString( '>Privacy<', $top() );

		$newest = $menu( 'Newest', 'Archive', '2026-03-01 08:00:00' );
		$this->assertStringContainsString( '>Archive<', $top(), 'Still WordPress\'s pick among the others.' );
		set_theme_mod( 'tala_blog_header_menu', (string) $header );
		$html = $top();
		$this->assertSame( 2, substr_count( $html, '>Lessons<' ), 'Chosen: in the header and in its phone menu.' );
		$this->assertStringNotContainsString( '>Archive<', $html );
		$this->assertStringContainsString( '>Privacy<', $this->footer() );

		$own = do_blocks( '<!-- wp:navigation {"overlayMenu":"never"} --><!-- wp:navigation-link {"label":"Own","url":"/own/","kind":"custom"} /--><!-- /wp:navigation -->' );
		$this->assertStringContainsString( '>Own<', $own, 'Links of its own stay.' );
		$this->assertStringNotContainsString( '>Lessons<', $own );

		set_theme_mod( 'tala_blog_header_menu', (string) $this->publish_post( array( 'post_type' => 'page' ) ) );
		$this->assertStringContainsString( '>Archive<', $top(), 'A page isn\'t a menu: no choice.' );
		wp_update_post(
			array(
				'ID'          => $newest,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( 0, tala_blog_sanitize_menu( $newest ), 'Nor is a draft.' );
		$this->assertSame( $header, tala_blog_sanitize_menu( (string) $header ) );
		remove_theme_mod( 'tala_blog_header_menu' );
		remove_theme_mod( 'tala_blog_footer_menu' );

		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$manager = new WP_Customize_Manager();
		tala_blog_customize_register( $manager );
		$this->assertNotNull( $manager->get_section( 'tala_blog_menus' ) );
		foreach ( array( 'tala_blog_header_menu', 'tala_blog_footer_menu' ) as $id ) {
			$control = $manager->get_control( $id );
			$this->assertNotNull( $control );
			$this->assertSame( 'select', $control->type );
			$this->assertSame( 'Automatic', $control->choices[0] ?? null );
			$this->assertSame( 'Header', $control->choices[ $header ] ?? null );
			$this->assertSame( 'Footer', $control->choices[ $footer ] ?? null );
			$this->assertArrayNotHasKey( $newest, $control->choices, 'Published menus only.' );
			$this->assertSame( '0', $manager->get_setting( $id )->default );
		}
	}

	/**
	 * The wordmark: the site title in giant capitals (CSS), sized from its length (characters as
	 * readers see them, spaces counted), hidden from screen readers and not a link. It is not a
	 * second site title: the brand cell's is the one.
	 */
	public function test_wordmark(): void {
		$wordmark = static function ( string $html ): string {
			return preg_match( '#<p [^>]*tala-wordmark[^>]*>.*?</p>#s', $html, $match ) ? $match[0] : '';
		};
		update_option( 'blogname', 'DepEd Tambayan' );
		$mark = $wordmark( $this->footer() );
		$this->assertNotSame( '', $mark );
		$this->assertStringContainsString( 'aria-hidden="true"', $mark );
		$this->assertStringContainsString( 'style="--tala-chars:14"', $mark );
		$this->assertStringContainsString( '>DepEd Tambayan</p>', $mark );
		$this->assertStringNotContainsString( '<a', $mark );
		$this->assertStringNotContainsString( 'wp-block-site-title', $mark, 'Not another site title' );

		// Characters, not bytes or entities.
		update_option( 'blogname', 'Niño & Co' );
		$this->assertStringContainsString( 'style="--tala-chars:9"', $wordmark( $this->footer() ) );

		// A link (the block's "Make title link to home page") is taken out of the tab order too.
		$linked = do_blocks( '<!-- wp:site-title {"level":0,"className":"tala-wordmark"} /-->' );
		$this->assertStringContainsString( 'tabindex="-1"', $linked );
		$this->assertStringContainsString( 'aria-hidden="true"', $linked );

		// Other site titles are left alone.
		$this->assertStringNotContainsString( 'aria-hidden', do_blocks( '<!-- wp:site-title {"level":0} /-->' ) );

		update_option( 'blogname', '' );
		$this->assertSame( '', trim( $wordmark( $this->footer() ) ), 'No title, no wordmark' );
	}

	/**
	 * The year in the footer's copyright line comes from the "tala-blog/year" Block Bindings
	 * source: this year in the site's time zone, never a year written into the pattern.
	 */
	public function test_year_binding(): void {
		$this->assertTrue( get_block_bindings_source( 'tala-blog/year' ) instanceof WP_Block_Bindings_Source );
		$year = do_blocks( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"tala-blog/year"}}}} --><p></p><!-- /wp:paragraph -->' );
		$this->assertMatchesRegularExpression( '#^<p[^>]*>' . wp_date( 'Y' ) . '</p>$#', trim( $year ) );

		$footer = $this->footer();
		$this->assertMatchesRegularExpression( '#<p class="tala-year[ "][^>]*>' . wp_date( 'Y' ) . '</p>#', $footer );
		$this->assertDoesNotMatchRegularExpression( '/\b20\d\d\b(?<!' . wp_date( 'Y' ) . ')/', wp_strip_all_tags( $footer ), 'No other year' );
	}

	/**
	 * The footer note: a template part the theme ships empty, filled per site in the Site
	 * Editor. Empty, it prints nothing (not even its wrapper); filled, its text shows in the brand
	 * cell. Any empty template part prints nothing.
	 */
	public function test_empty_footer_note_prints_nothing(): void {
		$part = get_block_template( 'tala-blog//footer-note', 'wp_template_part' );
		$this->assertInstanceOf( WP_Block_Template::class, $part );
		$this->assertSame( 'uncategorized', $part->area );
		$this->assertSame( '', trim( $part->content ) );

		$footer = $this->footer();
		$this->assertSame( 1, substr_count( $footer, 'wp-block-template-part' ), 'Only the footer itself' );

		$this->assertSame( '', tala_blog_empty_part( "<div class=\"wp-block-template-part\">\n\t </div>", array( 'blockName' => 'core/template-part' ) ) );
		$this->assertSame( '<div class="wp-block-template-part"><p>x</p></div>', tala_blog_empty_part( '<div class="wp-block-template-part"><p>x</p></div>', array( 'blockName' => 'core/template-part' ) ) );

		$note = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template_part',
				'post_status'  => 'publish',
				'post_name'    => 'footer-note',
				'post_title'   => 'Footer note',
				'post_content' => '<!-- wp:paragraph --><p>An independent site.</p><!-- /wp:paragraph -->',
				'tax_input'    => array( 'wp_theme' => array( 'tala-blog' ) ),
			)
		);
		wp_set_post_terms( $note, array( 'tala-blog' ), 'wp_theme' );
		$footer = $this->footer();
		$this->assertMatchesRegularExpression( '#tala-footer-brand.*<div class="wp-block-template-part">\s*<p[^>]*>An independent site.</p>\s*</div>#s', $footer );
		$this->assertLessThan( strpos( $footer, '<nav ' ), strpos( $footer, 'An independent site.' ), 'In the brand cell' );
	}

	/**
	 * The closing call to action: the theme ships the Call to action part empty, and an empty part
	 * prints nothing (no wrapper); filled in the Site Editor (saved in the database), it prints once,
	 * after the content and before the footer. The "Closing call to action" pattern gives NASH's
	 * band: graph paper, eyebrow, display h2, lead, one primary button and a meta line, in a surface
	 * card.
	 */
	public function test_call_to_action_part(): void {
		$part = get_block_template( 'tala-blog//call-to-action', 'wp_template_part' );
		$this->assertInstanceOf( WP_Block_Template::class, $part );
		$this->assertSame( 'uncategorized', $part->area );
		$this->assertSame( 'Call to action', $part->title );
		$this->assertSame( '', trim( $part->content ) );

		$post = $this->publish_post(
			array(
				'post_title'   => 'Seating plans',
				'post_content' => '<!-- wp:paragraph --><p>The article text.</p><!-- /wp:paragraph -->',
			)
		);
		$page = function () use ( $post ): string {
			$this->go_to( get_permalink( $post ) );
			$GLOBALS['_wp_current_template_content'] = get_block_template( 'tala-blog//single' )->content;
			try {
				return get_the_block_template_html();
			} finally {
				unset( $GLOBALS['_wp_current_template_content'] );
			}
		};
		$html = $page();
		$this->assertStringContainsString( 'The article text.', $html );
		$this->assertSame( 1, preg_match( '#</main>(.*?)<footer class="wp-block-template-part#s', $html, $between ) );
		$this->assertSame( '', trim( $between[1] ), 'An empty part prints nothing, not even its wrapper' );

		$saved = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template_part',
				'post_status'  => 'publish',
				'post_name'    => 'call-to-action',
				'post_title'   => 'Call to action',
				'post_content' => '<!-- wp:paragraph --><p>Practise for the exam.</p><!-- /wp:paragraph -->',
			)
		);
		wp_set_post_terms( $saved, array( 'tala-blog' ), 'wp_theme' );
		$html = $page();
		$this->assertSame( 1, substr_count( $html, 'Practise for the exam.' ), 'Printed once' );
		$this->assertGreaterThan( strpos( $html, 'The article text.' ), strpos( $html, 'Practise for the exam.' ), 'After the content' );
		$this->assertGreaterThan( strpos( $html, '</main>' ), strpos( $html, 'Practise for the exam.' ), 'After main' );
		$this->assertLessThan( strpos( $html, '<footer class="wp-block-template-part' ), strpos( $html, 'Practise for the exam.' ), 'Before the footer' );

		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'tala-blog/call-to-action' );
		$this->assertIsArray( $pattern );
		$this->assertSame( 'Closing call to action', $pattern['title'] );
		$this->assertContains( 'call-to-action', $pattern['categories'] );
		$this->assertContains( 'core/template-part/uncategorized', $pattern['blockTypes'] );
		$this->assertTrue( $pattern['inserter'] ?? true, 'In the inserter' );
		$content = $pattern['content'];
		$this->assertMatchesRegularExpression( '/class="wp-block-group tala-band tala-band--paper[ "]/', $content );
		// A region landmark named for screen readers, so a filled part isn't content outside every landmark.
		$this->assertMatchesRegularExpression( '/<section class="wp-block-group tala-band tala-band--paper" aria-label="Call to action">/', $content );
		$this->assertStringContainsString( '"ariaLabel":"Call to action"', $content, 'The block attribute matches, so the block stays valid' );
		$this->assertMatchesRegularExpression( '/<h2 class="wp-block-heading tala-display[ "]/', $content );
		$this->assertMatchesRegularExpression( '/<p class="tala-eyebrow[ "]/', $content );
		$this->assertMatchesRegularExpression( '/<p class="tala-lead[ "]/', $content );
		$this->assertMatchesRegularExpression( '/<p class="tala-meta[ "]/', $content );
		$this->assertMatchesRegularExpression( '/class="wp-block-group tala-cta[ "]/', $content, 'The card' );
		$this->assertSame( 1, preg_match_all( '/<div class="wp-block-button[ "]/', $content ), 'One button' );
		$this->assertStringNotContainsString( 'is-style-outline', $content, 'A primary button' );
		foreach ( array( 'Next step', 'Ready for the next step?', 'Tell readers where to go next.', 'Get started' ) as $text ) {
			$this->assertStringContainsString( $text, $content );
		}
		$css = (string) file_get_contents( get_theme_file_path( 'assets/theme.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$this->assertStringContainsString( 'box-shadow: 6px 6px 0 color-mix(in srgb, var(--wp--preset--color--accent) 10%, transparent);', $css, 'NASH\'s flat offset shadow on the card' );
	}

	/**
	 * Type: no faux bold or italic, balanced headings, tabular figures in dates and tables; the
	 * page-level h1 defaults to the "title" role (upright, regular weight), and display titles come
	 * only from the .tala-display class, in Inter Display's light italic (the preloaded file).
	 * Hover styles live in theme.css under the media query for devices that hover, never in
	 * theme.json, which can't express it.
	 */
	public function test_type_and_hover_styles(): void {
		$h1 = (array) wp_get_global_styles( array( 'elements', 'h1' ) );
		$this->assertSame( 'var(--wp--preset--font-size--x-large)', $h1['typography']['fontSize'] ?? null );
		$this->assertSame( '-0.025em', $h1['typography']['letterSpacing'] ?? null );
		$this->assertSame( '1.2', $h1['typography']['lineHeight'] ?? null );
		$this->assertSame( '400', wp_get_global_styles( array( 'elements', 'heading', 'typography', 'fontWeight' ) ) );
		$this->assertStringNotContainsString( ':hover', (string) file_get_contents( get_theme_file_path( 'theme.json' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.

		$css   = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( get_theme_file_path( 'assets/theme.css' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$rules = self::css_rules( $css );
		$find  = static function ( string $selector ) use ( $rules ): string {
			foreach ( $rules as $rule ) {
				if ( array() === $rule['at'] && preg_replace( '/\s+/', ' ', $rule['selector'] ) === $selector ) {
					return $rule['body'];
				}
			}
			return '';
		};
		$this->assertStringContainsString( 'font-synthesis: none', $find( 'html' ) );
		$this->assertStringContainsString( 'text-wrap: balance', $find( ':where(h1, h2, h3, h4)' ) );
		$this->assertStringContainsString( 'font-variant-numeric: tabular-nums', $find( ':where(time, td, th)' ) );
		$this->assertStringContainsString( 'max-inline-size: calc(72 * 0.628em)', $find( '.tala-measure' ), 'NASH\'s measure-72' );
		$this->assertSame( '48rem', wp_get_global_settings( array( 'layout', 'contentSize' ) ), 'The editor\'s column matches it' );
		$display = $find( '.tala-display' );
		$this->assertStringContainsString( 'font-family: var(--wp--preset--font-family--inter-display)', $display );
		$this->assertStringContainsString( 'font-style: italic', $display );
		$this->assertStringContainsString( 'font-weight: 300', $display );

		$hovers = array_filter( $rules, static fn( $rule ) => str_contains( $rule['selector'], ':hover' ) );
		$this->assertNotEmpty( $hovers );
		foreach ( $hovers as $rule ) {
			$this->assertContains( '@media (hover: hover) and (pointer: fine)', $rule['at'], $rule['selector'] );
		}
		foreach ( $rules as $rule ) {
			if ( preg_match( '/(?:^|;)\s*(?:translate|scale|transition(?:-property)?)\s*:\s*+(?!none)/', $rule['body'] ) ) {
				$this->assertContains( '@media (prefers-reduced-motion: no-preference)', $rule['at'], "Motion only for readers who don't ask for less: {$rule['selector']}" );
			}
		}
	}

	/**
	 * The style rules of a stylesheet (without comments), each with the at-rules around it.
	 *
	 * @param string $css CSS.
	 * @return array<int, array{selector: string, body: string, at: string[]}>
	 */
	private static function css_rules( string $css ): array {
		$rules   = array();
		$stack   = array();
		$prelude = '';
		$depth   = 0;
		$body    = '';
		foreach ( str_split( $css ) as $char ) {
			if ( '{' === $char ) {
				$prelude = trim( $prelude );
				if ( str_starts_with( $prelude, '@' ) ) {
					$stack[] = $prelude;
				} else {
					$depth = 1;
					$body  = '';
					$open  = $prelude;
				}
				$prelude = '';
			} elseif ( '}' === $char ) {
				if ( $depth ) {
					$rules[] = array(
						'selector' => $open ?? '',
						'body'     => trim( $body ),
						'at'       => $stack,
					);
					$depth   = 0;
				} else {
					array_pop( $stack );
				}
				$prelude = '';
			} elseif ( $depth ) {
				$body .= $char;
			} else {
				$prelude .= ( ';' === $char ) ? '' : $char;
				if ( ';' === $char ) {
					$prelude = '';
				}
			}
		}
		return $rules;
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
	 * "Read next" under an article: a navigation landmark of its own name, before the author box,
	 * with the older and newer articles as hairline cells whose label sits inside the link. An
	 * article with no other article to point to (a site's only one) gets no landmark, heading
	 * included.
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
		$this->assertSame( 'Read next', $tags->get_attribute( 'aria-label' ) );
		$this->assertMatchesRegularExpression( '#<nav\b.*<h2 class="[^"]*\btala-eyebrow\b[^"]*">Read next</h2>.*</nav>#s', $html );
		$this->assertMatchesRegularExpression( '#<nav\b.*<div class="wp-block-group tala-post-nav__links[ "][^>]*>\s*<div class="post-navigation-link-previous wp-block-post-navigation-link">.*?<a [^>]*rel="prev"[^>]*><span class="post-navigation-link__label">Older article</span> <span class="post-navigation-link__title">Seating plans</span></a>.*</nav>#s', $html, 'A cell; the label is part of the link, so the whole cell is the target.' );
		$this->assertStringNotContainsString( '<img', substr( $html, 0, (int) strpos( $html, 'wp-block-post-author' ) ), 'No pictures' );
		$this->assertStringNotContainsString( 'Newer article', $html, 'The newest article has no newer one.' );
		$this->assertLessThan( strpos( $html, 'wp-block-post-author' ), strpos( $html, '<nav' ), 'Before the author box' );

		wp_delete_post( $older, true );
		$this->go_to( get_permalink( $newer ) );
		$html = $this->render( $after, $newer );
		$this->assertStringContainsString( 'wp-block-post-author', $html, 'The author box stays' );
		$this->assertStringNotContainsString( '<nav', $html, 'Nothing to link to: no landmark' );
		$this->assertStringNotContainsString( 'Read next', $html, 'Nor its heading' );

		$words = '<!-- wp:group {"tagName":"nav"} --><nav class="wp-block-group"><p>Soon</p></nav><!-- /wp:group -->';
		$this->assertSame( '', do_blocks( $words ), 'A navigation landmark without links is left out, whatever it says' );
		$linked = '<!-- wp:group {"tagName":"nav"} --><nav class="wp-block-group"><p><a href="/about/">About</a></p></nav><!-- /wp:group -->';
		$this->assertStringContainsString( '<a href="/about/">About</a>', do_blocks( $linked ), 'One with a link stays' );
	}

	/**
	 * "Read next" lists up to three recent articles from the article's own categories (any of
	 * them), newest first, never the article itself or one from other categories; a pinned post
	 * takes no special place. They are ruled rows as in article lists, without pictures: the date,
	 * then the title (subtitle type) and summary. Without any, the list is left out and the cells
	 * stand alone.
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
		preg_match_all( '#<div class="wp-block-group tala-row[ "].*?<time\b.*?<h3 class="(?=[^"]*\bwp-block-post-title\b)(?=[^"]*\btala-subtitle\b)[^"]*"><a href="[^"]*"[^>]*>([^<]*)</a></h3>\s*<div class="wp-block-post-excerpt\b#s', $html, $titles );
		$this->assertSame( array( 'Homework habits', 'Forms to keep', 'Class rules' ), $titles[1], 'Newest three of its categories; the pinned oldest one takes no special place' );
		$this->assertStringNotContainsString( '>Budget season<', substr( $html, 0, (int) strpos( $html, 'tala-post-nav__links' ) ), 'Other categories are not related (the older-article card below may still be one)' );
		$this->assertStringNotContainsString( '<img', $html, 'No thumbnails' );
		$this->assertStringNotContainsString( 'wp-block-post-featured-image', $html );
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
								'color' => array( '#f4f4f2' ),
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
		$this->assertStringContainsString( 'content="#16181d" media="(prefers-color-scheme: dark)"', $meta, "The theme's own dark colour" );
	}

	/**
	 * One identity on every site: the theme offers no style variations (colour or typography
	 * presets) in the Site Editor.
	 */
	public function test_no_style_variations(): void {
		$this->assertSame( array(), WP_Theme_JSON_Resolver::get_style_variations() );
	}

	/**
	 * The theme's colours, light and dark (settings.custom.color.dark, which the CSS swaps in), with
	 * text that keeps WCAG AA contrast (4.5:1) on the page, the surface and the washed background,
	 * and on the accent and the footer slab, in both.
	 */
	public function test_colour_presets(): void {
		$palette = array_column( wp_get_global_settings( array( 'color', 'palette', 'theme' ) ), 'color', 'slug' );
		$this->assertSame(
			array(
				'base'        => '#f4f4f2',
				'surface'     => '#fafafa',
				'contrast'    => '#393d45',
				'heading'     => '#000000',
				'muted'       => '#585f6c',
				'accent'      => '#1348dc',
				'on-accent'   => '#ffffff',
				'accent-deep' => '#053794',
				'wash'        => '#ebebec',
				'border'      => '#dadde2',
				'rule'        => '#cbcfd4',
				'slab'        => '#1348dc',
				'on-slab'     => '#ffffff',
			),
			$palette
		);
		$dark = wp_get_global_settings( array( 'custom', 'color', 'dark' ) );
		$this->assertSame(
			array(
				'base'        => '#16181d',
				'surface'     => '#1c1f26',
				'contrast'    => '#d3d6dc',
				'heading'     => '#f2f3f5',
				'muted'       => '#a3aab7',
				'accent'      => '#7d9bff',
				'on-accent'   => '#0b1433',
				'accent-deep' => '#4f73e6',
				'wash'        => '#232730',
				'border'      => '#2e323b',
				'rule'        => '#2c3038',
				'slab'        => '#1640b8',
				'on-slab'     => '#ffffff',
			),
			$dark
		);

		$pairs = array( array( 'contrast', 'base' ), array( 'heading', 'base' ), array( 'muted', 'base' ), array( 'accent', 'base' ), array( 'muted', 'surface' ), array( 'contrast', 'wash' ), array( 'muted', 'wash' ), array( 'accent', 'wash' ), array( 'on-accent', 'accent' ), array( 'on-slab', 'slab' ) );
		foreach ( array(
			'light' => $palette,
			'dark'  => $dark,
		) as $scheme => $colors ) {
			foreach ( $pairs as list( $text, $background ) ) {
				$this->assertGreaterThanOrEqual( 4.5, self::contrast( $colors[ $text ], $colors[ $background ] ), "$scheme: $text on $background" );
			}
		}
	}

	/**
	 * Buttons keep their accent fill on hover: the deep accent is only the button's lower edge (text
	 * on it falls below 4.5:1 in dark mode), and hover styles belong under the CSS media query for
	 * devices that hover, which theme.json can't express.
	 */
	public function test_buttons_keep_their_colours_on_hover(): void {
		$button = (array) wp_get_global_styles( array( 'elements', 'button' ) );
		$this->assertSame( 'var(--wp--preset--color--accent)', $button['color']['background'] ?? null );
		$this->assertArrayNotHasKey( ':hover', $button );
	}

	/**
	 * The type scale: six sizes from meta text to display titles, as written (WordPress doesn't make
	 * them fluid; the two largest are fluid already).
	 */
	public function test_typography_presets(): void {
		$sizes = array(
			'x-small'  => '0.8125rem',
			'small'    => '0.9375rem',
			'medium'   => '1.0625rem',
			'large'    => '1.1875rem',
			'x-large'  => 'clamp(1.5rem, 1.3rem + 0.9vw, 2rem)',
			'xx-large' => 'clamp(2.25rem, 1.4rem + 3.6vw, 3.75rem)',
		);
		$this->assertSame( $sizes, array_column( wp_get_global_settings( array( 'typography', 'fontSizes', 'theme' ) ), 'size', 'slug' ) );
		$variables = wp_get_global_stylesheet( array( 'variables' ) );
		foreach ( $sizes as $slug => $size ) {
			$this->assertStringContainsString( "--wp--preset--font-size--$slug: $size;", $variables );
		}
	}

	/**
	 * Inter, self-hosted with its licence: the only font family besides its display cut, with
	 * fallbacks sized like it; the two Latin files every page needs are preloaded before any CSS.
	 */
	public function test_inter_font(): void {
		$families = array_column( wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) ), null, 'slug' );
		$this->assertSame( array( 'inter', 'inter-display' ), array_keys( $families ) );
		$this->assertStringStartsWith( '"Inter", "Inter Fallback", "Inter Fallback Roboto"', $families['inter']['fontFamily'] );
		$this->assertFileExists( get_theme_file_path( 'assets/fonts/OFL.txt' ) );
		foreach ( $families as $family ) {
			foreach ( $family['fontFace'] as $face ) {
				$this->assertFileExists( get_theme_file_path( str_replace( 'file:./', '', $face['src'][0] ) ) );
			}
		}

		// A fresh style queue, so the theme's styles print in this <head> whatever printed before.
		$GLOBALS['wp_styles'] = new WP_Styles();
		$head                 = get_echo( 'wp_head' );
		$this->assertSame( 1, substr_count( $head, 'InterVariable-latin.woff2" as="font" type="font/woff2" crossorigin' ) );
		$this->assertSame( 1, substr_count( $head, 'InterDisplay-LightItalic-latin.woff2" as="font" type="font/woff2" crossorigin' ) );
		$this->assertLessThan( strpos( $head, '<style' ), strpos( $head, 'rel="preload"' ), 'Preloads before styles.' );
		foreach ( array( 'InterVariable-latin.woff2', 'InterDisplay-LightItalic-latin.woff2' ) as $file ) {
			$this->assertStringContainsString( "url('" . get_theme_file_uri( "assets/fonts/$file" ) . "') format('woff2')", $head, "$file: the preloaded file is the one the CSS asks for" );
		}
	}

	/**
	 * The theme's CSS is inlined into every page without the comments that explain it in
	 * assets/theme.css, nor their indentation: the rules all arrive, the prose doesn't.
	 */
	public function test_inline_css_without_comments(): void {
		$saved                = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_styles'] = new WP_Styles();
		try {
			tala_blog_styles();
			$data = wp_styles()->get_data( 'tala-blog', 'after' );
			$css  = implode( "\n", is_array( $data ) ? $data : array() );
		} finally {
			$GLOBALS['wp_styles'] = $saved;
		}
		$file = (string) file_get_contents( get_theme_file_path( 'assets/theme.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$this->assertStringContainsString( '/*', $file, 'The file keeps its comments' );
		$this->assertStringNotContainsString( '/*', $css );
		$this->assertStringNotContainsString( '*/', $css );
		$this->assertDoesNotMatchRegularExpression( '/\n[ \t]/', $css, 'No indentation' );
		$this->assertLessThan( 0.8 * strlen( $file ), strlen( $css ) );
		$this->assertStringContainsString( 'box-shadow: 6px 6px 0 color-mix(in srgb, var(--wp--preset--color--accent) 10%, transparent);', $css, 'Rules arrive as written' );
		$rules = (string) preg_replace( '#/\*.*?\*/#s', '', $file );
		$this->assertSame( substr_count( $rules, '{' ), substr_count( $css, '{' ), 'Every rule arrives' );
	}

	/**
	 * If PCRE fails while stripping the comments (its backtrack limit, say), the CSS is inlined as
	 * the file has it, never left out: a page without it would be unstyled.
	 */
	public function test_inline_css_survives_a_pcre_failure(): void {
		$saved                = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_styles'] = new WP_Styles();
		$limits               = array(
			'pcre.backtrack_limit' => ini_get( 'pcre.backtrack_limit' ),
			'pcre.jit'             => ini_get( 'pcre.jit' ),
		);
		ini_set( 'pcre.jit', '0' ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- restored below; the test needs PCRE to fail.
		ini_set( 'pcre.backtrack_limit', '1' ); // phpcs:ignore WordPress.PHP.IniSet.Risky,WordPress.PHP.IniSet.pcre_backtrack_limit_Disallowed -- restored below.
		try {
			tala_blog_styles();
			$failed = preg_last_error();
			$data   = wp_styles()->get_data( 'tala-blog', 'after' );
			$css    = implode( "\n", is_array( $data ) ? $data : array() );
		} finally {
			foreach ( $limits as $name => $value ) {
				ini_set( $name, (string) $value ); // phpcs:ignore WordPress.PHP.IniSet.Risky,WordPress.PHP.IniSet.pcre_backtrack_limit_Disallowed -- restoring.
			}
			$GLOBALS['wp_styles'] = $saved;
		}
		$this->assertSame( PREG_BACKTRACK_LIMIT_ERROR, $failed, 'PCRE failed' );
		$file = (string) file_get_contents( get_theme_file_path( 'assets/theme.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$this->assertSame( trim( $file ), $css, 'The file as it is' );
	}

	/**
	 * The browser's theme colour and the dark-mode colours follow a chosen colour preset, also when
	 * the person who chose it can't save unfiltered HTML: WordPress then keeps only the palette, and
	 * the dark colours come from the preset's file. The theme ships no presets, but a child theme
	 * can (tests/phpunit/fixtures/themes/tala-blog-child). A palette changed after choosing a preset
	 * is the site's own, with the theme's dark colours.
	 */
	public function test_dark_colours_follow_a_colour_preset(): void {
		$themes = dirname( __DIR__ ) . '/fixtures/themes';
		$preset = (array) wp_json_file_decode( "$themes/tala-blog-child/styles/colors/slate.json", array( 'associative' => true ) );
		$base   = wp_list_pluck( $preset['settings']['color']['palette'], 'color', 'slug' )['base'];
		$dark   = $preset['settings']['custom']['color']['dark']['base'];
		$child  = static fn(): string => 'tala-blog-child';
		register_theme_directory( $themes );
		search_theme_directories( true );
		add_filter( 'stylesheet', $child );
		try {
			$this->assertSame( 'tala-blog', wp_get_theme()->get_template(), 'A child theme of Tala Blog' );
			foreach ( array(
				'unfiltered' => false,
				'filtered'   => true,
			) as $case => $filtered ) {
				$this->choose_variation( $preset, $filtered );
				$meta = get_echo( 'tala_blog_theme_color' );
				$this->assertStringContainsString( '<meta name="theme-color" content="' . $base . '" media="(prefers-color-scheme: light)">', $meta, $case );
				$this->assertStringContainsString( '<meta name="theme-color" content="' . $dark . '" media="(prefers-color-scheme: dark)">', $meta, $case );
				$this->assertStringContainsString( '--wp--custom--color--dark--base: ' . $dark, wp_get_global_stylesheet( array( 'variables' ) ), $case );
			}

			$changed = $preset;
			$changed['settings']['color']['palette'][0]['color'] = '#ffffff';
			$this->choose_variation( $changed, true );
			$this->assertStringContainsString( 'content="#16181d" media="(prefers-color-scheme: dark)"', get_echo( 'tala_blog_theme_color' ) );
		} finally {
			remove_filter( 'stylesheet', $child );
			$GLOBALS['wp_theme_directories'] = array_values( array_diff( $GLOBALS['wp_theme_directories'], array( $themes ) ) );
			search_theme_directories( true );
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

	/**
	 * Gravity Forms: the theme's rules for each of its form themes follow Gravity Forms' own stylesheet
	 * for that theme, once, and only when a form on the page uses it. Gravity Forms isn't installed in
	 * the tests: its stylesheets are registered by their names, as Gravity Forms registers them.
	 */
	public function test_gravity_forms_styles_follow_its_stylesheets(): void {
		$saved                = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_styles'] = new WP_Styles();
		try {
			foreach ( array( 'gravity_forms_theme_framework', 'gravity_forms_orbital_theme', 'gform_basic', 'gform_theme' ) as $handle ) {
				wp_register_style( $handle, false, array(), '3.2.0' );
			}
			$after = static function ( string $handle ): array {
				$data = wp_styles()->get_data( $handle, 'after' );
				return is_array( $data ) ? $data : array();
			};

			// No form, or a form without Gravity Forms' styles: nothing.
			do_action( 'gform_enqueue_scripts', array( 'id' => 1 ), false );
			$this->assertSame( array(), $after( 'gravity_forms_orbital_theme' ) );
			$this->assertSame( array(), $after( 'gform_theme' ) );

			// An Orbital form: the theme's colours for Orbital's CSS API, in light and dark mode.
			wp_enqueue_style( 'gravity_forms_theme_framework' );
			wp_enqueue_style( 'gravity_forms_orbital_theme' );
			do_action( 'gform_enqueue_scripts', array( 'id' => 1 ), false );
			do_action( 'gform_enqueue_scripts', array( 'id' => 2 ), false ); // A second form on the page.
			$orbital = $after( 'gravity_forms_orbital_theme' );
			$this->assertCount( 1, $orbital, 'Once per page, however many forms.' );
			$this->assertStringContainsString( 'body .gform-theme--framework.gform-theme:not(#tala-blog-gravity-forms)', $orbital[0] );
			$this->assertStringContainsString( '--gf-ctrl-label-color-primary: var(--wp--preset--color--contrast);', $orbital[0] );
			$this->assertStringContainsString( '--gf-color-in-ctrl: var(--wp--preset--color--base);', $orbital[0] );
			$this->assertStringContainsString( '@media (prefers-color-scheme: dark)', $orbital[0] );
			$this->assertSame( array(), $after( 'gform_theme' ), "The 2.5 theme's rules wait for a 2.5 form." );

			// A form with Gravity Forms' 2.5 theme.
			wp_enqueue_style( 'gform_basic' );
			wp_enqueue_style( 'gform_theme' );
			do_action( 'gform_enqueue_scripts', array( 'id' => 3 ), false );
			$legacy = $after( 'gform_theme' );
			$this->assertCount( 1, $legacy );
			$this->assertStringContainsString( 'body .gform_wrapper.gravity-theme', $legacy[0] );
			$this->assertSame( array(), $after( 'gform_basic' ) );
			$this->assertCount( 1, $after( 'gravity_forms_orbital_theme' ) );
		} finally {
			$GLOBALS['wp_styles'] = $saved;
		}
	}

	/**
	 * The script tag with this ID in the printed HTML (its attributes in any order), or ''.
	 *
	 * @param string $html Printed scripts.
	 * @param string $id   The tag's id.
	 */
	private function script_tag( string $html, string $id ): string {
		return preg_match( '#<script\b[^>]*\bid="' . preg_quote( $id, '#' ) . '"[^>]*>#', $html, $m ) ? $m[0] : '';
	}

	/**
	 * The scripts as PDF Embedder registers them: in <head>, without a loading strategy.
	 */
	private function register_pdf_embedder(): void {
		wp_register_script( 'pdfemb_pdfjs', 'https://example.org/pdf.js', array( 'jquery' ), '2.16.105', false );
		wp_register_script( 'pdfemb_embed_pdf', 'https://example.org/pdfemb.min.js', array( 'jquery', 'pdfemb_pdfjs' ), '5.0.2', false );
		wp_localize_script( 'pdfemb_embed_pdf', 'pdfemb_trans', array( 'worker_src' => 'https://example.org/pdf.worker.js' ) );
	}

	/**
	 * A page with a PDF viewer gets PDF Embedder's scripts and jQuery deferred, so they don't hold
	 * back the article's text; a page without one keeps jQuery as it was, and a blocking script that
	 * needs jQuery keeps jQuery blocking too.
	 */
	public function test_pdf_viewer_scripts_are_deferred(): void {
		$saved = $GLOBALS['wp_scripts'] ?? null;
		try {
			// A page with the viewer.
			$GLOBALS['wp_scripts'] = new WP_Scripts();
			$this->register_pdf_embedder();
			wp_enqueue_script( 'pdfemb_embed_pdf' );
			tala_blog_pdf_viewer();
			$head = get_echo( 'wp_print_head_scripts' );
			foreach ( array( 'pdfemb_embed_pdf-js', 'pdfemb_pdfjs-js', 'jquery-core-js', 'jquery-migrate-js' ) as $id ) {
				$this->assertMatchesRegularExpression( '#\sdefer[\s>]#', $this->script_tag( $head, $id ), "$id is deferred." );
			}
			$this->assertStringContainsString( 'var pdfemb_trans', $head, "The viewer's settings still come first." );

			// Its toolbar's contrast, after PDF Embedder's own stylesheet, once.
			$saved_styles         = $GLOBALS['wp_styles'] ?? null;
			$GLOBALS['wp_styles'] = new WP_Styles();
			wp_enqueue_style( 'pdfemb_embed_pdf_css', 'https://example.org/pdfemb.css', array(), '5.0.2' );
			tala_blog_pdf_viewer();
			tala_blog_pdf_viewer();
			$after = wp_styles()->get_data( 'pdfemb_embed_pdf_css', 'after' );
			$this->assertIsArray( $after );
			$this->assertCount( 1, $after );
			$this->assertStringContainsString( '.pdfemb-viewer .pdfemb-toolbar', $after[0] );
			$GLOBALS['wp_styles'] = $saved_styles;

			// The same page with a form whose script needs jQuery right away: jQuery stays blocking.
			$GLOBALS['wp_scripts'] = new WP_Scripts();
			$this->register_pdf_embedder();
			wp_enqueue_script( 'pdfemb_embed_pdf' );
			wp_enqueue_script( 'gform_gravityforms', 'https://example.org/gravityforms.js', array( 'jquery' ), '3.2.0', false );
			wp_add_inline_script( 'gform_gravityforms', 'gform.initializeOnLoaded();' );
			tala_blog_pdf_viewer();
			$head   = get_echo( 'wp_print_head_scripts' );
			$jquery = $this->script_tag( $head, 'jquery-core-js' );
			$this->assertNotSame( '', $jquery );
			$this->assertDoesNotMatchRegularExpression( '#\sdefer[\s>]#', $jquery, 'jQuery blocks when a blocking script needs it.' );

			// A page without the viewer: nothing changes.
			$GLOBALS['wp_scripts'] = new WP_Scripts();
			$this->register_pdf_embedder();
			wp_enqueue_script( 'jquery' );
			tala_blog_pdf_viewer();
			$this->assertFalse( wp_scripts()->get_data( 'jquery-core', 'strategy' ) );
			$this->assertSame( 1, has_action( 'wp_head', 'tala_blog_pdf_viewer' ), 'Before the head scripts print (priority 9).' );
		} finally {
			$GLOBALS['wp_scripts'] = $saved;
		}
	}
}
